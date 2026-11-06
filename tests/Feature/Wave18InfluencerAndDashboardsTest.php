<?php

namespace Tests\Feature;

use App\Filament\Pages\AgencyOpsHubPage;
use App\Filament\Pages\IntelligenceHubPage;
use App\Models\Agency;
use App\Models\Client;
use App\Support\InfluencerCampaignTracker;
use App\Support\LaunchReadinessAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave18InfluencerAndDashboardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_influencer_campaign_tracker_and_filament_hubs_are_configured(): void
    {
        $agency = Agency::create(['name' => 'Gulf Creators Agency', 'slug' => 'gulf-creators', 'plan' => 'agency', 'status' => 'active']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Beauty Store', 'currency' => 'SAR']);

        $campaign = InfluencerCampaignTracker::recordAndAttribute(
            $client,
            'Noor Al-Harbi',
            'snapchat',
            'NOOR25',
            1500.0,
            10.0,
            85,
            15000.0
        );

        $this->assertSame($agency->id, $campaign->agency_id);
        $this->assertSame('NOOR25', $campaign->promo_code);
        $this->assertEquals(3000.0, $campaign->total_payout);
        $this->assertEquals(5.0, $campaign->effective_roas);
        $this->assertSame('top_performer', $campaign->status);

        $this->assertSame('intelligence-hub', IntelligenceHubPage::getSlug());
        $this->assertSame('agency-ops', AgencyOpsHubPage::getSlug());

        $audit = LaunchReadinessAuditor::audit();
        $this->assertTrue($audit['ready']);
        $this->assertSame(21, $audit['tenant_tables_verified']);
    }
}