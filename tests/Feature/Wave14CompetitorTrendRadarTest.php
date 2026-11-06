<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Support\CreativeTrendRadar;
use App\Support\LaunchReadinessAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave14CompetitorTrendRadarTest extends TestCase
{
    use RefreshDatabase;

    public function test_competitor_ad_spy_and_creative_trend_radar_detects_winning_hooks_and_formats(): void
    {
        $agency = Agency::create(['name' => 'MENA Strategy Group', 'slug' => 'mena-strategy', 'plan' => 'pro', 'status' => 'active']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Fashion Co', 'currency' => 'SAR']);

        $ads = [
            ['format' => 'ugc_vertical', 'hook' => '3 أسباب تجعل هذه العباية الأكثر مبيعاً في الرياض', 'days_active' => 21],
            ['format' => 'ugc_vertical', 'hook' => 'خصم حصري 30% ينتهي الليلة', 'days_active' => 18],
            ['format' => 'carousel', 'hook' => 'تشكيلة الصيف الجديدة', 'days_active' => 4],
        ];

        $radar = CreativeTrendRadar::scanCompetitorAds($client, 'Rival Modest Wear', 'meta', $ads);

        $this->assertSame($agency->id, $radar->agency_id);
        $this->assertSame('ugc_vertical', $radar->dominant_format);
        $this->assertSame(3, $radar->active_ads_count);
        $this->assertSame(2, $radar->trend_signals['evergreen_winners']);
        $this->assertSame('high_retention_hook_detected', $radar->trend_signals['shift_alert']);

        $audit = LaunchReadinessAuditor::audit();
        $this->assertTrue($audit['ready']);
        $this->assertSame(20, $audit['tenant_tables_verified']);
    }
}