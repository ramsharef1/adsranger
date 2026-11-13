<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Connection;
use App\Models\SyncRun;
use App\Models\User;
use App\Support\LiveVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A-11: only a real (non-demo) sync that returned rows makes a network "verified". */
class LiveVerificationA11Test extends TestCase
{
    use RefreshDatabase;

    private function sync(Agency $agency, AdNetwork $net, string $env, string $userId, string $status, int $rows): void
    {
        $conn = Connection::create(['agency_id' => $agency->id, 'ad_network_id' => $net->id, 'status' => 'active', 'environment' => $env, 'external_user_id' => $userId]);
        $acct = AdAccount::create(['agency_id' => $agency->id, 'connection_id' => $conn->id, 'ad_network_id' => $net->id, 'external_id' => 'act_'.$userId.$net->id]);
        SyncRun::create(['agency_id' => $agency->id, 'ad_account_id' => $acct->id, 'from' => now()->subDay(), 'to' => now(), 'status' => $status, 'rows' => $rows, 'finished_at' => now()]);
    }

    public function test_demo_syncs_never_verify_a_network_and_real_ones_do(): void
    {
        $owner = User::factory()->create();
        $agency = Agency::create(['name' => 'A', 'slug' => 'a-lv', 'plan' => 'agency', 'status' => 'active', 'owner_id' => $owner->id]);
        $meta = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta', 'category' => 'social']);
        $tiktok = AdNetwork::firstOrCreate(['key' => 'tiktok'], ['name' => 'TikTok', 'category' => 'social']);

        $this->sync($agency, $meta, 'demo', 'demo', 'ok', 500);
        $this->sync($agency, $tiktok, 'live', 'u1', 'ok', 0);

        $board = collect(LiveVerification::board())->keyBy('key');
        $this->assertNotSame('verified', $board['meta']['level']);
        $this->assertSame('connected', $board['tiktok']['level']);

        $this->sync($agency, $meta, 'live', 'u2', 'ok', 42);
        $this->assertSame('verified', collect(LiveVerification::board())->keyBy('key')['meta']['level']);

        // #74: /admin requires two-step verification, so this admin has it set up.
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->actingAs($admin)->get('/admin/live-verification?lang=en')->assertOk()->assertSee('Live verification board');
    }
}
