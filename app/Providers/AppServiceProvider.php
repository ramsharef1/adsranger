<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Support\ErrorRef;
use Illuminate\Auth\Events\PasswordReset;
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
    }
}
