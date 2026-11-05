<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Support\ErrorRef;
use App\Support\Signup;
use Filament\Auth\Notifications\VerifyEmail as FilamentVerifyEmail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ErrorRef::class);
        // #158: our AR/EN "confirm your email" mail replaces Filament's wherever Filament sends one.
        $this->app->bind(FilamentVerifyEmail::class, VerifyEmail::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // #158 / #73: every completed password reset is audit-logged (no token, no password).
        Event::listen(PasswordReset::class, function (PasswordReset $e): void {
            AuditLog::create(['user_id' => $e->user->getAuthIdentifier(), 'action' => 'auth.password_reset',
                'subject_type' => $e->user::class, 'subject_id' => $e->user->getAuthIdentifier(), 'meta' => [], 'ip' => request()?->ip()]);
        });

        // #158: a verified self-signup gets its agency (14-day Pro trial, D-61) before the redirect into /app.
        Event::listen(Verified::class, function (Verified $e): void {
            if ($e->user instanceof User) {
                Signup::completeVerified($e->user);
            }
        });
    }
}
