<?php

namespace Tests\Feature;

use App\Filament\Resources\Alerts\AlertResource;
use App\Jobs\RunAlerts;
use App\Mail\AlertAssignedMail;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\CriticalPush;
use App\Support\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** #24 finish (snooze wake-up, alert page, history, assignee told) + #175 Critical push (D-104, D-141, spec A). */
class CriticalPushTest extends TestCase
{
    use RefreshDatabase;

    private string $vapid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vapid = sys_get_temp_dir().'/adsr-vapid-'.uniqid().'.json';
        config(['push.vapid_path' => $this->vapid, 'push.enabled' => true]);
        $this->withSession(['locale' => 'en']);
    }

    protected function tearDown(): void
    {
        @unlink($this->vapid);
        parent::tearDown();
    }

    private function agency(string $plan = 'pro'): Agency
    {
        return Agency::create(['name' => "Acme $plan", 'slug' => Agency::uniqueSlug("acme push $plan"), 'plan' => $plan, 'status' => 'active', 'timezone' => 'Asia/Amman', 'language' => 'en']);
    }

    /** A user with one subscription whose keys we hold, so the test can decrypt what was sent. */
    private function subscribed(Agency $a, string $role = 'owner'): array
    {
        $u = User::factory()->create(['push_weekend_off' => false]);
        $a->members()->attach($u->id, ['role' => $role]);
        $ua = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $auth = random_bytes(16);
        $endpoint = 'https://push.example.test/send/'.uniqid();
        PushSubscription::create(['user_id' => $u->id, 'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint),
            'p256dh' => WebPush::b64(WebPush::rawPublic($ua)), 'auth' => WebPush::b64($auth)]);

        return [$u, $ua, $auth];
    }

    private function alert(Agency $a, string $severity = 'critical', ?Client $c = null, string $fp = 'x'): Alert
    {
        return Alert::create(['agency_id' => $a->id, 'client_id' => $c?->id, 'rule_key' => 'tracking_break', 'severity' => $severity,
            'title' => 'Tracking break', 'entity_name' => 'Campaign A', 'status' => 'open', 'fingerprint' => $fp.uniqid(), 'last_seen_at' => now()]);
    }

    /** RFC 8291 decryption from the browser's side. */
    private function decrypt(string $body, $uaKey, string $auth): string
    {
        $salt = substr($body, 0, 16);
        $idlen = ord($body[20]);
        $asPublic = substr($body, 21, $idlen);
        $cipher = substr($body, 21 + $idlen);
        $ecdh = openssl_pkey_derive(WebPush::publicKeyFromRaw($asPublic), $uaKey, 32);
        $uaPublic = WebPush::rawPublic($uaKey);
        $prkKey = hash_hmac('sha256', $ecdh, $auth, true);
        $ikm = hash_hmac('sha256', "WebPush: info\x00".$uaPublic.$asPublic."\x01", $prkKey, true);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);
        $plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));
        $this->assertNotFalse($plain, 'payload decrypts');

        return rtrim($plain, "\x02");
    }

    public function test_payload_encryption_and_vapid_signature_follow_the_rfcs(): void
    {
        $ua = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $auth = random_bytes(16);
        $body = WebPush::encrypt('{"title":"حرج · Acme"}', WebPush::rawPublic($ua), $auth);
        $this->assertSame('{"title":"حرج · Acme"}', $this->decrypt($body, $ua, $auth));
        $this->assertSame(4096, unpack('N', substr($body, 16, 4))[1]);

        [$h, $c, $s] = explode('.', WebPush::jwt('https://push.example.test'));
        $raw = WebPush::unb64($s);
        $this->assertSame(64, strlen($raw));
        $int = function ($x) { $x = ltrim($x, "\x00"); $x = $x === "" ? "\x00" : $x; return ord($x[0]) & 0x80 ? "\x00".$x : $x; };
        $r = $int(substr($raw, 0, 32));
        $sv = $int(substr($raw, 32));
        $der = "\x30".chr(4 + strlen($r) + strlen($sv))."\x02".chr(strlen($r)).$r."\x02".chr(strlen($sv)).$sv;
        $this->assertSame(1, openssl_verify("$h.$c", $der, WebPush::publicKeyFromRaw(WebPush::unb64(WebPush::publicKey())), OPENSSL_ALGO_SHA256));
        $this->assertSame('https://push.example.test', json_decode(WebPush::unb64($c), true)['aud']);
        $this->assertFileExists($this->vapid);
    }

    public function test_critical_alerts_push_on_pro_and_warning_or_starter_never_do(): void
    {
        Http::fake(['push.example.test/*' => Http::response('', 201)]);
        $a = $this->agency('pro');
        [$u, $ua, $auth] = $this->subscribed($a);
        $noon = Carbon::parse('2026-11-08 12:00', 'Asia/Amman'); // a Sunday, outside quiet hours

        $this->assertSame(1, CriticalPush::dispatch($a, [$this->alert($a)], $noon));
        Http::assertSentCount(1);
        Http::assertSent(function ($req) use ($ua, $auth) {
            $p = json_decode($this->decrypt($req->body(), $ua, $auth), true);

            return str_starts_with($req->header('Authorization')[0], 'vapid t=') && $req->header('Content-Encoding')[0] === 'aes128gcm'
                && $p['title'] === 'Critical · Acme pro' && str_contains($p['body'], 'Tracking break') && str_contains($p['url'], '/alerts/');
        });

        $this->assertSame(0, CriticalPush::dispatch($a, [$this->alert($a, 'warning')], $noon));
        $starter = $this->agency('starter');
        $this->subscribed($starter);
        $this->assertSame(0, CriticalPush::dispatch($starter, [$this->alert($starter)], $noon));
        Http::assertSentCount(1);
    }

    public function test_more_than_three_at_once_send_one_grouped_push(): void
    {
        Http::fake(['push.example.test/*' => Http::response('', 201)]);
        $a = $this->agency('agency');
        [$u, $ua, $auth] = $this->subscribed($a);
        $c = Client::create(['agency_id' => $a->id, 'name' => 'Clinic One', 'currency' => 'USD', 'status' => 'active']);
        $alerts = collect(range(1, 4))->map(fn ($i) => $this->alert($a, 'critical', $c, "g$i"));
        $this->assertSame(1, CriticalPush::dispatch($a, $alerts, Carbon::parse('2026-11-08 12:00', 'Asia/Amman')));
        Http::assertSent(fn ($req) => json_decode($this->decrypt($req->body(), $ua, $auth), true)['body'] === '4 critical alerts for Clinic One');
        $this->assertSame(4, Alert::whereNotNull('pushed_at')->count());
    }

    public function test_quiet_hours_and_weekend_hold_then_send_one_summary(): void
    {
        Http::fake(['push.example.test/*' => Http::response('', 201)]);
        $a = $this->agency('pro');
        [$u, $ua, $auth] = $this->subscribed($a);
        $night = Carbon::parse('2026-11-08 23:30', 'Asia/Amman');
        $this->assertTrue(CriticalPush::isQuiet($u, $a, $night));
        $this->assertSame(0, CriticalPush::dispatch($a, [$this->alert($a, 'critical', null, 'q1'), $this->alert($a, 'critical', null, 'q2')], $night));
        $this->assertSame(2, DB::table('push_held')->count());
        Http::assertNothingSent();

        $this->assertSame(0, CriticalPush::flushHeld(Carbon::parse('2026-11-09 06:45', 'Asia/Amman')));
        $this->assertSame(1, CriticalPush::flushHeld(Carbon::parse('2026-11-09 07:00', 'Asia/Amman')));
        Http::assertSent(fn ($req) => json_decode($this->decrypt($req->body(), $ua, $auth), true)['body'] === '2 critical alerts during your quiet hours');
        $this->assertSame(0, DB::table('push_held')->count());

        // Workspace weekend (Jordan: Fri + Sat) is quiet all day when the user keeps "weekend off".
        $u->forceFill(['push_weekend_off' => true])->save();
        $this->assertTrue(CriticalPush::isQuiet($u, $a, Carbon::parse('2026-11-06 12:00', 'Asia/Amman')));
        $a->forceFill(['country' => 'AE'])->save();
        $this->assertFalse(CriticalPush::isQuiet($u, $a, Carbon::parse('2026-11-06 12:00', 'Asia/Amman')));
    }

    public function test_dropped_subscriptions_are_removed(): void
    {
        Http::fake(['push.example.test/*' => Http::response('', 410)]);
        $a = $this->agency('pro');
        $this->subscribed($a);
        $this->assertSame(0, CriticalPush::dispatch($a, [$this->alert($a)], Carbon::parse('2026-11-08 12:00', 'Asia/Amman')));
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_subscribe_route_is_pro_only(): void
    {
        $pro = $this->agency('pro');
        $u = User::factory()->create();
        $pro->members()->attach($u->id, ['role' => 'analyst']);
        $body = ['endpoint' => 'https://push.example.test/send/abc', 'keys' => ['p256dh' => str_repeat('A', 87), 'auth' => str_repeat('B', 22)]];
        $this->actingAs($u)->postJson("/app/{$pro->slug}/push/subscribe", $body)->assertOk();
        $this->assertSame(1, $u->pushSubscriptions()->count());
        $this->actingAs($u)->postJson("/app/{$pro->slug}/push/unsubscribe", ['endpoint' => $body['endpoint']])->assertOk();
        $this->assertSame(0, $u->pushSubscriptions()->count());

        $starter = $this->agency('starter');
        $starter->members()->attach($u->id, ['role' => 'owner']);
        $this->actingAs($u)->postJson("/app/{$starter->slug}/push/subscribe", $body)->assertForbidden();
    }

    public function test_snoozed_alerts_wake_up_when_the_snooze_ends(): void
    {
        $a = $this->agency('pro');
        $today = now('Asia/Amman')->toDateString();
        $a->forceFill(['alerts_run_on' => $today, 'digest_sent_on' => $today])->save(); // only the snooze wake-up runs
        $al = $this->alert($a);
        $al->update(['status' => 'snoozed', 'snoozed_until' => now()->subMinute()]);
        $later = $this->alert($a, 'critical', null, 'later');
        $later->update(['status' => 'snoozed', 'snoozed_until' => now()->addDay()]);
        (new RunAlerts)->handle();
        $this->assertSame('open', $al->fresh()->status);
        $this->assertSame('snoozed', $later->fresh()->status);
    }

    public function test_alert_page_shows_the_alert_and_my_notifications_shows_phone_alerts(): void
    {
        $a = $this->agency('pro');
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'owner']);
        $al = $this->alert($a);
        $this->actingAs($u)->get("/app/{$a->slug}/alerts/{$al->id}")->assertOk()
            ->assertSee('Campaign A')->assertSee('data-push-offer', false)->assertSee('History');
        $this->actingAs($u)->get("/app/{$a->slug}/notifications")->assertOk()
            ->assertSee('data-phone-alerts', false)->assertSee('Quiet hours from')->assertSee('/push-sw.js', false);
        $this->actingAs($u)->withSession(['locale' => 'ar'])->get("/app/{$a->slug}/notifications")->assertOk()->assertSee('ساعات الهدوء من');

        $starter = $this->agency('starter');
        $starter->members()->attach($u->id, ['role' => 'owner']);
        $this->actingAs($u)->withSession(['locale' => 'en'])->get("/app/{$starter->slug}/notifications")->assertOk()->assertSee('Instant phone alerts are available on Pro.');
    }

    public function test_a_restricted_member_cannot_open_another_clients_alert(): void
    {
        $a = $this->agency('pro');
        $mine = Client::create(['agency_id' => $a->id, 'name' => 'Mine', 'currency' => 'USD', 'status' => 'active']);
        $other = Client::create(['agency_id' => $a->id, 'name' => 'Other', 'currency' => 'USD', 'status' => 'active']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'manager']);
        $mine->users()->attach($u->id);
        $x = User::factory()->create();
        $a->members()->attach($x->id, ['role' => 'manager']);
        $other->users()->attach($x->id);
        $al = $this->alert($a, 'critical', $other);
        $this->actingAs($u)->get("/app/{$a->slug}/alerts/{$al->id}")->assertNotFound();
    }

    public function test_assigning_tells_the_assignee_only(): void
    {
        Mail::fake();
        $a = $this->agency('pro');
        $owner = User::factory()->create();
        $a->members()->attach($owner->id, ['role' => 'owner']);
        $mate = User::factory()->create(['email_alerts' => true]);
        $a->members()->attach($mate->id, ['role' => 'manager']);
        $al = $this->alert($a);
        $this->actingAs($owner);
        AlertResource::notifyAssignee($al, $mate->id, null);
        Mail::assertQueued(AlertAssignedMail::class, fn ($m) => $m->hasTo($mate->email));
        AlertResource::notifyAssignee($al, $owner->id, null); // assigning to yourself: no mail
        Mail::assertQueued(AlertAssignedMail::class, 1);
    }
}
