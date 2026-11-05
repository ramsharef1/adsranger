<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CampaignAutomation;
use App\Models\Client;
use App\Models\ClonedCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave4StudioSlice3Test extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_automation_rule_creation(): void
    {
        $agency = Agency::create(['name' => 'Agency Pro', 'slug' => 'agency-pro', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Client X', 'currency' => 'USD', 'status' => 'active']);

        CampaignAutomation::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'name' => 'Scale on CPA Drop',
            'trigger_type' => 'cpa_spike',
            'trigger_conditions' => ['threshold' => 15.0],
            'action_type' => 'scale_budget',
            'action_parameters' => ['multiplier' => 1.2],
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('campaign_automations', [
            'name' => 'Scale on CPA Drop',
            'trigger_type' => 'cpa_spike',
        ]);
    }

    public function test_cloned_campaign_multi_network_tracking(): void
    {
        $agency = Agency::create(['name' => 'Agency Pro', 'slug' => 'agency-pro', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Client X', 'currency' => 'USD', 'status' => 'active']);

        $cloned = ClonedCampaign::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'source_campaign_ref' => 'camp_123',
            'target_networks' => ['meta', 'tiktok', 'snapchat'],
            'budget_multiplier' => 1.5,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('cloned_campaigns', [
            'source_campaign_ref' => 'camp_123',
            'status' => 'pending',
        ]);
        $this->assertEquals(['meta', 'tiktok', 'snapchat'], $cloned->target_networks);
    }
}
