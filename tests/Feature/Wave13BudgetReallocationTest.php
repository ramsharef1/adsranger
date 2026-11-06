<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Support\BudgetReallocationEngine;
use App\Support\LaunchReadinessAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave13BudgetReallocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_automated_budget_reallocation_shifts_spend_and_respects_monthly_caps(): void
    {
        $agency = Agency::create(['name' => 'Dubai Growth Partners', 'slug' => 'dubai-growth', 'plan' => 'agency', 'status' => 'active']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Ecom Gulf', 'currency' => 'AED']);

        $campaigns = [
            ['name' => 'Meta Prospecting Broad', 'cpa' => 35.0, 'daily_budget' => 500.0],
            ['name' => 'Google Brand Search', 'cpa' => 14.0, 'daily_budget' => 300.0],
        ];

        $log = BudgetReallocationEngine::evaluateAndReallocate($client, $campaigns, 20.0, 10000.0, 6000.0);

        $this->assertNotNull($log);
        $this->assertSame($agency->id, $log->agency_id);
        $this->assertSame('Meta Prospecting Broad', $log->source_campaign);
        $this->assertSame('Google Brand Search', $log->target_campaign);
        $this->assertEquals(100.0, $log->shifted_amount);
        $this->assertSame('applied', $log->status);

        $audit = LaunchReadinessAuditor::audit();
        $this->assertTrue($audit['ready']);
        $this->assertSame(20, $audit['tenant_tables_verified']);
    }
}