<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Tenancy\EditAgencyProfile;
use App\Filament\Pages\Tenancy\RegisterAgency;
use App\Http\Middleware\SetPanelLocale;
use App\Models\Agency;
use App\Support\PanelColors;
use Filament\Actions\Action;
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
            ->login()
            ->passwordReset(RequestPasswordReset::class)
            ->font('IBM Plex Sans Arabic', provider: GoogleFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('panels.head'))
            ->renderHook(PanelsRenderHook::SIMPLE_PAGE_END, fn () => view('panels.lang-switch'))
            ->profile(isSimple: false)
            ->tenant(Agency::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterAgency::class)
            ->tenantProfile(EditAgencyProfile::class)
            ->colors([
                // UX-6: buttons fill with #0F766E (teal-700, white text 5.5:1) in both themes; #14B8A6 stays for accents.
                'primary' => PanelColors::primary(),
            ])
            ->userMenuItems([
                Action::make('switch-language')
                    ->label(fn (): string => __('ui.switch_to'))
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => request()->fullUrlWithQuery(['lang' => __('ui.switch_locale')])),
                Action::make('admin-panel')
                    ->label('Admin panel')
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
