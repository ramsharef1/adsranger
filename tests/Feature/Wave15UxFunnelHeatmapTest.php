<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Support\LaunchReadinessAuditor;
use App\Support\UxHeatmapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave15UxFunnelHeatmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_behavioral_ux_session_heatmap_pinpoints_funnel_conversion_leaks(): void
    {
        $agency = Agency::create(['name' => 'Jeddah Performance CRO', 'slug' => 'jeddah-cro', 'plan' => 'agency', 'status' => 'active']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'KSA Auto Leasing', 'currency' => 'SAR']);

        $heatmap = UxHeatmapService::analyzeLandingFunnel(
            $client,
            'https://ksa-auto.example/ramadan-offer',
            1250,
            64.5,
            ['hero_cta' => 12.0, 'salary_certificate_upload' => 58.4, 'submit_button' => 9.5]
        );

        $this->assertSame($agency->id, $heatmap->agency_id);
        $this->assertSame('salary_certificate_upload', $heatmap->primary_leak_step);
        $this->assertEquals(58.4, $heatmap->drop_off_rate);
        $this->assertSame('simplify_checkout_field', $heatmap->friction_Zones['recommendation']);

        $audit = LaunchReadinessAuditor::audit();
        $this->assertTrue($audit['ready']);
        $this->assertSame(21, $audit['tenant_tables_verified']);
    }
}