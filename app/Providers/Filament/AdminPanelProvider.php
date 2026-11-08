<?php

namespace App\Providers\Filament;

use App\Auth\AppAuthentication;
use App\Filament\Admin\Widgets\SystemOverviewWidget;
use App\Filament\Pages\Auth\AdminLogin;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Http\Middleware\SetPanelLocale;
use Filament\Actions\Action;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** System admin (adsranger.com/admin): super admins only. Controls the platform, holds no ad accounts. */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            // UX-48: browser titles read «AdsRanger · مدير النظام» / "AdsRanger · System admin".
            ->brandName(fn (): string => __('AdsRanger · System admin'))
            ->brandLogo(fn () => view('brand.logo-admin'))
            ->brandLogoHeight('2rem')
            ->favicon(asset('brand/favicon.svg'))
            ->login(AdminLogin::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            // #74 super admins must use two-step verification. No lock-out at deploy: an admin without it signs in
            // with the password as before and is then held on the set-up screen (QR + code + recovery codes)
            // until it is on; every /admin page redirects there until then. Afterwards each sign-in asks for a code.
            ->multiFactorAuthentication([AppAuthentication::configured()], isRequired: true)
            ->font('IBM Plex Sans Arabic', provider: GoogleFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('panels.head'))
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('panels.admin-head'))
            // UX-5: a permanent "System admin" label on the login and in the panel.
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_START, fn () => view('panels.admin-label-auth'))
            ->renderHook(PanelsRenderHook::TOPBAR_START, fn () => view('panels.admin-label'))
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_END, fn () => view('panels.lang-switch'))
            ->profile(isSimple: false)
            ->colors([
                'primary' => Color::Teal,
            ])
            ->userMenuItems([
                Action::make('switch-language')
                    ->label(fn (): string => __('ui.switch_to'))
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => request()->fullUrlWithQuery(['lang' => __('ui.switch_locale')])),
                Action::make('agency-app')
                    ->label(fn (): string => __('Agency app'))
                    ->icon('heroicon-o-squares-2x2')
                    ->url(fn (): string => url('/app')),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                AccountWidget::class,
                SystemOverviewWidget::class,
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
