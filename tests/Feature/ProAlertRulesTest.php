<?php

namespace Tests\Feature;

use App\Alerts\AlertEngine;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Connection;
use App\Models\DailyMetric;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** #23 Alerts engine: 7 Starter rules + 11 Pro rules = 18 rules total. */
class ProAlertRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_has_7_unlocked_and_11_pro_locked_rules(): void
    {
        $starter = Agency::create(['name' => 'Starter Co', 'slug' => 'starter-co', 'plan' => 'starter', 'status' => 'active']);
        $rules = AlertEngine::rules($starter);

        $this->assertCount(20, $rules); // + upload_stale (PS-08, D-296) and fx_drift (FS-17, D-304) on every plan
        $unlocked = collect($rules)->where('locked', false)->keys()->all();
        $locked = collect($rules)->where('locked', true)->keys()->all();

        $this->assertCount(9, $unlocked);
        $this->assertContains('fx_drift', $unlocked);
        $this->assertContains('upload_stale', $unlocked);
        $this->assertCount(11, $locked);
        $this->assertContains('ctr_drop', $locked);
        $this->assertContains('spend_spike', $locked);
        $this->assertContains('zero_impressions', $locked);
    }

    public function test_pro_evaluates_new_pro_alert_rules(): void
    {
        $pro = Agency::create(['name' => 'Pro Co', 'slug' => 'pro-co', 'plan' => 'pro', 'status' => 'active', 'language' => 'en']);
        $owner = User::factory()->create();
        DB::table('agency_user')->where('user_id', $owner->id)->delete();
        $pro->members()->attach($owner->id, ['role' => 'owner']);

        $client = Client::create(['agency_id' => $pro->id, 'name' => 'Test Brand', 'currency' => 'USD', 'status' => 'active']);
        $net = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta Ads', 'category' => 'social', 'stage' => 1, 'auth_type' => 'oauth', 'is_active' => true]);
        $conn = Connection::create([
            'agency_id' => $pro->id, 'ad_network_id' => $net->id, 'status' => 'active',
        ]);
        $acct = AdAccount::create([
            'agency_id' => $pro->id, 'client_id' => $client->id, 'ad_network_id' => $net->id, 'connection_id' => $conn->id,
            'external_id' => 'act_1', 'name' => 'Meta 1', 'currency' => 'USD', 'is_active' => true,
        ]);
        $camp = Campaign::create([
            'agency_id' => $pro->id, 'ad_account_id' => $acct->id, 'external_id' => 'c_1',
            'name' => 'Summer Promo', 'status' => 'ACTIVE',
        ]);

        $day = Carbon::parse('2026-10-20');

        // Previous week (days -13..-7): high CTR, normal daily spend ($20/day)
        for ($i = 7; $i <= 13; $i++) {
            DailyMetric::create([
                'agency_id' => $pro->id, 'client_id' => $client->id, 'ad_account_id' => $acct->id,
                'campaign_ref' => $camp->id, 'date' => $day->copy()->subDays($i)->toDateString(),
                'network' => 'meta', 'currency' => 'USD', 'spend' => 20, 'impressions' => 1000, 'reach' => 800,
                'clicks' => 50, 'link_clicks' => 50, 'results' => 5, 'result_type' => 'leads',
            ]);
        }

        // Current week (days -6..-1): lower CTR, high frequency, active campaign had impressions
        for ($i = 1; $i <= 6; $i++) {
            DailyMetric::create([
                'agency_id' => $pro->id, 'client_id' => $client->id, 'ad_account_id' => $acct->id,
                'campaign_ref' => $camp->id, 'date' => $day->copy()->subDays($i)->toDateString(),
                'network' => 'meta', 'currency' => 'USD', 'spend' => 20, 'impressions' => 1000, 'reach' => 150,
                'clicks' => 10, 'link_clicks' => 10, 'results' => 2, 'result_type' => 'leads',
            ]);
        }

        // Yesterday (day 0): spend spike ($120 vs $20 avg) on a second campaign while Summer Promo had 0 impressions
        $camp2 = Campaign::create([
            'agency_id' => $pro->id, 'ad_account_id' => $acct->id, 'external_id' => 'c_2',
            'name' => 'Spike Campaign', 'status' => 'ACTIVE',
        ]);
        DailyMetric::create([
            'agency_id' => $pro->id, 'client_id' => $client->id, 'ad_account_id' => $acct->id,
            'campaign_ref' => $camp2->id, 'date' => $day->toDateString(),
            'network' => 'meta', 'currency' => 'USD', 'spend' => 120, 'impressions' => 2000, 'reach' => 200,
            'clicks' => 10, 'link_clicks' => 10, 'results' => 3, 'result_type' => 'leads',
        ]);

        AlertEngine::run($pro, $day->toDateString());

        $this->assertDatabaseHas('alerts', ['agency_id' => $pro->id, 'rule_key' => 'ctr_drop']);
        $this->assertDatabaseHas('alerts', ['agency_id' => $pro->id, 'rule_key' => 'spend_spike']);
        $this->assertDatabaseHas('alerts', ['agency_id' => $pro->id, 'rule_key' => 'high_frequency']);
        $this->assertDatabaseHas('alerts', ['agency_id' => $pro->id, 'rule_key' => 'zero_impressions', 'entity_id' => $camp->id]);
    }
}
