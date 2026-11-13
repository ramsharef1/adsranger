<?php

namespace App\Providers\Filament;

use App\Auth\AppAuthentication;
use App\Filament\Pages\Auth\EmailVerificationPrompt;
use App\Filament\Pages\Auth\GoogleFinishSignup;
use App\Filament\Pages\Auth\GoogleTwoStep;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Pages\Tenancy\EditAgencyProfile;
use App\Filament\Pages\Tenancy\RegisterAgency;
use App\Filament\Pages\WhatsNewPage;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GoogleSignInController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\SampleDataController;
use App\Http\Middleware\EnsureAgencyTwoStep;
use App\Http\Middleware\EnsureOnboarded;
use App\Http\Middleware\FreePlanFence;
use App\Http\Middleware\SetPanelLocale;
use App\Http\Middleware\TrialLifecycleGate;
use App\Models\Agency;
use App\Support\PanelColors;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** Agency app (adsranger.com/app): agencies, their clients and connected ad accounts. */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->brandName('AdsRanger')
            ->brandLogo(fn () => view('brand.logo'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('brand/favicon.svg'))
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            // #158: /app/register always answers; the page itself shows the invite-only copy while `signup.open` is off.
            ->registration(Register::class)
            ->emailVerification(EmailVerificationPrompt::class)
            // #74 two-step verification (authenticator app + recovery codes), optional per user in /app/profile.
            // "Required" here only registers Filament's set-up screen: EnsureAgencyTwoStep enforces it for the
            // members of an agency that turned on "Require two-step verification for all members".
            ->multiFactorAuthentication([AppAuthentication::configured()], isRequired: true)
            ->multiFactorAuthenticationRequiredMiddlewareName(EnsureAgencyTwoStep::class)
            // #74 "Continue with Google" (/app only; 404 + hidden button while GOOGLE_CLIENT_ID/SECRET are empty).
            ->routes(function (): void {
                Route::get('oauth/google/redirect', [GoogleSignInController::class, 'redirect'])
                    ->middleware('throttle:20,1')->name('oauth.google.redirect');
                Route::get('oauth/google/callback', [GoogleSignInController::class, 'callback'])
                    ->middleware('throttle:20,1')->name('oauth.google.callback');
                Route::get('oauth/google/finish', GoogleFinishSignup::class)->name('oauth.google.finish');
                Route::get('oauth/google/two-step', GoogleTwoStep::class)->name('oauth.google.two-step');
            })
            // #79 CSV / ZIP downloads (signed in + member of the workspace; role scoping in the controller).
            ->authenticatedTenantRoutes(function (): void {
                Route::get('export/daily-metrics.csv', [ExportController::class, 'metrics'])->middleware('throttle:20,1')->name('export.metrics');
                Route::get('export/daily-metrics.xlsx', [ExportController::class, 'metricsXlsx'])->middleware('throttle:20,1')->name('export.metrics.xlsx');
                Route::get('export/clients.csv', [ExportController::class, 'clients'])->middleware('throttle:20,1')->name('export.clients');
                Route::get('export/ad-accounts.csv', [ExportController::class, 'adAccounts'])->middleware('throttle:20,1')->name('export.accounts');
                Route::get('export/workspace.zip', [ExportController::class, 'workspace'])->middleware('throttle:3,1')->name('export.workspace');
                // #175 Critical push: subscribe / unsubscribe this device, send a test.
                Route::post('push/subscribe', [PushController::class, 'subscribe'])->middleware('throttle:20,1')->name('push.subscribe');
                Route::post('push/unsubscribe', [PushController::class, 'unsubscribe'])->middleware('throttle:20,1')->name('push.unsubscribe');
                Route::post('push/test', [PushController::class, 'test'])->middleware('throttle:5,1')->name('push.test');
                // PS-05 (D-296): sample data in place, one click in and out (owner + admins).
                Route::post('sample-data/fill', [SampleDataController::class, 'fill'])->middleware('throttle:5,1')->name('sample-data.fill');
                Route::post('sample-data/clear', [SampleDataController::class, 'clear'])->middleware('throttle:5,1')->name('sample-data.clear');
            })
            ->livewireComponents([GoogleFinishSignup::class, GoogleTwoStep::class])
            ->font('IBM Plex Sans Arabic', provider: GoogleFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('panels.head'))
            // #175: PWA manifest + service worker for phone alerts.
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('panels.pwa'))
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_END, fn () => view('panels.lang-switch'))
            // D-61 trial banner (days left / trial ended), under the top bar.
            // EH-17 (D-293): the /admin announcement line, above the other banners.
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, fn () => view('panels.announcement'))
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, fn () => view('panels.sample-data'))
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, fn () => view('panels.context-bar'))
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, fn () => view('panels.trial-banner'))
            // EX-50 point 6: usage warning at 80 % of the ad-account / seat limit (owner/admin).
            ->renderHook(PanelsRenderHook::TOPBAR_AFTER, fn () => view('panels.usage-warning'))
            ->profile(isSimple: false)
            ->tenant(Agency::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterAgency::class)
            ->tenantProfile(EditAgencyProfile::class)
            // D-61 trial lifecycle (self-signup only): read-only pages refuse create/edit; closed workspaces open only
            // the "trial ended" page, Plan & usage and the exports. Livewire calls: App\Support\ReadOnlyGuard.
            ->tenantMiddleware([TrialLifecycleGate::class, EnsureOnboarded::class, FreePlanFence::class])
            ->colors([
                // UX-6: buttons fill with #0F766E (teal-700, white text 5.5:1) in both themes; #14B8A6 stays for accents.
                'primary' => PanelColors::primary(),
            ])
            ->userMenuItems([
                Action::make('whats-new')
                    ->label(fn (): string => __("What's new"))
                    ->icon('heroicon-o-sparkles')
                    ->url(fn (): string => Filament::getTenant() ? WhatsNewPage::getUrl(tenant: Filament::getTenant()) : url('/app'))
                    ->visible(fn (): bool => Filament::getTenant() !== null),
                Action::make('switch-language')
                    ->label(fn (): string => __('ui.switch_to'))
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => request()->fullUrlWithQuery(['lang' => __('ui.switch_locale')])),
                Action::make('admin-panel')
                    ->label(fn (): string => __('Admin panel'))
                    ->icon('heroicon-o-shield-check')
                    ->url(fn (): string => url('/admin'))
                    ->visible(fn (): bool => auth()->user()?->role === 'super_admin'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetPanelLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
