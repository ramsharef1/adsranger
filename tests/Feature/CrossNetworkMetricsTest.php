<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Connection;
use App\Models\User;
use App\Support\MetricsImporter;
use App\Support\MetricsQuery;
use App\Sync\SyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** #116 & #128: Cross-network normalised metrics across API connectors and CSV/Sheet imports. */
class CrossNetworkMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_ingest_normalises_connector_rows_into_unified_funnel(): void
    {
        $agency = Agency::create(['name' => 'Norm Co', 'slug' => 'norm-co', 'plan' => 'pro', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Brand X', 'currency' => 'USD', 'status' => 'active']);
        $net = AdNetwork::firstOrCreate(['key' => 'tiktok'], ['name' => 'TikTok Ads', 'category' => 'social', 'stage' => 1, 'auth_type' => 'oauth', 'is_active' => true]);
        $conn = Connection::create(['agency_id' => $agency->id, 'ad_network_id' => $net->id, 'status' => 'active']);
        $acct = AdAccount::create([
            'agency_id' => $agency->id, 'client_id' => $client->id, 'ad_network_id' => $net->id,
            'connection_id' => $conn->id, 'external_id' => 'tt_1', 'name' => 'TT Account', 'currency' => 'USD', 'is_active' => true,
        ]);

        SyncService::ingest($account = $acct, [
            'campaigns' => [['external_id' => 'c1', 'name' => 'TT Lead Camp', 'status' => 'ACTIVE']],
            'rows' => [[
                'date' => '2026-10-15', 'campaign' => 'c1', 'spend' => 50,
                'impressions' => 2000, 'clicks' => 80, 'results' => 10, 'result_type' => 'lead',
            ]],
        ], '2026-10-15', '2026-10-15');

        $totals = MetricsQuery::totals($client, '2026-10-15', '2026-10-15');
        $this->assertSame(50.0, $totals['spend']);
        $this->assertSame(80.0, $totals['link_clicks']);
        $this->assertSame(10.0, $totals['leads']);
        $this->assertSame(10.0, $totals['main_results']);
        $this->assertSame('lead', $totals['main_type']);
        $this->assertSame(5.0, $totals['cpr']);
    }

    public function test_csv_importer_maps_extended_funnel_columns_and_normalises_results(): void
    {
        $agency = Agency::create(['name' => 'Import Co', 'slug' => 'import-co', 'plan' => 'pro', 'status' => 'active', 'currency' => 'USD']);
        $user = User::factory()->create();
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Shop Client', 'currency' => 'USD', 'status' => 'active']);

        $csv = "Date,Campaign Name,Spend,Impressions,Link Clicks,Landing Page Views,Add to Cart,Checkouts,Purchases,Revenue\n"
             . "2026-10-16,Snap Autumn,100,5000,200,150,40,20,8,400\n";

        MetricsImporter::import($agency, $user, 'snapchat', $csv, 'snap.csv', 'file', $client->id);

        $totals = MetricsQuery::totals($client, '2026-10-16', '2026-10-16');
        $this->assertSame(100.0, $totals['spend']);
        $this->assertSame(200.0, $totals['link_clicks']);
        $this->assertSame(150.0, $totals['landing_views']);
        $this->assertSame(40.0, $totals['add_to_cart']);
        $this->assertSame(20.0, $totals['checkouts']);
        $this->assertSame(8.0, $totals['purchases']);
        $this->assertSame('purchase', $totals['main_type']);
        $this->assertSame(4.0, $totals['roas']);
    }
}
