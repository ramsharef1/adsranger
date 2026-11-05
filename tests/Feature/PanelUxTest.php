<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Models\User;
use App\Support\ErrorRef;
use App\Support\PanelColors;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** Panel UX fixes from UX-H1 batch 0 (UX-3…7, UX-9) and password reset (#158). */
class PanelUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_panels_default_to_arabic_rtl_with_a_language_switch(): void
    {
        $this->get('/app/login')->assertOk()
            ->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false)
            ->assertSee('lang=en', false)
            ->assertSee('English');
    }

    public function test_lang_query_switches_and_sticks(): void
    {
        $this->get('/app/login?lang=en')->assertOk()->assertSee('lang="en"', false)->assertSee('dir="ltr"', false)->assertSee('العربية');
        $this->get('/app/login')->assertOk()->assertSee('lang="en"', false)->assertSee('dir="ltr"', false);
        $this->get('/app/login?lang=ar')->assertOk()->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false);
        $this->get('/app/login?lang=fr')->assertOk()->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false);
    }

    public function test_language_follows_the_site_the_visitor_came_from(): void
    {
        $this->withHeader('referer', 'http://localhost/en')->get('/app/login')->assertSee('lang="en"', false)->assertSee('dir="ltr"', false);
    }

    public function test_panel_font_and_button_colour(): void
    {
        $this->get('/app/login')->assertSee('IBM+Plex+Sans+Arabic', false)->assertSee('min-height: 44px', false);
        $this->assertSame(Color::Teal[700], PanelColors::primary()[600], 'filled buttons use #0F766E (teal-700)');
    }

    public function test_admin_login_is_labelled_and_looks_different(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertSee('مدير النظام · System admin')
            ->assertSee('#0f172a', false);
    }

    public function test_agency_login_has_no_admin_label(): void
    {
        $this->get('/app/login')->assertOk()->assertDontSee('مدير النظام · System admin');
    }

    public function test_login_offers_forgot_password(): void
    {
        $this->get('/app/login?lang=en')->assertSee('/app/password-reset/request', false);
        $this->get('/app/password-reset/request')->assertOk();
        $this->get('/admin/password-reset/request')->assertOk();
    }

    public function test_reset_request_never_reveals_whether_an_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.test']);
        Filament::setCurrentPanel(Filament::getPanel('app'));

        $sent = __('passwords.sent');
        Livewire::test(RequestPasswordReset::class)->set('data.email', 'known@example.test')->call('request')->assertNotified($sent);
        Livewire::test(RequestPasswordReset::class)->set('data.email', 'nobody@example.test')->call('request')->assertNotified($sent);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_completed_reset_is_audit_logged(): void
    {
        $user = User::factory()->create();
        event(new PasswordReset($user));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.password_reset']);
    }

    public function test_branded_error_pages(): void
    {
        $this->get('/does-not-exist')->assertNotFound()
            ->assertSee('lang="ar"', false)->assertSee('dir="rtl"', false)
            ->assertSee('لم نجد هذه الصفحة')
            ->assertSee(url('/app'), false);
        $this->get('/en/does-not-exist')->assertNotFound()
            ->assertSee('lang="en"', false)->assertSee('dir="ltr"', false)
            ->assertSee('We couldn&#039;t find this page', false);

        $html = view('errors.500')->render();
        $this->assertStringContainsString(app(ErrorRef::class)->id(), $html);
        $this->assertStringNotContainsString('Reference', view('errors.404')->render());
    }
}
