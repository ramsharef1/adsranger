<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\PlatformSettings;
use App\Filament\Admin\Resources\Agencies\AgencyResource;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Tenancy\RegisterAgency;
use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Support\DemoData;
use App\Support\PlanGate;
use App\Support\PlanLimits;
use App\Support\QuotaNotice;
use App\Support\Signup;
use App\Support\Trial;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** #158 self-signup behind `signup.open` (D-42, D-53) + the 14-day Pro trial with caps (D-61). */
class SelfSignupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Notification::fake();
    }

    private function open(): void
    {
        PlatformSetting::put(Signup::FLAG, true);
    }

    private function signup(array $over = []): Testable
    {
        return Livewire::test(Register::class)->fillForm(array_merge([
            'name' => 'Lina Haddad',
            'email' => 'lina@haddad-media.test',
            'password' => 'correct-horse-9',
            'passwordConfirmation' => 'correct-horse-9',
            'agency_name' => 'Haddad Media',
            'country' => 'JO',
            'consent' => true,
        ], $over))->call('register');
    }

    private function verify(User $user): TestResponse
    {
        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use (&$url) {
            $url = $n->url;

            return true;
        });

        return $this->actingAs($user)->get($url);
    }

    public function test_flag_is_off_by_default(): void
    {
        $this->assertFalse(config('adsranger.signup.open'));
        $this->assertFalse(Signup::isOpen());
    }

    public function test_flag_off_shows_the_invite_only_copy_and_creates_nothing(): void
    {
        $this->get('/app/register')->assertOk()
            ->assertSee('التسجيل بالدعوة حاليًا')
            ->assertSee('أدز رينجر في مرحلة تجريبية خاصة.')
            ->assertSee('mailto:support@adsranger.com', false)
            ->assertSee('<bdi dir="ltr">support@adsranger.com</bdi>', false)
            ->assertDontSee('name="data.password"', false);
        $this->get('/app/register?lang=en')->assertOk()->assertSee('Sign-up is by invitation for now');

        // A crafted POST (Livewire call) is refused too.
        $this->signup();
        $this->assertSame(0, User::count());
        $this->assertSame(0, Agency::count());

        // The login page has no "sign up" link while closed.
        $this->get('/app/login?lang=en')->assertOk()->assertDontSee('/app/register', false);
    }

    public function test_flag_on_signup_then_verify_creates_a_14_day_pro_trial(): void
    {
        $this->open();
        $this->get('/app/register?lang=en')->assertOk()->assertSee('Create account and start your trial')
            ->assertSee('14 days on Pro · up to 5 ad accounts and 3 team members · no card needed');
        $this->get('/app/login?lang=en')->assertSee('/app/register', false);

        $this->signup()->assertHasNoFormErrors();
        $user = User::where('email', 'lina@haddad-media.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame(0, Agency::count(), 'no agency before the email is verified');
        $this->assertSame('Haddad Media', $user->signup_payload['agency_name']);

        // Unverified users are held at the prompt.
        $this->actingAs($user)->get('/app')->assertRedirect();
        $this->actingAs($user)->get('/app/email-verification/prompt')->assertOk()->assertSee('<bdi dir="ltr">lina@haddad-media.test</bdi>', false);

        $this->verify($user)->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->signup_payload);
        $agency = Agency::firstOrFail();
        $this->assertSame('self_signup', $agency->signup_source);
        $this->assertSame('trial', $agency->status);
        $this->assertSame('starter', $agency->plan);
        $this->assertSame($user->id, $agency->owner_id);
        $this->assertSame('owner', $user->roleIn($agency));
        $this->assertSame('JO', $agency->country);
        $this->assertSame('haddad-media', $agency->slug);
        $this->assertEqualsWithDelta(now()->addDays(14)->timestamp, $agency->trial_ends_at->timestamp, 5);
        $this->assertSame(14, Trial::daysLeft($agency));
        $this->assertSame('pro', PlanGate::tier($agency));
        $this->assertTrue(PlanGate::allows($agency, 'insights'));
        $this->assertTrue(AuditLog::where('action', 'agency.self_signup')->where('subject_id', $agency->id)->exists());

        // Into the workspace (onboarding checklist on the dashboard), with the trial banner and the plans link.
        $this->actingAs($user)->withSession(['locale' => 'en'])->get('/app/'.$agency->slug)->assertOk()
            ->assertSee('14 days left in your Pro trial')->assertSee('See plans');
        $this->actingAs($user)->withSession(['locale' => 'ar'])->get('/app/'.$agency->slug)->assertOk()
            ->assertSee('تبقّى 14 يومًا من الفترة التجريبية للباقة الاحترافية');

        // Verifying again never makes a second agency; a self-signup owner can't open more trial workspaces.
        Signup::completeVerified($user);
        $this->assertSame(1, Agency::count());
        $this->actingAs($user);
        $this->assertFalse(RegisterAgency::canView());
    }

    public function test_verify_mail_is_queued_in_the_page_language(): void
    {
        $this->open();
        app()->setLocale('en');
        $this->signup(['email' => 'sam@example-agency.test']);
        $user = User::where('email', 'sam@example-agency.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user) {
            $mail = $n->toMail($user);

            return $n instanceof ShouldQueue && $n->locale === 'en'
                && $mail->subject === 'Confirm your email to start using AdsRanger';
        });

        auth()->logout();
        app()->setLocale('ar');
        $this->signup(['email' => 'rana@other-agency.test']);
        $ar = User::where('email', 'rana@other-agency.test')->firstOrFail();
        Notification::assertSentTo($ar, VerifyEmail::class, function (VerifyEmail $n) use ($ar) {
            $html = view($n->toMail($ar)->view, $n->toMail($ar)->viewData)->render();

            return $n->locale === 'ar' && str_contains($html, 'dir="rtl"') && str_contains($html, 'الرابط صالح لمدة 60 دقيقة');
        });
    }

    public function test_duplicate_email_is_refused_with_the_generic_message(): void
    {
        $this->open();
        User::factory()->create(['email' => 'taken@gmail.com']);
        app()->setLocale('en');
        $this->signup(['email' => 'Taken@Gmail.com'])->assertHasFormErrors(['email']);
        $this->assertSame(1, User::count());
    }

    public function test_disposable_domain_is_refused(): void
    {
        $this->open();
        $this->signup(['email' => 'bot@mailinator.com'])->assertHasFormErrors(['email']);
        $this->assertSame(0, User::count());
    }

    public function test_second_trial_on_the_same_business_domain_needs_the_allowlist(): void
    {
        $this->open();
        $this->signup(['email' => 'one@acme-ads.test'])->assertHasNoFormErrors();
        auth()->logout();
        $this->signup(['email' => 'two@acme-ads.test', 'agency_name' => 'Acme 2'])->assertHasFormErrors(['email']);
        $this->assertSame(1, User::count());

        // Shared providers are exempt (D-61: domain rule for business domains only).
        $this->signup(['email' => 'a@gmail.com'])->assertHasNoFormErrors();
        auth()->logout();
        $this->signup(['email' => 'b@gmail.com'])->assertHasNoFormErrors();
        auth()->logout();

        PlatformSetting::put(Signup::ALLOWLIST, ['acme-ads.test']);
        $this->signup(['email' => 'two@acme-ads.test', 'agency_name' => 'Acme 2'])->assertHasNoFormErrors();
        $this->assertSame(4, User::count());
    }

    public function test_throttle_refuses_the_sixth_attempt_in_an_hour(): void
    {
        $this->open();
        for ($i = 1; $i <= 5; $i++) {
            $this->signup(['email' => "p$i@gmail.com"])->assertHasNoFormErrors();
            auth()->logout();
        }
        $this->assertSame(5, User::count());
        $this->signup(['email' => 'p6@gmail.com'])->assertNotified();
        $this->assertSame(5, User::count(), 'the 6th attempt from the same IP is refused');
    }

    public function test_honeypot_creates_nothing(): void
    {
        $this->open();
        $this->signup(['website' => 'http://spam.test']);
        $this->assertSame(0, User::count());
    }

    public function test_password_rules(): void
    {
        $this->open();
        $this->signup(['password' => 'short-pw1', 'passwordConfirmation' => 'short-pw1'])->assertHasFormErrors(['password']);
        $this->signup(['passwordConfirmation' => 'something-else'])->assertHasFormErrors(['password']);
        $this->signup(['consent' => false])->assertHasFormErrors(['consent']);
        $this->assertSame(0, User::count());
    }

    private function trialAgency(): array
    {
        $a = Agency::create(['name' => 'Trial Co', 'slug' => 'trial-co', 'plan' => 'starter', 'status' => 'trial',
            'trial_ends_at' => now()->addDays(14), 'signup_source' => 'self_signup', 'language' => 'en']);
        $owner = User::factory()->create();
        $a->members()->attach($owner->id, ['role' => 'owner']);

        return [$a, $owner];
    }

    public function test_trial_caps_five_ad_accounts(): void
    {
        [$a, $owner] = $this->trialAgency();
        DemoData::seed($a, 5);
        $tpl = AdAccount::where('agency_id', $a->id)->firstOrFail();
        AdAccount::where('agency_id', $a->id)->update(['is_active' => false]);
        for ($i = 0; $i < 7; $i++) {
            AdAccount::create(['agency_id' => $a->id, 'connection_id' => $tpl->connection_id, 'ad_network_id' => $tpl->ad_network_id, 'external_id' => "cap-$i", 'is_active' => false]);
        }

        $this->assertSame(5, PlanLimits::adAccountsLimit($a), 'Starter allows 10, the trial caps at 5');
        $accounts = AdAccount::where('agency_id', $a->id)->where('external_id', 'like', 'cap-%')->orderBy('id')->get();
        foreach ($accounts->take(5) as $acc) {
            $this->assertTrue(PlanLimits::canActivateAdAccount($a));
            $acc->update(['is_active' => true]);
        }
        $this->assertFalse(PlanLimits::canActivateAdAccount($a), 'the 6th account is refused');
        $this->assertTrue(PlanLimits::isTrialCap($a, 'ad_accounts'));

        app()->setLocale('en');
        $n = QuotaNotice::make($a, $owner, 'ad_accounts')->toArray();
        $this->assertSame('Trial limit reached: 5 ad accounts', $n['title']);
        $this->assertSame('See plans', $n['actions'][0]['label']);

        app()->setLocale('ar');
        $analyst = User::factory()->create();
        $a->members()->attach($analyst->id, ['role' => 'analyst']);
        $n = QuotaNotice::make($a, $analyst, 'ad_accounts')->toArray();
        $this->assertSame('وصلت إلى حد الفترة التجريبية: 5 حسابات إعلانية', $n['title']);
        $this->assertSame([], $n['actions']);
        $this->assertStringContainsString('اطلب من مالك مساحة العمل الترقية', $n['body']);

        // After the trial: Starter limits and Starter features, plus the "Trial ended" banner.
        $a->update(['trial_ends_at' => now()->subDay()]);
        $this->assertTrue(Trial::hasEnded($a));
        $this->assertSame(10, PlanLimits::adAccountsLimit($a));
        $this->assertSame('starter', PlanGate::tier($a));
        $this->actingAs($owner)->withSession(['locale' => 'en'])->get('/app/trial-co')->assertOk()
            ->assertSee('Your Pro trial has ended. Your workspace is on Starter now.');
    }

    public function test_trial_caps_three_seats(): void
    {
        [$a, $owner] = $this->trialAgency();
        $a->planModel->update(['seats' => 10]); // even with a bigger plan, the trial caps seats at 3
        foreach (['m1@x.test', 'm2@x.test'] as $email) {
            $a->members()->attach(User::factory()->create(['email' => $email])->id, ['role' => 'manager']);
        }
        $this->assertSame(3, PlanLimits::seatsLimit($a));
        $this->assertFalse(PlanLimits::canAddSeat($a), 'the 4th seat is refused');

        // The Members invite action sends this notice (table pages can't render locally without intl).
        app()->setLocale('en');
        $n = QuotaNotice::make($a, $owner, 'seats')->toArray();
        $this->assertSame('Trial limit reached: 3 team members', $n['title']);
        $this->assertSame('See plans', $n['actions'][0]['label']);
        app()->setLocale('ar');
        $this->assertSame('وصلت إلى حد الفترة التجريبية: 3 أعضاء في الفريق', QuotaNotice::make($a, $owner, 'seats')->toArray()['title']);

        // Pending invites count toward seats too.
        $a->members()->detach($a->members()->where('email', 'm2@x.test')->first()->id);
        $this->assertTrue(PlanLimits::canAddSeat($a));
        AgencyInvitation::send($a, 'pending@x.test', 'analyst', $owner);
        $this->assertFalse(PlanLimits::canAddSeat($a));
    }

    public function test_existing_invite_agencies_keep_plan_limits_when_active(): void
    {
        $a = Agency::create(['name' => 'Paid', 'slug' => 'paid', 'plan' => 'starter', 'status' => 'active']);
        $this->assertSame(10, PlanLimits::adAccountsLimit($a));
        $this->assertFalse(PlanLimits::isTrialCap($a, 'ad_accounts'));
    }

    public function test_admin_extends_a_trial_and_it_is_audit_logged(): void
    {
        [$a] = $this->trialAgency();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $before = $a->trial_ends_at->copy();
        $to = Trial::extend($a, 7);
        $this->assertEqualsWithDelta($before->addDays(7)->timestamp, $to->timestamp, 2);
        $log = AuditLog::where('action', 'agency.trial_extended')->firstOrFail();
        $this->assertSame(7, $log->meta['days']);
        $this->assertSame($admin->id, $log->user_id);

        // From today when the trial already ended.
        $a->update(['trial_ends_at' => now()->subDays(3)]);
        $this->assertEqualsWithDelta(now()->addDays(5)->timestamp, Trial::extend($a, 5)->timestamp, 2);

        $action = AgencyResource::extendTrialAction();
        $this->assertSame('extendTrial', $action->getName());
        $this->assertSame(['invite' => 'Invite', 'self_signup' => 'Self-signup', 'admin' => 'Created by admin'], AgencyResource::sources());
    }

    public function test_admin_toggles_the_flag_and_it_is_audit_logged(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->get('/admin/platform-settings')->assertOk();

        Livewire::test(PlatformSettings::class)->set('signupOpen', true)->set('allowlist', "Agency.test\n@other.test, not a domain")->call('save');
        $this->assertTrue(Signup::isOpen());
        $this->assertSame(['agency.test', 'other.test'], Signup::allowlist());
        $this->assertTrue(AuditLog::where('action', 'platform.settings_updated')->exists());

        $member = User::factory()->create();
        $this->actingAs($member)->get('/admin/platform-settings')->assertForbidden();
    }

    public function test_new_agency_cannot_see_another_agencys_client(): void
    {
        $other = Agency::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'pro', 'status' => 'active']);
        $secret = Client::create(['agency_id' => $other->id, 'name' => 'Secret Client']);

        $this->open();
        $this->signup(['email' => 'new@fresh-agency.test'])->assertHasNoFormErrors();
        $user = User::where('email', 'new@fresh-agency.test')->firstOrFail();
        $this->verify($user);
        $mine = $user->agencies()->firstOrFail();

        $this->assertFalse($user->canAccessTenant($other));
        $this->actingAs($user)->get('/app/other')->assertNotFound();
        $this->actingAs($user)->get("/app/{$mine->slug}/clients/{$secret->id}")->assertNotFound();
        $this->actingAs($user)->get("/app/{$mine->slug}/clients/{$secret->id}/edit")->assertNotFound();
    }

    public function test_existing_users_are_not_held_at_verification(): void
    {
        $a = Agency::create(['name' => 'Old', 'slug' => 'old', 'plan' => 'starter', 'status' => 'active']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'owner']);
        $this->actingAs($u)->get('/app/old')->assertOk();

        $admin = User::factory()->unverified()->create(['role' => 'super_admin']);
        $this->assertTrue($admin->hasVerifiedEmail());
    }
}
