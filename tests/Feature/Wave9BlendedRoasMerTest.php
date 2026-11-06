<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Support\BlendedRoasCalculator;
use App\Support\LaunchReadinessAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave9BlendedRoasMerTest extends TestCase
{
    use RefreshDatabase;

    public function test_blended_roas_and_mer_aggregates_across_networks_with_tenant_isolation(): void
    {
        $agencyA = Agency::create(['name' => 'Alpha Agency', 'slug' => 'alpha-agency', 'plan' => 'agency', 'status' => 'active']);
        $clientA = Client::create(['agency_id' => $agencyA->id, 'name' => 'Alpha Retail', 'currency' => 'USD']);

        $agencyB = Agency::create(['name' => 'Beta Agency', 'slug' => 'beta-agency', 'plan' => 'agency', 'status' => 'active']);
        $clientB = Client::create(['agency_id' => $agencyB->id, 'name' => 'Beta Store', 'currency' => 'USD']);

        DailyMetric::create([
            'agency_id' => $agencyA->id,
            'client_id' => $clientA->id,
            'network' => 'meta',
            'date' => '2026-11-05',
            'spend' => 400,
            'revenue' => 1600,
            'conversions' => 16,
        ]);

        DailyMetric::create([
            'agency_id' => $agencyA->id,
            'client_id' => $clientA->id,
            'network' => 'google_ads',
            'date' => '2026-11-05',
            'spend' => 600,
            'revenue' => 2900,
            'conversions' => 29,
        ]);

        DailyMetric::create([
            'agency_id' => $agencyB->id,
            'client_id' => $clientB->id,
            'network' => 'tiktok',
            'date' => '2026-11-05',
            'spend' => 5000,
            'revenue' => 25000,
            'conversions' => 100,
        ]);

        $metric = BlendedRoasCalculator::computeForClientDate($clientA, '2026-11-05');

        $this->assertSame($agencyA->id, $metric->agency_id);
        $this->assertEquals(1000.0, $metric->total_spend);
        $this->assertEquals(4500.0, $metric->total_revenue);
        $this->assertEquals(45.0, $metric->total_conversions);
        $this->assertEquals(4.5, $metric->marketing_efficiency_ratio);
        $this->assertEquals(4.0, $metric->channel_breakdown['meta']['roas']);

        $audit = LaunchReadinessAuditor::audit();
        $this->assertTrue($audit['ready']);
    }
}