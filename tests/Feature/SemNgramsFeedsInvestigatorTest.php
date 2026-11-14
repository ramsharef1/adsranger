<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use App\Support\Attribution\PerformanceInvestigator;
use App\Support\Attribution\ProfitAndAttributionManager;
use App\Support\Attribution\SearchTermNgramMiner;
use App\Support\Attribution\ShoppingFeedBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemNgramsFeedsInvestigatorTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create([
            'name' => 'SEM Hub Co',
            'slug' => 'sem-hub-co',
            'plan' => 'pro',
            'status' => 'active',
            'timezone' => 'Asia/Riyadh',
            'language' => 'en',
        ]);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Oud Boutique',
            'currency' => 'KWD',
            'status' => 'active',
        ]);

        return [$agency, $owner, $client];
    }

    public function test_ngram_miner_unifies_arabic_variants_exposes_wasted_stems_and_exports_negatives(): void
    {
        [, , $client] = $this->workspace();

        $csv = "search_term,campaign_name,network,impressions,clicks,spend,conversions,revenue,stat_date\n"
            ."عطور مجانية عينات,Search_Oud,google_ads,800,45,١٨.٥٠٠,0,0,2026-11-10\n"
            ."عطور مجانيه للتجربة,Search_Oud,google_ads,620,35,١٤.٢٥٠,0,0,2026-11-11\n"
            ."عطر عود فاخر اصلي,Search_Oud,google_ads,900,60,25.000,6,320.000,2026-11-11\n";

        $saved = SearchTermNgramMiner::importCsv($client, $csv);
        $this->assertSame(3, $saved);

        $bleed = SearchTermNgramMiner::mineBleed($client, 30, 10.0);
        $this->assertNotEmpty($bleed);

        $stems = array_column($bleed, 'ngram');
        $this->assertContains('عطور مجانيه', $stems);
        $this->assertNotContains('عطر عود', $stems);

        $topBleed = collect($bleed)->firstWhere('ngram', 'عطور مجانيه');
        $this->assertNotNull($topBleed);
        $this->assertSame(2, $topBleed['term_count']);
        $this->assertEqualsWithDelta(32.75, $topBleed['spend'], 0.001);

        $negatives = SearchTermNgramMiner::exportNegativesTxt([$topBleed], 'phrase');
        $this->assertSame("\"عطور مجانيه\"\n", $negatives);
        $this->assertSame("[عطور مجانيه]\n", SearchTermNgramMiner::exportNegativesTxt([$topBleed], 'exact'));
    }

    public function test_shopping_feed_endpoint_gates_module_sets_noindex_filters_oos_and_labels_quadrants(): void
    {
        [$agency, , $client] = $this->workspace();

        ProfitAndAttributionManager::recordSkuSnapshot($client, [
            'sku_code' => 'OUD-HERO-1',
            'product_title' => 'دهن عود كمبودي',
            'stock_qty' => 18,
            'ad_spend' => 160.0,
            'gross_revenue' => 640.0,
            'orders_count' => 8,
            'repeat_ltv_90d' => 45.0,
            'period_date' => '2026-11-12',
        ]);

        ProfitAndAttributionManager::recordSkuSnapshot($client, [
            'sku_code' => 'OUD-OOS-2',
            'product_title' => 'بخور ملكي نفد',
            'stock_qty' => 0,
            'stock_status' => 'out_of_stock',
            'ad_spend' => 40.0,
            'gross_revenue' => 0.0,
            'orders_count' => 0,
            'period_date' => '2026-11-12',
        ]);

        $feed = ShoppingFeedBuilder::createOrUpdateFeed($client, 'google_merchant', false, [
            'base_url' => 'https://oud-boutique.kw',
            'brand_ar' => 'دار العود',
            'brand_en' => 'Oud House',
            'attribute_ar' => 'توصيل سريع',
        ]);

        // Module intelligence_hub is off by default -> 404
        $this->get('/feeds/'.$feed->feed_token.'.xml')->assertNotFound();

        config(['modules.preview_agencies' => [$agency->slug]]);

        $res = $this->get('/feeds/'.$feed->feed_token.'.xml')->assertOk();
        $this->assertSame('noindex, nofollow', $res->headers->get('X-Robots-Tag'));

        $xml = (string) $res->getContent();
        $this->assertStringContainsString('<g:id>OUD-HERO-1</g:id>', $xml);
        $this->assertStringContainsString('دار العود - دهن عود كمبودي | توصيل سريع', $xml);
        $this->assertStringContainsString('<g:custom_label_0>hero</g:custom_label_0>', $xml);
        $this->assertStringContainsString('<g:price>80.000 KWD</g:price>', $xml);
        $this->assertStringNotContainsString('OUD-OOS-2', $xml);
        $this->assertStringNotContainsStringIgnoringCase('AdsRanger', $xml);
        $this->assertStringNotContainsStringIgnoringCase('Reach', $xml);
    }

    public function test_performance_investigator_decomposes_cpa_roas_and_poas_shifts_deterministically(): void
    {
        $out = PerformanceInvestigator::investigate(
            [
                'impressions' => 100000,
                'clicks' => 2000,
                'spend' => 1000.0,
                'conversions' => 100.0,
                'revenue' => 4000.0,
                'cod_return_rate_pct' => 10.0,
            ],
            [
                'impressions' => 100000,
                'clicks' => 2000,
                'spend' => 1400.0,
                'conversions' => 80.0,
                'revenue' => 3200.0,
                'cod_return_rate_pct' => 28.0,
            ],
            [
                'cogs_pct' => 30.0,
                'shipping_fee' => 2.0,
                'return_shipping_fee' => 3.0,
                'cod_share_pct' => 60.0,
            ]
        );

        // Prior ROAS = 4.0, Current ROAS = 3200 / 1400 = 2.286
        $this->assertEqualsWithDelta(4.0, $out['prior']['roas'], 0.001);
        $this->assertEqualsWithDelta(2.286, $out['current']['roas'], 0.002);

        $roasSum = $out['roas_waterfall']['cpc_effect']
            + $out['roas_waterfall']['cvr_effect']
            + $out['roas_waterfall']['aov_effect'];
        $this->assertEqualsWithDelta($out['deltas']['roas_delta'], $roasSum, 0.005);

        $cpaSum = $out['cpa_waterfall']['cpc_effect'] + $out['cpa_waterfall']['cvr_effect'];
        $this->assertEqualsWithDelta($out['deltas']['cpa_delta'], $cpaSum, 0.005);

        $this->assertLessThan(0.0, $out['poas_waterfall']['cod_return_effect']);
        $this->assertNotSame('improved', $out['primary_root_cause']);
    }
}
