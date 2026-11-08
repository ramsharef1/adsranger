<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\CreativeBrief;
use App\Support\CampaignBuilder;
use App\Support\PolicyPreCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave4StudioSlice2Test extends TestCase
{
    use RefreshDatabase;

    public function test_policy_pre_check_detects_prohibited_claims(): void
    {
        $clean = PolicyPreCheck::analyze('Discover our new professional consulting services in Amman.');
        $this->assertTrue($clean['passed']);
        $this->assertEmpty($clean['issues']);

        $flagged = PolicyPreCheck::analyze('Guaranteed results! Cure any ailment instantly with 100% success!');
        $this->assertFalse($flagged['passed']);
        $this->assertNotEmpty($flagged['issues']);
    }

    public function test_campaign_builder_publishes_valid_brief_across_networks(): void
    {
        $agency = Agency::create(['name' => 'Launch Agency', 'slug' => 'launch-agency', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Tech Hub', 'currency' => 'USD', 'status' => 'active']);

        $brief = CreativeBrief::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'title' => 'Spring Launch Campaign',
            'objective' => 'lead',
            'dialect' => 'en',
            'placement' => 'feed',
            'primary_text' => 'Join our upcoming webinar on digital marketing.',
            'headline' => 'Register Today',
            'cta' => 'Sign Up',
            'status' => 'approved',
        ]);

        $launch = CampaignBuilder::launch($brief, ['meta', 'google_ads', 'tiktok'], 150.0);
        $this->assertSame('simulated', $launch->status);
        $this->assertNull($launch->published_at);
        $this->assertCount(3, $launch->networks);

        $this->assertDatabaseHas('network_launches', [
            'client_id' => $client->id,
            'campaign_name' => 'Spring Launch Campaign',
            'status' => 'simulated',
        ]);
    }
}
