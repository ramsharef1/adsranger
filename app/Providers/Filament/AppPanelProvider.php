<?php

namespace App\Providers\Filament;

use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
            ->profile(isSimple: false)
            ->tenant(\App\Models\Agency::class, slugAttribute: 'slug')
            ->tenantRegistration(\App\Filament\Pages\Tenancy\RegisterAgency::class)
            ->tenantProfile(\App\Filament\Pages\Tenancy\EditAgencyProfile::class)
            ->colors([
                'primary' => Color::Teal,
            ])
            ->userMenuItems([
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
