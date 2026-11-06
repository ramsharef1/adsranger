<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Support\LaunchReadinessAuditor;
use App\Support\NetworkDepthManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave7And8LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_audience_multi_network_sync(): void
    {
        $agency = Agency::create(['name' => 'Depth Agency', 'slug' => 'depth-agency', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Amman Gold', 'currency' => 'JOD', 'status' => 'active']);

        $sync = NetworkDepthManager::syncAudienceList(
            $client,
            'High LTV Purchasers Q4',
            'high_ltv',
            ['meta', 'google', 'tiktok', 'snapchat'],
            4250
        );

        $this->assertSame('synced', $sync->status);
        $this->assertSame(4250, $sync->records_count);
        $this->assertEquals(78.50, (float) $sync->match_rate);
        $this->assertEquals(['meta', 'google', 'tiktok', 'snapchat'], $sync->target_networks);
    }

    public function test_conversion_pixel_and_capi_health_evaluation(): void
    {
        $agency = Agency::create(['name' => 'Depth Agency', 'slug' => 'depth-agency', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Amman Gold', 'currency' => 'JOD', 'status' => 'active']);

        $healthy = NetworkDepthManager::evaluateConversionHealth($client, 'meta', 'Purchase', true, true, 8.4, 96.5);
        $this->assertSame('healthy', $healthy->status);

        $warning = NetworkDepthManager::evaluateConversionHealth($client, 'tiktok', 'Lead', true, false, 4.5, 80.0);
        $this->assertSame('warning', $warning->status);

        $critical = NetworkDepthManager::evaluateConversionHealth($client, 'snapchat', 'Purchase', false, false, 0.0, 0.0);
        $this->assertSame('critical', $critical->status);
    }

    public function test_wave8_production_readiness_and_tenant_isolation_audit(): void
    {
        $report = LaunchReadinessAuditor::audit();

        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['missing_agency_columns']);
        $this->assertSame(14, $report['tenant_tables_verified']);
        $this->assertGreaterThanOrEqual(18, $report['connectors_count']);
    }
}
