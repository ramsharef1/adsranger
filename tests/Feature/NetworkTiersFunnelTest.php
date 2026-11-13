<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use App\Support\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NetworkTiersFunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['locale' => 'en']);
    }

    private function plansFor(string $network): array
    {
        return DB::table('ad_network_plan')->join('plans', 'plans.id', '=', 'plan_id')
            ->join('ad_networks', 'ad_networks.id', '=', 'ad_network_id')
            ->where('ad_networks.key', $network)->where('plans.key', '!=', 'free')->orderBy('plans.sort')->pluck('plans.key')->all();
    }

    public function test_network_tiers_follow_d32_and_d01(): void
    {
        // #177 Solo gets the same networks as Starter.
        $this->assertSame(['solo', 'starter', 'pro', 'agency'], $this->plansFor('tiktok'));
        $this->assertSame(['solo', 'starter', 'pro', 'agency'], $this->plansFor('snapchat'));
        // D-286: Free «الباقة المجانية» has Solo's networks (one ad account).
        $free = DB::table('ad_network_plan')->join('plans', 'plans.id', '=', 'plan_id')->join('ad_networks', 'ad_networks.id', '=', 'ad_network_id')
            ->where('plans.key', 'free')->pluck('ad_networks.key')->all();
        $this->assertContains('tiktok', $free);
        $this->assertNotContains('microsoft', $free);
        $this->assertSame(['pro', 'agency'], $this->plansFor('microsoft'));
        $this->assertSame(['pro', 'agency'], $this->plansFor('linkedin'));
        $this->assertSame(['pro', 'agency'], $this->plansFor('ga4'));
        foreach (['pinterest', 'reddit', 'taboola', 'outbrain', 'mgid', 'revcontent', 'apple_ads', 'amazon_ads', 'yahoo_dsp', 'google_merchant'] as $n) {
            $this->assertSame(['agency'], $this->plansFor($n), $n);
        }
    }

    public function test_full_funnel_is_locked_on_starter_and_open_on_pro(): void
    {
        $a = Agency::create(['name' => 'Funnel Co', 'slug' => Agency::uniqueSlug('funnel co'), 'plan' => 'starter', 'status' => 'active']);
        $owner = User::factory()->create();
        $a->members()->attach($owner->id, ['role' => 'owner']);
        DemoData::seed($a, 30);
        $client = Client::where('agency_id', $a->id)->orderBy('id')->firstOrFail();

        $this->actingAs($owner)->get("/app/{$a->slug}/clients/{$client->id}")->assertOk()
            ->assertSee('Every step from impressions to paid')
            ->assertDontSee('Bars use a log scale');

        $a->update(['plan' => 'pro']);
        $this->actingAs($owner)->get("/app/{$a->slug}/clients/{$client->id}")->assertOk()
            ->assertSee('Bars use a log scale')
            ->assertDontSee('Every step from impressions to paid');
    }
}
