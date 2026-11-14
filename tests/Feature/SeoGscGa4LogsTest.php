<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoTelemetryPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\SkuPerformanceSnapshot;
use App\Models\User;
use App\Support\Seo\Ga4FunnelReconciler;
use App\Support\Seo\GscSearchAnalyticsSync;
use App\Support\Seo\ServerAccessLogAnalyzer;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SeoGscGa4LogsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create([
            'name' => 'Telemetry Co',
            'slug' => 'telemetry-co',
            'plan' => 'pro',
            'status' => 'active',
            'timezone' => 'Asia/Riyadh',
            'language' => 'en',
        ]);
        $owner = User::factory()->create();
        $analyst = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);
        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Riyadh Roastery',
            'currency' => 'KWD',
            'status' => 'active',
        ]);

        return [$agency, $owner, $analyst, $client];
    }

    public function test_gsc_sync_contract_csv_striking_distance_and_decay_work_without_fake_success(): void
    {
        [, , , $client] = $this->workspace();

        Http::preventStrayRequests();
        $unconfigured = GscSearchAnalyticsSync::syncApi($client, 'https://roastery.sa', null, '2026-11-01', '2026-11-10');
        $this->assertFalse($unconfigured['ok']);
        $this->assertSame('unconfigured', $unconfigured['status']);
        $this->assertSame(0, $unconfigured['rows_saved']);
        $this->assertCount(0, Http::recorded());

        Http::fake([
            'searchconsole.googleapis.com/*' => Http::sequence()
                ->push(['error' => 'upstream down'], 500)
                ->push([
                    'rows' => [
                        [
                            'keys' => ['2026-11-12', 'قهوة مختصة الرياض', 'https://roastery.sa/ar/beans', 'sau', 'mobile'],
                            'clicks' => 14,
                            'impressions' => 420,
                            'ctr' => 0.0333,
                            'position' => 6.4,
                        ],
                    ],
                ], 200),
        ]);

        $failed = GscSearchAnalyticsSync::syncApi($client, 'https://roastery.sa', 'tok-1', '2026-11-01', '2026-11-12');
        $this->assertFalse($failed['ok']);
        $this->assertSame('failed', $failed['status']);
        $this->assertDatabaseCount('client_search_console_rows', 0);

        $synced = GscSearchAnalyticsSync::syncApi($client, 'https://roastery.sa', 'tok-1', '2026-11-01', '2026-11-12');
        $this->assertTrue($synced['ok']);
        $this->assertSame(1, $synced['rows_saved']);

        $csv = "query,page,clicks,impressions,ctr,position,date\n"
            ."بن اثيوبي,https://roastery.sa/ar/ethiopia,٢٠,٢٠٠,10%,3.1,2026-10-20\n"
            ."بن اثيوبي,https://roastery.sa/ar/ethiopia,٦,١٩٠,3.15%,6.8,2026-11-10\n";

        $imported = GscSearchAnalyticsSync::importCsv($client, 'https://roastery.sa', $csv);
        $this->assertSame(2, $imported);

        $striking = GscSearchAnalyticsSync::strikingDistance($client, 28, 10);
        $queries = array_column($striking, 'query');
        $this->assertContains('قهوة مختصة الرياض', $queries);

        $decay = GscSearchAnalyticsSync::queryDecay($client, 14, 5);
        $this->assertNotEmpty($decay);
        $this->assertSame('بن اثيوبي', $decay[0]['query']);
        $this->assertLessThanOrEqual(-25.0, $decay[0]['click_delta_pct']);
        $this->assertGreaterThanOrEqual(2.0, $decay[0]['position_delta']);
    }

    public function test_ga4_contract_and_three_way_reconciliation_respect_hide_spend_and_3_decimal_currency(): void
    {
        [$agency, , , $client] = $this->workspace();

        $unconfigured = Ga4FunnelReconciler::syncApi($client, '998877', '', '2026-11-01', '2026-11-10');
        $this->assertFalse($unconfigured['ok']);
        $this->assertSame('unconfigured', $unconfigured['status']);

        Http::fake([
            'analyticsdata.googleapis.com/*' => Http::response([
                'rows' => [
                    [
                        'dimensionValues' => [
                            ['value' => '20261110'],
                            ['value' => 'Paid Search'],
                            ['value' => '/ar/beans'],
                        ],
                        'metricValues' => [
                            ['value' => '310'],
                            ['value' => '210'],
                            ['value' => '55'],
                            ['value' => '28'],
                            ['value' => '420.500'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $synced = Ga4FunnelReconciler::syncApi($client, 'properties/998877', 'ga4-live-tok', '2026-11-01', '2026-11-12');
        $this->assertTrue($synced['ok']);
        $this->assertSame(1, $synced['rows_saved']);

        DailyMetric::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'network' => 'meta',
            'date' => '2026-11-10',
            'currency' => 'KWD',
            'spend' => 115.25,
            'impressions' => 12000,
            'clicks' => 400,
            'link_clicks' => 350,
            'purchases' => 36,
            'results' => 36,
            'result_type' => 'purchase',
            'revenue' => 540.000,
            'roas_eligible' => true,
        ]);

        SkuPerformanceSnapshot::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'sku_code' => 'BEAN-ETH-250',
            'product_title' => 'Ethiopian Beans 250g',
            'stock_qty' => 40,
            'ad_spend' => 115.25,
            'gross_revenue' => 360.000,
            'cogs_amount' => 120.000,
            'net_profit' => 124.750,
            'orders_count' => 24,
            'period_date' => '2026-11-10',
        ]);

        $rec = Ga4FunnelReconciler::reconcile($client, '2026-11-01', '2026-11-12');
        $this->assertSame('over_claimed', $rec['status']);
        $this->assertEqualsWithDelta(50.0, $rec['ad_vs_store_pct'], 0.1);
        $this->assertSame(115.25, $rec['ad_spend']);

        $client->update(['hide_spend' => true]);
        $hidden = Ga4FunnelReconciler::reconcile($client->fresh(), '2026-11-01', '2026-11-12');
        $this->assertNull($hidden['ad_spend']);
    }

    public function test_streamed_log_analyzer_parses_plain_and_gzip_logs_for_ai_bots_waste_and_orphans(): void
    {
        [, , , $client] = $this->workspace();

        $lines = implode("\n", [
            '66.249.66.1 - - [10/Nov/2026:10:00:01 +0000] "GET /ar/beans HTTP/1.1" 200 5412 "-" "Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)"',
            '20.171.207.1 - - [10/Nov/2026:10:01:02 +0000] "GET /ar/secret-landing HTTP/1.1" 200 4120 "-" "Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1"',
            '34.100.10.2 - - [10/Nov/2026:10:02:03 +0000] "GET /ar/old-promo HTTP/1.1" 404 512 "-" "Mozilla/5.0 (compatible; ClaudeBot/1.0; +claudebot@anthropic.com)"',
            '52.10.20.3 - - [10/Nov/2026:10:03:04 +0000] "GET /ar/beans?sort=price_asc&filter=dark HTTP/1.1" 200 6100 "-" "Mozilla/5.0 (compatible; PerplexityBot/1.0)"',
            '40.77.167.5 - - [10/Nov/2026:10:04:05 +0000] "GET /ar/Redirected HTTP/1.1" 301 0 "-" "Mozilla/5.0 (compatible; bingbot/2.0)"',
            '88.1.2.3 - - [10/Nov/2026:10:05:06 +0000] "GET /ar/beans HTTP/1.1" 200 5412 "-" "Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)"',
        ])."\n";

        $gzPath = tempnam(sys_get_temp_dir(), 'seo_log_').'.gz';
        $gz = gzopen($gzPath, 'wb9');
        gzwrite($gz, $lines);
        gzclose($gz);

        try {
            $analysis = ServerAccessLogAnalyzer::analyzeFile($client, $gzPath, 'november-access.log.gz', ['/ar/beans']);
        } finally {
            @unlink($gzPath);
        }

        $this->assertSame(6, $analysis->total_lines);
        $this->assertSame(1, $analysis->bot_hits['Googlebot']);
        $this->assertSame(1, $analysis->bot_hits['GPTBot']);
        $this->assertSame(1, $analysis->bot_hits['ClaudeBot']);
        $this->assertSame(1, $analysis->bot_hits['PerplexityBot']);
        $this->assertSame(1, $analysis->bot_hits['Bingbot']);
        $this->assertSame(1, $analysis->bot_hits['Human']);
        $this->assertSame(4, $analysis->status_breakdown['2xx']);
        $this->assertSame(1, $analysis->status_breakdown['3xx']);
        $this->assertSame(1, $analysis->status_breakdown['4xx']);

        $wastePaths = array_column($analysis->wasted_budget_paths, 'path');
        $this->assertContains('/ar/old-promo', $wastePaths);
        $this->assertContains('/ar/beans?sort=price_asc&filter=dark', $wastePaths);

        $orphanPaths = array_column($analysis->orphan_candidates, 'path');
        $this->assertContains('/ar/secret-landing', $orphanPaths);
        $this->assertNotContains('/ar/beans', $orphanPaths);
    }

    public function test_seo_telemetry_page_enforces_module_gate_roles_and_hide_spend(): void
    {
        [$agency, $owner, $analyst, $client] = $this->workspace();

        $this->actingAs($owner)->get("/app/{$agency->slug}/seo-telemetry")->assertForbidden();

        config(['modules.preview_agencies' => [$agency->slug]]);

        $this->withSession(['locale' => 'ar'])->actingAs($owner)->get("/app/{$agency->slug}/seo-telemetry")
            ->assertOk()
            ->assertSee('مؤشرات البحث وسجلات الخادم');

        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/seo-telemetry")
            ->assertOk()
            ->assertSee('Search & log telemetry');

        $this->actingAs($analyst);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        Livewire::test(SeoTelemetryPage::class)
            ->call('syncGscApi')
            ->assertForbidden();

        $client->update(['hide_spend' => true]);
        $html = $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/seo-telemetry?client={$client->id}")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('>Ad spend<', $html);
    }
}
