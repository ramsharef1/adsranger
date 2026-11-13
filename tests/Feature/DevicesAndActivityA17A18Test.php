<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Support\Devices;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** cl96: A-17 signed-in devices + new-device alert, A-18 owner's workspace activity log. */
class DevicesAndActivityA17A18Test extends TestCase
{
    use RefreshDatabase;

    private const WIN_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

    private const IPHONE_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';

    private function workspace(string $slug, string $role = 'owner'): array
    {
        $agency = Agency::create(['name' => 'Acme '.$slug, 'slug' => $slug, 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman']);
        $user = User::factory()->create();
        $agency->members()->attach($user->id, ['role' => $role]);

        return [$agency, $user];
    }

    private function addSession(User $user, string $id, string $ua, int $ago = 60): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '203.0.113.7', 'user_agent' => $ua, 'payload' => '', 'last_activity' => now()->subSeconds($ago)->timestamp]);
    }

    public function test_device_labels_never_show_the_raw_user_agent(): void
    {
        $this->assertSame('Chrome on Windows', Devices::label(self::WIN_CHROME));
        $this->assertSame('Safari on iPhone', Devices::label(self::IPHONE_SAFARI));
        $this->assertSame('Unknown device', Devices::label(null));
        $this->assertSame(Devices::fingerprint(self::WIN_CHROME), Devices::fingerprint(str_replace('131.0', '132.0', self::WIN_CHROME)));
    }

    public function test_devices_page_lists_sessions_and_signing_out_others_keeps_this_one(): void
    {
        [$agency, $user] = $this->workspace('acme-dev', 'analyst');
        $this->addSession($user, 'aaa', self::WIN_CHROME);
        $this->addSession($user, 'bbb', self::IPHONE_SAFARI, 3600);
        $other = User::factory()->create();
        $this->addSession($other, 'ccc', self::WIN_CHROME);

        $this->withSession(['locale' => 'en'])->actingAs($user)->get("/app/{$agency->slug}/devices")->assertOk()
            ->assertSee('Chrome on Windows')->assertSee('Safari on iPhone')->assertDontSee('Mozilla/5.0');

        $token = $user->remember_token;
        $this->assertSame(1, Devices::signOutOthers($user, 'aaa'));
        $this->assertSame(['aaa', 'ccc'], DB::table('sessions')->orderBy('id')->pluck('id')->all());
        $this->assertNotSame($token, $user->fresh()->remember_token);
        $this->assertFalse(Devices::signOut($user, 'aaa', 'aaa'));
        $this->assertFalse(Devices::signOut($user, 'ccc', 'aaa'), 'another user\'s session is never touched');
    }

    public function test_new_device_is_flagged_only_after_the_first_sign_in_and_only_once(): void
    {
        $user = User::factory()->create();
        $login = function (string $ua) use ($user) {
            $this->withHeader('User-Agent', $ua);
            request()->headers->set('User-Agent', $ua);
            event(new Login('web', $user, false));
        };

        $login(self::WIN_CHROME);
        $login(self::WIN_CHROME);
        $this->assertSame(0, AuditLog::where('action', 'auth.new_device')->count());

        $login(self::IPHONE_SAFARI);
        $login(self::IPHONE_SAFARI);
        $this->assertSame(1, AuditLog::where('action', 'auth.new_device')->count());
        $this->assertSame('Safari on iPhone', AuditLog::where('action', 'auth.new_device')->first()->meta['label']);
    }

    public function test_owner_reads_this_workspace_log_in_plain_words_and_others_cannot(): void
    {
        [$agency, $owner] = $this->workspace('acme-log');
        [$other] = $this->workspace('other-log');
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Jeddah Shop']);

        $this->actingAs($owner);
        Filament::setTenant($agency);
        AuditLog::record('client.created', $client);
        AuditLog::record('social.post_published', $client);
        Filament::setTenant($other);
        AuditLog::record('client.deleted');
        Filament::setTenant(null);

        $this->assertSame(2, AuditLog::where('agency_id', $agency->id)->count());

        $this->withSession(['locale' => 'en'])->get("/app/{$agency->slug}/activity")->assertOk()
            ->assertSee('Added a client')->assertSee('Jeddah Shop')->assertSee('Social: other change')
            ->assertDontSee('Deleted a client')->assertDontSee('client.created');
        $this->withSession(['locale' => 'ar'])->get("/app/{$agency->slug}/activity")->assertOk()->assertSee('إضافة عميل');

        // The new user can already be attached here (unique pivot), so set the role rather than attach twice.
        $admin = User::factory()->create();
        $agency->members()->syncWithoutDetaching([$admin->id => ['role' => 'admin']]);
        $agency->members()->updateExistingPivot($admin->id, ['role' => 'admin']);
        $this->actingAs($admin)->get("/app/{$agency->slug}/activity")->assertForbidden();
    }
}
