<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Support\AgencyOperationsHub;
use App\Support\LaunchReadinessAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave17AgencyOperationsPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_operations_pack_runway_margin_fx_panic_defuser_and_workload(): void
    {
        $agency = Agency::create(['name' => 'Amman Premier Digital', 'slug' => 'amman-premier', 'plan' => 'agency', 'status' => 'active']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Levant Retail Group', 'currency' => 'JOD']);

        $runway = AgencyOperationsHub::calculateBudgetRunway($client, 600.0, 300.0);
        $this->assertSame('critical_depletion', $runway->status);
        $this->assertEquals(2.0, $runway->primary_metric);

        $margin = AgencyOperationsHub::auditRetainerMargin($client, 3000.0, 25000.0, 20.0, 35.0);
        $this->assertSame('profitable', $margin->status);
        $this->assertEquals(76.67, $margin->primary_metric);

        $fx = AgencyOperationsHub::checkMenaFxVolatility($client, 'USD/EGP', 48.0, 50.5);
        $this->assertSame('fx_hedge_alert', $fx->status);

        $defuser = AgencyOperationsHub::generateClientPanicDefuser($client, 14.5, 'ar');
        $this->assertSame('brief_ready', $defuser->status);
        $this->assertStringContainsString('ملاحظة تنفيذية', $defuser->details['executive_summary']);

        $workload = AgencyOperationsHub::evaluateMediaBuyerWorkload($agency, 'Tariq Al-Mansoor', 9, 10);
        $this->assertSame('overloaded', $workload->status);
        $this->assertEquals(92.0, $workload->primary_metric);

        $audit = LaunchReadinessAuditor::audit();
        $this->assertTrue($audit['ready']);
        $this->assertSame(20, $audit['tenant_tables_verified']);
    }
}