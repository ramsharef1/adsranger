<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Mail\TwoStepResetMail;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** Super admin "Reset two-step verification" on /admin › Users (EX-32 confirm, audit, email; never your own). */
class AdminTwoStepResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_resets_a_users_two_step_and_the_user_is_told(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $user = User::factory()->create(['email' => 'lina@acme.test']);
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXQ', 'app_authentication_recovery_codes' => ['a', 'b']])->save();
        $this->assertTrue($user->fresh()->hasTwoStep());

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $action = UserResource::resetTwoStepAction();
        $this->assertTrue($action->record($user)->isVisible());
        $this->assertTrue($action->isConfirmationRequired(), 'EX-32 confirm');
        $this->assertFalse(UserResource::resetTwoStepAction()->record($admin)->isVisible(), 'never your own');

        $action->record($user)->call();

        $user->refresh();
        $this->assertFalse($user->hasTwoStep());
        $this->assertNull($user->app_authentication_recovery_codes);
        $log = AuditLog::where('action', 'auth.2fa_reset_by_admin')->firstOrFail();
        $this->assertSame($user->id, $log->subject_id);
        $this->assertSame($admin->id, $log->user_id);
        Mail::assertQueued(TwoStepResetMail::class, fn (TwoStepResetMail $m) => $m->hasTo('lina@acme.test') && $m->lang === 'ar');

        $html = (new TwoStepResetMail($user, 'ar', url('/app/login')))->render();
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('أوقف مدير النظام في AdsRanger التحقق بخطوتين لحسابك', $html);
        $this->assertStringContainsString('Your AdsRanger two-step verification was reset', (new TwoStepResetMail($user, 'en', url('/app/login')))->envelope()->subject);
    }

    public function test_own_account_is_refused_even_if_called_directly(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        try {
            UserResource::resetTwoStepAction()->record($admin)->call();
            $this->fail('expected 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertTrue($admin->fresh()->hasTwoStep());
        $this->assertSame(0, AuditLog::where('action', 'auth.2fa_reset_by_admin')->count());
    }
}
