<?php

namespace Tests\Feature;

use App\Auth\AppAuthentication;
use App\Filament\Pages\Auth\GoogleFinishSignup;
use App\Filament\Pages\Auth\GoogleTwoStep;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Tenancy\EditAgencyProfile;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\GoogleSignIn;
use App\Support\Signup;
use Filament\Auth\Pages\EditProfile;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** #74 two-step verification (authenticator app + recovery codes) and "Continue with Google" (hidden until keys are set). */
class TwoFactorAndGoogleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
    }

    private function keys(): void
    {
        config(['services.google.client_id' => 'test-client.apps.googleusercontent.com', 'services.google.client_secret' => 'test-secret']);
    }

    private function member(string $email = 'sara@acme.test', string $role = 'owner', string $slug = 'acme'): array
    {
        $a = Agency::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'plan' => 'starter', 'status' => 'active', 'language' => 'en', 'timezone' => 'Asia/Amman', 'currency' => 'USD', 'country' => 'JO']);
        $u = User::factory()->create(['email' => $email, 'password' => 'correct-horse-9']);
        $a->members()->attach($u->id, ['role' => $role]);

        return [$a, $u];
    }

    /** Turn two-step on the way the set-up action does (secret + recovery codes); returns [secret, codes]. */
    private function enable(User $u): array
    {
        $p = AppAuthentication::configured();
        $secret = $p->generateSecret();
        $codes = $p->generateRecoveryCodes();
        $p->saveSecret($u, $secret);
        $p->saveRecoveryCodes($u, $codes);

        return [$secret, $codes];
    }

    private function totp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function googleUser(string $email, bool $verified = true, string $id = 'g-1001', string $name = 'Sara Haddad'): GoogleUser
    {
        $g = (new GoogleUser)->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified, 'name' => $name])
            ->map(['id' => $id, 'email' => $email, 'name' => $name]);
        Socialite::shouldReceive('driver->user')->andReturn($g);

        return $g;
    }

    // ---- Two-step verification -------------------------------------------------------------------------------

    public function test_enable_needs_a_valid_code_and_is_audit_logged(): void
    {
        [, $u] = $this->member();
        $p = AppAuthentication::configured();
        $secret = $p->generateSecret();

        $wrong = $this->totp($secret) === '123456' ? '654321' : '123456';
        $this->assertFalse($p->verifyCode($wrong, $secret));
        $this->assertTrue($p->verifyCode($this->totp($secret), $secret));

        $this->actingAs($u);
        $p->saveSecret($u, $secret);
        $p->saveRecoveryCodes($u, $p->generateRecoveryCodes());
        $u->refresh();

        $this->assertTrue($u->hasTwoStep());
        $this->assertTrue($p->isEnabled($u));
        $this->assertCount(8, $u->getAppAuthenticationRecoveryCodes());
        // Stored encrypted, never in the model's array form.
        $this->assertNotSame($secret, \DB::table('users')->where('id', $u->id)->value('app_authentication_secret'));
        $this->assertArrayNotHasKey('app_authentication_secret', $u->toArray());
        $this->assertTrue(AuditLog::where('action', 'auth.2fa_enabled')->where('user_id', $u->id)->exists());

        // The profile shows the section with the glossary terms (AR).
        app()->setLocale('ar');
        Livewire::test(EditProfile::class)->assertSee('التحقق بخطوتين')->assertSee('تطبيق المصادقة');
    }

    public function test_login_asks_for_the_code_after_the_password(): void
    {
        [, $u] = $this->member();
        [$secret] = $this->enable($u);

        $lw = Livewire::test(Login::class)
            ->set('data.email', 'sara@acme.test')->set('data.password', 'correct-horse-9')
            ->call('authenticate');
        $this->assertGuest();
        $this->assertNotEmpty($lw->get('userUndertakingMultiFactorAuthentication'));

        // A wrong code keeps the user out.
        $lw->set('data.multiFactor.app.code', $this->totp($secret) === '123456' ? '654321' : '123456')->call('authenticate')
            ->assertHasErrors();
        $this->assertGuest();

        $lw->set('data.multiFactor.app.code', $this->totp($secret))->call('authenticate');
        $this->assertAuthenticatedAs($u);
    }

    public function test_recovery_code_works_once(): void
    {
        [, $u] = $this->member();
        [, $codes] = $this->enable($u);

        $login = fn () => Livewire::test(Login::class)
            ->set('data.email', 'sara@acme.test')->set('data.password', 'correct-horse-9')
            ->call('authenticate')
            ->set('data.multiFactor.app.useRecoveryCode', true)
            ->set('data.multiFactor.app.recoveryCode', $codes[0])
            ->call('authenticate');

        $login();
        $this->assertAuthenticatedAs($u);
        $this->assertCount(7, $u->fresh()->getAppAuthenticationRecoveryCodes());
        $log = AuditLog::where('action', 'auth.recovery_code_used')->firstOrFail();
        $this->assertSame(7, $log->meta['codes_left']);

        auth()->logout();
        $login()->assertHasErrors();
        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('action', 'auth.recovery_code_used')->count());
    }

    public function test_disable_clears_the_secret_and_is_audit_logged(): void
    {
        [, $u] = $this->member();
        $this->enable($u);
        $p = AppAuthentication::configured();
        $p->saveSecret($u, null);
        $p->saveRecoveryCodes($u, null);

        $this->assertFalse($u->fresh()->hasTwoStep());
        $this->assertTrue(AuditLog::where('action', 'auth.2fa_disabled')->where('user_id', $u->id)->exists());

        // Without two-step the password alone signs in again.
        Livewire::test(Login::class)->set('data.email', 'sara@acme.test')->set('data.password', 'correct-horse-9')->call('authenticate');
        $this->assertAuthenticatedAs($u);
    }

    public function test_admin_panel_requires_two_step_setup(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        // No lock-out: an admin without it is held on the set-up screen, not refused.
        $this->actingAs($admin)->get('/admin/platform-settings')
            ->assertRedirectContains('/admin/multi-factor-authentication/set-up');
        $this->actingAs($admin)->withSession(['locale' => 'ar'])->get('/admin/multi-factor-authentication/set-up')
            ->assertOk()->assertSee('إعداد التحقق بخطوتين');

        $this->enable($admin);
        $this->actingAs($admin->fresh())->get('/admin/platform-settings')->assertOk();

        // /admin/login never offers Google, even with keys.
        auth()->logout();
        $this->keys();
        $this->get('/admin/login')->assertOk()->assertDontSee('google-signin');
    }

    public function test_agency_can_require_two_step_for_its_members(): void
    {
        [$a, $owner] = $this->member();
        [, $analyst] = $this->member('ali@acme.test', 'analyst');
        [$other, $elsewhere] = $this->member('noor@other.test', 'owner', 'other');

        // Owner turns it on in Agency settings (audit-logged).
        $this->actingAs($owner);
        Filament::setTenant($a);
        Livewire::test(EditAgencyProfile::class)->set('data.require_two_factor', true)->call('save')->assertHasNoErrors();
        $this->assertTrue($a->fresh()->require_two_factor);
        $this->assertTrue(AuditLog::where('action', 'agency.require_2fa_changed')->exists());

        $this->actingAs($analyst)->get('/app/acme')->assertRedirectContains('/app/multi-factor-authentication/set-up');
        $this->flushSession();
        $this->actingAs($owner)->get('/app/acme')->assertRedirectContains('/app/multi-factor-authentication/set-up');
        $this->enable($analyst);
        $this->flushSession();
        $this->actingAs($analyst->fresh())->get('/app/acme')->assertOk();

        // Workspaces without the rule are untouched.
        $this->flushSession();
        $this->actingAs($elsewhere)->get('/app/other')->assertOk();
        $this->assertFalse((bool) $other->fresh()->require_two_factor);
    }

    // ---- Google sign-in ---------------------------------------------------------------------------------------

    public function test_google_button_is_hidden_without_keys_and_shown_with_them(): void
    {
        $this->get('/app/login')->assertOk()->assertDontSee('google-signin');
        $this->get('/app/oauth/google/redirect')->assertNotFound();
        $this->get('/app/oauth/google/callback')->assertNotFound();

        $this->keys();
        $this->withSession(['locale' => 'en'])->get('/app/login')->assertOk()->assertSee('google-signin')
            ->assertSee('Continue with <bdi dir="ltr">Google</bdi>', false);
        $this->withSession(['locale' => 'ar'])->get('/app/login')->assertOk()
            ->assertSee('المتابعة باستخدام <bdi dir="ltr">Google</bdi>', false);
        $this->get('/app/oauth/google/redirect')->assertRedirectContains('accounts.google.com');

        // Register: only while signup is open.
        $this->get('/app/register')->assertOk()->assertDontSee('google-signin');
        PlatformSetting::put(Signup::FLAG, true);
        $this->get('/app/register')->assertOk()->assertSee('google-signin');
    }

    public function test_existing_verified_email_signs_in_and_links_google_id(): void
    {
        $this->keys();
        [, $u] = $this->member('Sara@Acme.test');
        $this->googleUser('sara@acme.test');

        $this->get('/app/oauth/google/callback')->assertRedirect();
        $this->assertAuthenticatedAs($u);
        $this->assertSame('g-1001', $u->fresh()->google_id);
        $this->assertTrue(AuditLog::where('action', 'auth.google_login')->where('user_id', $u->id)->exists());
    }

    public function test_unverified_google_email_is_refused(): void
    {
        $this->keys();
        [, $u] = $this->member();
        $this->googleUser('sara@acme.test', verified: false);

        $this->get('/app/oauth/google/callback')->assertRedirect('/app/login');
        $this->assertGuest();
        $this->assertNull($u->fresh()->google_id);
    }

    public function test_unconfirmed_local_account_and_super_admin_are_never_linked(): void
    {
        $this->keys();
        $pending = User::factory()->unverified()->create(['email' => 'pending@acme.test']);
        $this->googleUser('pending@acme.test');
        $this->get('/app/oauth/google/callback')->assertRedirect('/app/login');
        $this->assertGuest();
        $this->assertNull($pending->fresh()->google_id);

        $admin = User::factory()->create(['email' => 'root@adsranger.test', 'role' => 'super_admin']);
        $this->googleUser('root@adsranger.test', id: 'g-2002');
        $this->get('/app/oauth/google/callback')->assertRedirect('/app/login');
        $this->assertGuest();
        $this->assertNull($admin->fresh()->google_id);
    }

    public function test_google_never_skips_two_step(): void
    {
        $this->keys();
        [, $u] = $this->member();
        [$secret] = $this->enable($u);
        $this->googleUser('sara@acme.test');

        $this->get('/app/oauth/google/callback')->assertRedirect('/app/oauth/google/two-step');
        $this->assertGuest();
        $this->get('/app/oauth/google/two-step')->assertOk()->assertSee('inputmode="numeric"', false);

        Livewire::test(GoogleTwoStep::class)->set('data.code', '000001')->call('verify')->assertHasErrors();
        $this->assertGuest();
        Livewire::test(GoogleTwoStep::class)->set('data.code', $this->totp($secret))->call('verify')->assertHasNoErrors();
        $this->assertAuthenticatedAs($u);
    }

    public function test_new_email_with_signup_closed_is_refused(): void
    {
        $this->keys();
        $this->googleUser('new@fresh-agency.test');

        $this->get('/app/oauth/google/callback')->assertRedirect('/app/register');
        $this->assertGuest();
        $this->assertSame(0, User::count());
        $this->get('/app/register')->assertOk()->assertSee('التسجيل بالدعوة حاليًا');
    }

    public function test_new_email_with_signup_open_creates_the_trial_agency(): void
    {
        $this->keys();
        PlatformSetting::put(Signup::FLAG, true);
        $other = Agency::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'pro', 'status' => 'active']);
        $secretClient = Client::create(['agency_id' => $other->id, 'name' => 'Secret Client']);
        $this->googleUser('lina@haddad-media.test', id: 'g-3003', name: 'Lina Haddad');

        $this->get('/app/oauth/google/callback')->assertRedirect('/app/oauth/google/finish');
        $this->assertSame(0, User::count());
        $this->assertNull(User::where('email', 'lina@haddad-media.test')->first());
        $this->withSession(['locale' => 'ar'])->get('/app/oauth/google/finish')->assertOk()
            ->assertSee('<bdi dir="ltr">lina@haddad-media.test</bdi>', false);

        Livewire::test(GoogleFinishSignup::class)
            ->set('data.agency_name', 'Haddad Media')->set('data.country', 'JO')->set('data.consent', true)
            ->call('finish')->assertHasNoErrors();

        $user = User::where('email', 'lina@haddad-media.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('g-3003', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->signup_payload);
        $agency = $user->agencies()->firstOrFail();
        $this->assertSame('owner', $agency->pivot->role);
        $this->assertSame(['starter', 'trial', 'self_signup', 'JO'], [$agency->plan, $agency->status, $agency->signup_source, $agency->country]);
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $agency->trial_ends_at->timestamp, 5);
        $this->assertTrue(AuditLog::where('action', 'agency.self_signup')->exists());
        $this->assertTrue(AuditLog::where('action', 'auth.google_login')->where('user_id', $user->id)->exists());

        // Tenant isolation unaffected (once past the D-188 onboarding).
        $agency->forceFill(['onboarding_completed_at' => now()])->save();
        $this->assertFalse($user->canAccessTenant($other));
        $this->actingAs($user)->get('/app/other')->assertNotFound();
        $this->actingAs($user)->get("/app/{$agency->slug}/clients/{$secretClient->id}/edit")->assertNotFound();
    }

    public function test_google_signup_follows_the_trial_rules(): void
    {
        $this->keys();
        PlatformSetting::put(Signup::FLAG, true);

        // Disposable inbox.
        $this->googleUser('bot@mailinator.com', id: 'g-4');
        $this->get('/app/oauth/google/callback')->assertRedirect('/app/login');

        // One trial per business domain (D-61).
        User::factory()->create(['email' => 'first@acme-ads.test']);
        $this->googleUser('second@acme-ads.test', id: 'g-5');
        $this->get('/app/oauth/google/callback')->assertRedirect('/app/login');

        $this->assertNull(session(GoogleSignIn::SIGNUP_SESSION));
        $this->assertSame(1, User::count());
        $this->assertGuest();

        // The finish page without a held Google identity goes back to sign-in.
        $this->get('/app/oauth/google/finish')->assertRedirect('/app/login');
    }
}
