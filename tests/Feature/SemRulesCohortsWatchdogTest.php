<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientUnitEconomic;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\Attribution\CohortLtvAnalyzer;
use App\Support\Attribution\CrossChannelRuleEngine;
use App\Support\Attribution\LandingUrlWatchdog;
use App\Support\Attribution\ProfitAndAttributionManager;
use App\Support\LandingPages;
use App\Support\Seo\SafeFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SemRulesCohortsWatchdogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SafeFetcher::$resolver = fn (string $host) => str_ends_with($host, '.watch-test.com') ? '93.184.216.34' : LandingPages::publicIp($host);
    }

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    private function workspace(): array
    {
        $agency = Agency::create([
            'name' => 'Rule Watch Agency',
            'slug' => 'rule-watch-agency',
            'plan' => 'pro',
            'status' => 'active',
            'timezone' => 'Asia/Riyadh',
            'language' => 'en',
        ]);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Riyadh Apparel',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        return [$agency, $owner, $client];
    }

    public function test_rule_engine_clamps_budget_delta_to_20_pct_and_combines_ad_metrics_stock_and_cod_returns(): void
    {
        [, , $client] = $this->workspace();

        DailyMetric::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'network' => 'meta',
            'date' => now()->subDays(2)->toDateString(),
            'impressions' => 40000,
            'clicks' => 900,
            'spend' => 1200,
            'conversions' => 24,
            'revenue' => 2400,
        ]);

        ClientUnitEconomic::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'cogs_pct' => 40.0,
            'cod_return_rate_pct' => 32.5,
            'costs_entered_at' => now(),
        ]);

        ProfitAndAttributionManager::recordSkuSnapshot($client, [
            'sku_code' => 'ABAYA-BLK-01',
            'product_title' => 'عباية كريب سوداء',
            'stock_qty' => 2,
            'ad_spend' => 450,
            'gross_revenue' => 900,
            'orders_count' => 9,
            'period_date' => now()->subDay()->toDateString(),
        ]);

        // Attempt 55% budget delta -> clamped to <= 20% both on strategy and proposed action
        $strategy = CrossChannelRuleEngine::createStrategy($client, [
            'name' => 'Trim spend when COD return high & stock low',
            'conditions' => [
                ['field' => 'cod_return_rate_pct', 'op' => '>', 'value' => 25],
                ['field' => 'min_stock_qty', 'op' => '<=', 'value' => 5],
                ['field' => 'roas', 'op' => '<=', 'value' => 2.5],
            ],
            'actions' => [
                ['type' => 'adjust_budget_pct', 'delta_pct' => -55],
            ],
            'max_budget_delta_pct' => 55,
        ]);

        $this->assertSame('dry_run', $strategy->execution_mode);
        $this->assertEqualsWithDelta(20.0, $strategy->max_budget_delta_pct, 0.001);
        $this->assertEqualsWithDelta(-20.0, $strategy->actions[0]['delta_pct'], 0.001);

        $run = CrossChannelRuleEngine::evaluateStrategy($strategy);
        $this->assertTrue($run->matched);
        $this->assertSame('dry_run_logged', $run->status);
        $this->assertEqualsWithDelta(-20.0, $run->proposed_actions[0]['delta_pct'], 0.001);

        $strategy->update(['execution_mode' => 'approval_queue']);
        $queuedRun = CrossChannelRuleEngine::evaluateStrategy($strategy->fresh());
        $this->assertSame('queued_for_approval', $queuedRun->status);
    }

    public function test_cohort_ltv_analyzer_imports_curves_and_interpolates_payback_days(): void
    {
        [, , $client] = $this->workspace();

        $csv = "cohort_month,acquisition_key,customers_count,cac_amount,d30_ltv,d60_ltv,d90_ltv,d180_ltv\n"
            ."2026-08,meta_prospecting,١٢٠,90,60,120,165,210\n"
            ."2026-09,tiktok_broad,85,150,40,65,85,110\n";

        $saved = CohortLtvAnalyzer::importCsv($client, $csv);
        $this->assertSame(2, $saved);

        $summary = CohortLtvAnalyzer::summarize($client);
        $this->assertCount(2, $summary);

        $aug = collect($summary)->firstWhere('cohort_month', '2026-08');
        $this->assertNotNull($aug);
        // CAC 90 sits halfway between D30=60 and D60=120 -> 45 days
        $this->assertSame(45, $aug['payback_days']);
        $this->assertEqualsWithDelta(1.83, $aug['ltv_to_cac_d90'], 0.02);

        $sep = collect($summary)->firstWhere('cohort_month', '2026-09');
        $this->assertNull($sep['payback_days']);
    }

    public function test_landing_url_watchdog_uses_safe_fetcher_detects_redirects_noindex_missing_pixels_and_broken_whatsapp(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        config(['modules.preview_agencies' => [$agency->slug]]);

        Http::fake([
            'https://promo.watch-test.com/start' => Http::response('', 302, [
                'Location' => 'https://promo.watch-test.com/final',
            ]),
            'https://promo.watch-test.com/final' => Http::response(
                '<!doctype html><html><head>'
                .'<meta name="robots" content="noindex, follow">'
                ."<script>fbq('init', '12345');</script>"
                .'</head><body>'
                .'<a href="https://wa.me/0000">Chat</a>'
                .'</body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
        ]);

        $watched = LandingUrlWatchdog::watchUrl($client, 'https://promo.watch-test.com/start', ['meta_pixel', 'google_tag']);
        $check = LandingUrlWatchdog::check($watched);

        $this->assertSame(200, $check->http_status);
        $this->assertSame(1, $check->redirect_hops);
        $this->assertTrue($check->has_noindex);
        $this->assertSame(['google_tag'], $check->missing_pixels);
        $this->assertTrue($check->broken_whatsapp_link);

        // SSRF guard via SafeFetcher refuses loopback URLs
        $ssrfWatched = LandingUrlWatchdog::watchUrl($client, 'http://127.0.0.1/internal', ['meta_pixel']);
        $ssrfCheck = LandingUrlWatchdog::check($ssrfWatched);
        $this->assertSame('blocked', $ssrfCheck->error_code);
        $this->assertNull($ssrfCheck->http_status);

        $this->actingAs($owner)
            ->withSession(['locale' => 'en'])
            ->get('/app/'.$agency->slug.'/profit-attribution-hub')
            ->assertOk()
            ->assertSee('Cross-channel rule engine')
            ->assertSee('Ad landing URL & pixel watchdog');
    }
}
