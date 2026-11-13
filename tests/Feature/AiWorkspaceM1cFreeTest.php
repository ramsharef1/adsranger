<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\AiConnection;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Connection;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\AiWorkspace\FreePlan;
use App\Support\PlanGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** cl106: AI Workspace M1 slice 3 — Free plan «الباقة المجانية» rules and lite view, plan caps, Pro-only parts (D-286). */
class AiWorkspaceM1cFreeTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $slug, string $plan = 'free', string $role = 'owner'): array
    {
        $agency = Agency::create(['name' => 'Acme '.$slug, 'slug' => $slug, 'plan' => $plan, 'status' => 'active', 'timezone' => 'Asia/Amman']);
        $user = User::factory()->create();
        $agency->members()->attach($user->id, ['role' => $role]);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Amman Cafe', 'currency' => 'USD']);
        config(['modules.preview_agencies' => array_merge(config('modules.preview_agencies', []), [$slug])]);

        return [$agency, $user, $client];
    }

    private function account(Agency $agency, Client $client, string $external = 'act_1', string $status = 'ACTIVE'): AdAccount
    {
        $network = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta', 'enabled' => true, 'status' => 'live', 'environment' => 'live']);
        $conn = Connection::create(['agency_id' => $agency->id, 'ad_network_id' => $network->id, 'status' => 'active', 'environment' => 'live']);

        return AdAccount::create(['agency_id' => $agency->id, 'connection_id' => $conn->id, 'ad_network_id' => $network->id, 'client_id' => $client->id,
            'external_id' => $external, 'name' => 'Cafe ads', 'currency' => 'USD', 'status' => $status, 'is_active' => true]);
    }

    public function test_free_plan_record_exists_with_one_of_everything(): void
    {
        $this->assertSame([1, 1, 1], array_values((array) \DB::table('plans')->where('key', 'free')->first(['seats', 'ad_accounts', 'clients'])));
    }

    public function test_daily_caps_follow_the_plan_and_full_ai_workspace_starts_at_pro(): void
    {
        $caps = [];
        foreach (['free', 'solo', 'starter', 'pro', 'agency'] as $plan) {
            [$a] = $this->workspace("cap-$plan", $plan);
            $caps[$plan] = FreePlan::dailyCap($a);
            $this->assertSame(in_array($plan, ['pro', 'agency'], true), PlanGate::allows($a, 'ai_workspace_full'), $plan);
        }
        $this->assertSame(['free' => 200, 'solo' => 1000, 'starter' => 2000, 'pro' => 5000, 'agency' => 10000], $caps);
        $this->assertNotContains('free', array_keys(PlanGate::byTier()), 'Free is not offered in plan lists yet');
    }

    public function test_one_free_workspace_per_ad_account_and_no_disabled_accounts(): void
    {
        [$a1, , $c1] = $this->workspace('free-one');
        [$a2, , $c2] = $this->workspace('free-two');
        [$paid, , $c3] = $this->workspace('paid-one', 'pro');
        $this->assertTrue($this->account($a1, $c1, 'act_9')->is_active);
        $this->assertFalse($this->account($a2, $c2, 'act_9')->fresh()->is_active, 'already in another Free workspace');
        $this->assertTrue($this->account($paid, $c3, 'act_9')->is_active, 'paid plans are not limited');
        $this->assertFalse($this->account($a2, $c2, 'act_x', 'DISABLED')->fresh()->is_active);
    }

    public function test_spend_fence_makes_ai_saves_view_only_after_two_months_over_the_cap(): void
    {
        [$agency, , $client] = $this->workspace('free-fence');
        $today = now('Asia/Amman')->startOfMonth()->addDays(20);
        foreach ([2, 1] as $back) {
            DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => $today->copy()->startOfMonth()->subMonthsNoOverflow($back)->addDays(3)->toDateString(),
                'network' => 'meta', 'currency' => 'USD', 'result_type' => 'purchase', 'spend' => 1200, 'revenue' => 0]);
        }
        $f = FreePlan::spendFence($agency, $today);
        $this->assertTrue($f['over']);
        $this->assertSame($today->copy()->startOfMonth()->addDays(14)->toDateString(), $f['view_only_from']);
        $this->assertTrue($f['view_only']);
        $this->assertFalse(FreePlan::spendFence($agency, $today->copy()->startOfMonth()->addDays(3))['view_only'], '14 days of notice first');
    }

    public function test_free_workspace_is_inactive_after_30_quiet_days_and_wakes_on_sign_in(): void
    {
        [$agency, $user] = $this->workspace('free-quiet');
        $agency->forceFill(['created_at' => now()->subDays(60)])->save();
        $this->assertTrue(FreePlan::inactive($agency->fresh()));
        AuditLog::create(['user_id' => $user->id, 'action' => 'auth.login', 'meta' => []]);
        $this->assertFalse(FreePlan::inactive($agency->fresh()));
    }

    public function test_free_app_is_the_lite_view_and_pro_parts_say_what_unlocks_them(): void
    {
        [$agency, $owner, $client] = $this->workspace('free-lite');
        $acc = $this->account($agency, $client);
        $camp = Campaign::create(['agency_id' => $agency->id, 'ad_account_id' => $acc->id, 'external_id' => 'c1', 'name' => 'Ramadan offers', 'status' => 'ACTIVE']);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'ad_account_id' => $acc->id, 'date' => now()->subDays(2)->toDateString(), 'network' => 'meta',
            'currency' => 'USD', 'result_type' => 'purchase', 'spend' => 120, 'revenue' => 400, 'purchases' => 4, 'results' => 4, 'level' => 'campaign', 'campaign_ref' => $camp->id]);

        $this->actingAs($owner)->get("/app/{$agency->slug}/insights")->assertRedirect("/app/{$agency->slug}/lite");
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/lite")->assertOk()
            ->assertSee('Ramadan offers')->assertSee('Automatic summary, no AI')->assertSee('Coming soon')->assertSee('Connect your AI to turn on the tools');
        $this->withSession(['locale' => 'ar'])->actingAs($owner)->get("/app/{$agency->slug}/lite")->assertOk()->assertSee('قريبًا');
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/ai-keys")->assertOk()->assertSee('comes with Pro')->assertDontSee('Test and save');

        AiConnection::create(['agency_id' => $agency->id, 'user_id' => $owner->id, 'oauth_client_id' => '9d0f8a3e-0000-4000-8000-000000000001', 'status' => 'verified',
            'allowed_client_ids' => [$client->id], 'ownership_version' => 'ai-own-v1', 'ownership_at' => now()]);
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/lite")->assertOk()->assertDontSee('Connect your AI to turn on the tools');
    }

    public function test_paid_workspaces_are_not_fenced(): void
    {
        [$agency, $owner] = $this->workspace('pro-open', 'pro');
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/ai-keys")->assertOk()->assertSee('Test and save');
        $this->assertNotSame(302, $this->actingAs($owner)->get("/app/{$agency->slug}/insights")->status());
    }
}
