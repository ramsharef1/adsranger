<?php

namespace Tests\Feature;

use App\Filament\Pages\ProfitAttributionHubPage;
use App\Models\Agency;
use App\Models\CapiBridgeEvent;
use App\Models\Client;
use App\Models\CustomAudienceSync;
use App\Models\User;
use App\Support\Attribution\ProfitAndAttributionManager;
use App\Support\Attribution\SafeFormulaEvaluator;
use App\Support\CurrentClient;
use App\Support\Modules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class WaveB2PoasCapiSkuTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkspace(string $slug, string $plan = 'agency', string $status = 'active', string $source = 'admin'): array
    {
        $owner = User::factory()->create();
        $agency = Agency::create([
            'name' => 'Agency '.$slug,
            'slug' => $slug,
            'plan' => $plan,
            'status' => $status,
            'signup_source' => $source,
            'owner_id' => $owner->id,
            'language' => 'en',
        ]);
        DB::table('agency_user')->where('user_id', $owner->id)->delete();
        $agency->members()->attach($owner->id, ['role' => 'owner']);

        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Riyadh Store '.$slug,
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        return [$agency, $owner, $client];
    }

    public function test_module_is_off_by_default_and_gates_hub_and_public_collector_routes(): void
    {
        $this->assertFalse(Modules::enabled('intelligence_hub'));

        [$agency, $owner, $client] = $this->createWorkspace('gate-b2');
        $econ = ProfitAndAttributionManager::ensureUnitEconomics($client);
        $survey = ProfitAndAttributionManager::ensureSurvey($client);

        // Workspace page hidden / 403 while intelligence_hub is off
        $this->assertContains(
            $this->actingAs($owner)->get("/app/{$agency->slug}/profit-attribution-hub")->status(),
            [403, 404]
        );

        // Public collector & survey return 404 + X-Robots-Tag: noindex, nofollow when intelligence_hub is off (D-229 + GoLiveCl53)
        $this->get('/collect/'.$econ->collector_token)
            ->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->postJson('/collect/'.$econ->collector_token, ['event_name' => 'Purchase', 'value' => 250])
            ->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/survey/'.$survey->survey_token)
            ->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post('/survey/'.$survey->survey_token, ['channel_choice' => 'tiktok'])
            ->assertNotFound();

        // Super Admin keeps preview access even while globally off
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin)->get("/app/{$agency->slug}/profit-attribution-hub")->assertOk();
    }

    public function test_cod_adjusted_poas_ctwa_capi_bridge_and_collector_deduplication(): void
    {
        config(['modules.enabled.intelligence_hub' => true]);
        [$agency, , $client] = $this->createWorkspace('poas-b2');

        // #193 COD-Adjusted POAS & Break-Even ROAS
        $poas = ProfitAndAttributionManager::computeCodPoas($client, 20000.0, 2500.0, 100, [
            'cogs_pct' => 30.0,
            'shipping_fee' => 15.0,
            'return_shipping_fee' => 12.0,
            'cod_share_pct' => 50.0,
            'cod_return_rate_pct' => 20.0, // 10% total gross revenue lost to COD returns -> realized 18,000
            'bnpl_share_pct' => 30.0,
            'bnpl_fee_pct' => 6.0,        // 20,000 * 0.30 * 0.06 = 360
            'gateway_fee_pct' => 2.0,     // remaining 20% online card -> 20,000 * 0.20 * 0.02 = 80
        ]);

        // Realized = 18,000; COGS = 5,400; Outbound = 1,500; Return shipping = 10 * 12 = 120; BNPL = 360; Gateway = 80
        // Contribution margin = 18,000 - (5,400 + 1,500 + 120 + 360 + 80) = 10,540
        // Net profit after 2,500 spend = 8,040 -> POAS = 8,040 / 2,500 = 3.22
        $this->assertSame(18000.0, $poas['realized_revenue']);
        $this->assertSame(10540.0, $poas['contribution_margin']);
        $this->assertSame(8040.0, $poas['net_profit']);
        $this->assertSame(3.22, $poas['poas']);
        $this->assertSame(1.9, $poas['break_even_roas']);

        // #194 Click-to-WhatsApp (ctwa_clid) 72-hour free window & honest D-229 simulated state
        $ctwa = ProfitAndAttributionManager::captureCtwaEvent($client, [
            'ctwa_clid' => 'ctwa_abc123xyz',
            'event_name' => 'Lead',
            'occurred_at' => '2026-11-12 10:00:00',
            'phone' => '+966 50 123 4567',
            'value' => 450,
        ]);
        $this->assertSame('ctwa', $ctwa->channel);
        $this->assertSame('simulated', $ctwa->delivery_state);
        $this->assertNotSame('sent', $ctwa->delivery_state);
        $this->assertSame('2026-11-15 10:00:00', $ctwa->free_window_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(64, strlen((string) $ctwa->hashed_contact_sha256));

        // #195 First-party collector POST /collect/{token} deduplicating event_dedup_id across click IDs
        $econ = ProfitAndAttributionManager::ensureUnitEconomics($client);
        $payload = [
            'event_name' => 'Purchase',
            'event_dedup_id' => 'ord_998877',
            'fbclid' => 'fb_111',
            'gclid' => 'gc_222',
            'ttclid' => 'tt_333',
            'ScCid' => 'sc_444',
            'ctwa_clid' => 'ctwa_555',
            'value' => 890.50,
            'currency' => 'SAR',
            'delivery_mode' => 'queued_offline',
        ];

        $first = $this->postJson('/collect/'.$econ->collector_token, $payload)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertJson([
                'ok' => true,
                'duplicate' => false,
                'channel' => 'ctwa',
                'delivery_state' => 'queued_offline',
            ]);

        $second = $this->postJson('/collect/'.$econ->collector_token, $payload)
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'duplicate' => true,
                'event_id' => $first->json('event_id'),
            ]);

        $this->assertSame(1, CapiBridgeEvent::where('client_id', $client->id)->where('event_dedup_id', 'ord_998877')->count());
        $stored = CapiBridgeEvent::findOrFail($second->json('event_id'));
        $this->assertSame('sc_444', $stored->click_ids_json['ScCid']);
    }

    public function test_sku_matrix_oos_bleed_ltv_bundles_promo_bleed_and_rfm_segments(): void
    {
        [$agency, , $client] = $this->createWorkspace('sku-b2');

        // #196 Hero, Sidekick, Villain, Zombie SKUs + #197 OOS bleed + #198 LTV + #199 Bundles + #200 Promo bleed
        ProfitAndAttributionManager::recordSkuSnapshot($client, [
            'sku_code' => 'SKU-HERO',
            'product_title' => 'Oud Perfume 100ml',
            'stock_qty' => 80,
            'ad_spend' => 500,
            'gross_revenue' => 2400,
            'net_profit' => 950,
            'orders_count' => 24,
            'repeat_ltv_90d' => 620,
            'co_purchased_skus_json' => [['sku' => 'SKU-SIDE', 'count' => 14]],
            'promo_codes_json' => [['code' => 'VIP10', 'discount_pct' => 10, 'total_uses' => 20, 'unattributed_uses' => 2, 'discount_amount' => 120]],
        ]);

        ProfitAndAttributionManager::recordSkuSnapshot($client, [
            'sku_code' => 'SKU-SIDE',
            'product_title' => 'Travel Atomizer',
            'stock_qty' => 45,
            'ad_spend' => 40,
            'gross_revenue' => 420,
            'net_profit' => 190,
            'orders_count' => 18,
            'repeat_ltv_90d' => 310,
            'co_purchased_skus_json' => [['sku' => 'SKU-HERO', 'count' => 6]],
        ]);

        ProfitAndAttributionManager::recordSkuSnapshot($client, [
            'sku_code' => 'SKU-VILLAIN-OOS',
            'product_title' => 'Sold Out Gift Box',
            'stock_qty' => 0,
            'stock_status' => 'out_of_stock',
            'ad_spend' => 380,
            'gross_revenue' => 150,
            'net_profit' => -290,
            'orders_count' => 2,
            'repeat_ltv_90d' => 90,
            'promo_codes_json' => [['code' => 'LEAKED35', 'discount_pct' => 35, 'total_uses' => 30, 'unattributed_uses' => 26, 'discount_amount' => 1450]],
        ]);

        ProfitAndAttributionManager::recordSkuSnapshot($client, [
            'sku_code' => 'SKU-ZOMBIE',
            'product_title' => 'Old Sample',
            'stock_qty' => 12,
            'ad_spend' => 10,
            'gross_revenue' => 0,
            'net_profit' => -10,
            'orders_count' => 0,
        ]);

        $matrix = ProfitAndAttributionManager::skuMatrix($client);
        $this->assertSame(['hero' => 1, 'sidekick' => 1, 'villain' => 1, 'zombie' => 1], $matrix['counts']);

        $oos = ProfitAndAttributionManager::detectOutOfStockBleed($client);
        $this->assertSame(380.0, $oos['wasted_spend']);
        $this->assertSame('SKU-VILLAIN-OOS', $oos['flagged_skus'][0]['sku_code']);

        $ltv = ProfitAndAttributionManager::rankLtvGateways($client);
        $this->assertSame('SKU-HERO', $ltv[0]['sku_code']);
        $this->assertSame(620.0, $ltv[0]['repeat_ltv_90d']);

        $bundles = ProfitAndAttributionManager::mineBundleAffinities($client);
        $this->assertCount(1, $bundles);
        $this->assertSame('SKU-HERO + SKU-SIDE', $bundles[0]['pair']);
        $this->assertSame(20, $bundles[0]['co_purchases']);

        $promo = ProfitAndAttributionManager::detectPromoCodeBleed($client);
        $this->assertSame(1450.0, $promo['total_bleed_amount']);
        $this->assertCount(1, $promo['flagged_codes']);
        $this->assertSame('LEAKED35', $promo['flagged_codes'][0]['code']);

        // #201 Automated RFM Buyer Segmentation -> Audience Sync (#138) stays D-229 simulated
        $rfm = ProfitAndAttributionManager::buildRfmSegments($client, [
            ['recency_days' => 5, 'frequency' => 6, 'monetary' => 1400, 'cod_return_rate_pct' => 0],
            ['recency_days' => 75, 'frequency' => 3, 'monetary' => 500, 'cod_return_rate_pct' => 10],
            ['recency_days' => 14, 'frequency' => 4, 'monetary' => 300, 'cod_return_rate_pct' => 55],
        ], true);

        $this->assertSame(1, $rfm['vip_whales']['count']);
        $this->assertSame('simulated', $rfm['vip_whales']['sync_status']);
        $this->assertSame(1, $rfm['at_risk']['count']);
        $this->assertSame(1, $rfm['high_cod_return_exclusion']['count']);
        $this->assertSame(3, CustomAudienceSync::where('agency_id', $agency->id)->where('status', 'simulated')->count());
    }

    public function test_hdyhau_survey_mmm_mta_formula_evaluator_and_offline_pos_hash_matcher(): void
    {
        config(['modules.enabled.intelligence_hub' => true]);
        [$agency, , $client] = $this->createWorkspace('mta-b2');

        // #202 Post-purchase HDYHAU bilingual survey & blended share
        $survey = ProfitAndAttributionManager::ensureSurvey($client);
        $this->get('/survey/'.$survey->survey_token.'?lang=ar')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('كيف سمعت عنّا؟');

        $this->postJson('/survey/'.$survey->survey_token, ['channel_choice' => 'tiktok', 'order_value' => 300])->assertOk();
        $this->postJson('/survey/'.$survey->survey_token, ['channel_choice' => 'tiktok', 'order_value' => 250])->assertOk();
        $this->postJson('/survey/'.$survey->survey_token, ['channel_choice' => 'whatsapp', 'order_value' => 400])->assertOk();
        $this->postJson('/survey/'.$survey->survey_token, ['channel_choice' => 'meta_instagram', 'order_value' => 200])->assertOk();

        // tiktok survey = 50%, platform = 20% -> blended = 35%
        $blended = ProfitAndAttributionManager::blendedHdyhauAttribution($client, ['tiktok' => 20.0, 'meta_instagram' => 60.0, 'whatsapp' => 20.0]);
        $this->assertSame(4, $blended['total_responses']);
        $tiktokRow = collect($blended['channels'])->firstWhere('channel', 'tiktok');
        $this->assertSame(35.0, $tiktokRow['blended_share_pct']);

        // #203 Lightweight Automated MMM ridge regression
        $mmm = ProfitAndAttributionManager::estimateMmm([
            ['spends' => ['meta' => 1000, 'tiktok' => 400], 'revenue' => 5200],
            ['spends' => ['meta' => 2000, 'tiktok' => 500], 'revenue' => 7600],
            ['spends' => ['meta' => 3200, 'tiktok' => 1200], 'revenue' => 10400],
            ['spends' => ['meta' => 1500, 'tiktok' => 1800], 'revenue' => 8300],
            ['spends' => ['meta' => 4000, 'tiktok' => 900], 'revenue' => 11100],
        ]);
        $this->assertSame('deterministic_ridge', $mmm['status']);
        $this->assertArrayHasKey('meta', $mmm['channels']);
        $this->assertGreaterThan(0.0, $mmm['channels']['meta']['coefficient']);

        // #204 MTA 5-model credit comparison (U-Shaped 40/20/40)
        $mta = ProfitAndAttributionManager::compareMtaModels([
            ['touchpoints' => ['tiktok', 'google_ads', 'meta', 'whatsapp'], 'value' => 1000.0],
        ]);
        $this->assertSame(1000.0, $mta['first_touch']['tiktok']);
        $this->assertSame(1000.0, $mta['last_touch']['whatsapp']);
        $this->assertSame(250.0, $mta['linear']['google_ads']);
        $this->assertSame(400.0, $mta['position_based']['tiktok']);
        $this->assertSame(400.0, $mta['position_based']['whatsapp']);
        $this->assertSame(100.0, $mta['position_based']['google_ads']);
        $this->assertSame(100.0, $mta['position_based']['meta']);

        // #205 SafeFormulaEvaluator: arithmetic, divide-by-zero safety, and strict code-injection rejection
        $val = SafeFormulaEvaluator::evaluate('(revenue - spend) / spend * 100', ['revenue' => 5000, 'spend' => 1000]);
        $this->assertSame(400.0, $val);
        $this->assertSame(0.0, SafeFormulaEvaluator::evaluate('net_profit / spend', ['net_profit' => 900, 'spend' => 0]));

        foreach ([
            'system("id")',
            'eval(spend)',
            '$spend + 1',
            'spend; phpinfo()',
            'unknown_column * 2',
            '(spend + revenue',
        ] as $malicious) {
            $check = SafeFormulaEvaluator::validate($malicious);
            $this->assertFalse($check['valid'], "Formula should be rejected: {$malicious}");
        }

        $this->expectException(InvalidArgumentException::class);
        SafeFormulaEvaluator::evaluate('exec(spend)', ['spend' => 100]);
    }

    public function test_offline_pos_csv_matcher_hashes_pii_and_enforces_tenant_isolation_and_readonly_guard(): void
    {
        config(['modules.enabled.intelligence_hub' => true]);
        [$agencyA, $ownerA, $clientA] = $this->createWorkspace('pos-a', 'starter', 'trial', 'self_signup');
        [$agencyB, , $clientB] = $this->createWorkspace('pos-b');

        // Seed a WhatsApp touchpoint for Agency A and a separate one for Agency B
        ProfitAndAttributionManager::captureCtwaEvent($clientA, [
            'ctwa_clid' => 'ctwa_agency_a_match',
            'phone' => '+962 79 555 1122',
            'event_name' => 'Lead',
        ]);
        ProfitAndAttributionManager::captureCtwaEvent($clientB, [
            'ctwa_clid' => 'ctwa_agency_b_secret',
            'phone' => '+962 79 999 8877',
            'event_name' => 'Lead',
        ]);

        // #206 Match POS CSV on Agency A: matches clientA's contact, NEVER crosses into Agency B's touchpoints, stores zero raw PII
        $csv = "email,phone,value,currency,order_ref\n"
            .",+962795551122,320.00,JOD,POS-101\n"
            .",+962799998877,500.00,JOD,POS-102\n";

        $summary = ProfitAndAttributionManager::matchOfflinePosCsv($clientA, $csv, true);
        $this->assertSame(2, $summary['processed_rows']);
        $this->assertSame(1, $summary['matched_rows']);
        $this->assertSame(320.0, $summary['matched_revenue']);
        $this->assertSame('queued_offline', $summary['mode']);

        $posRow = CapiBridgeEvent::where('agency_id', $agencyA->id)->where('channel', 'pos_offline')->where('value', 320.0)->firstOrFail();
        $this->assertSame('ctwa_agency_a_match', $posRow->ctwa_clid);
        $this->assertSame('queued_offline', $posRow->delivery_state);
        $this->assertStringNotContainsString('795551122', json_encode($posRow->toArray()));

        // ReadOnlyGuard: expire Agency A's self-signup trial -> view actions work, write actions blocked
        $agencyA->forceFill(['trial_ends_at' => now()->subDays(3)])->save();

        $this->actingAs($ownerA);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agencyA);
        CurrentClient::remember($clientA->id, $agencyA);

        $lw = Livewire::test(ProfitAttributionHubPage::class)
            ->call('setMtaModel', 'linear')
            ->set('kpiFormula', 'revenue / conversions')
            ->call('previewFormula');

        $this->assertSame('linear', $lw->get('activeMtaModel'));
        $this->assertNotNull($lw->get('formulaPreviewValue'));

        // Write call (saveCustomKpi) is blocked by ReadOnlyGuard
        $lw->set('kpiName', 'Blocked ReadOnly KPI')
            ->call('saveCustomKpi');

        $this->assertDatabaseMissing('client_custom_kpis', [
            'agency_id' => $agencyA->id,
            'name' => 'Blocked ReadOnly KPI',
        ]);
    }
}
