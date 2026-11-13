<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\VerifyEmail;
use App\Support\ErrorRef;
use App\Support\Mail\EmailSuppressionGuard;
use App\Support\Ops\JobRuns;
use App\Support\ReadOnlyGuard;
use App\Support\ShellNavigationManager;
use App\Support\Signup;
use Filament\Auth\Notifications\VerifyEmail as FilamentVerifyEmail;
use Filament\Navigation\NavigationManager;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
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
        // PX-01 (D-302): the new shell filters the sidebar by area; off = Filament's own navigation, unchanged.
        $this->app->scoped(NavigationManager::class, fn () => new ShellNavigationManager);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // EG-07: record every queued job's last run for the /admin Operations board.
        Queue::before(function (JobProcessing $e): void {
            app()->instance('ops.job_started', microtime(true));
            JobRuns::started(class_basename($e->job->resolveName()));
        });
        Queue::after(function (JobProcessed $e): void {
            JobRuns::finished(class_basename($e->job->resolveName()), microtime(true) - (float) (app()->bound('ops.job_started') ? app('ops.job_started') : microtime(true)));
        });
        Queue::failing(function (JobFailed $e): void {
            JobRuns::finished(class_basename($e->job->resolveName()), microtime(true) - (float) (app()->bound('ops.job_started') ? app('ops.job_started') : microtime(true)), $e->exception->getMessage());
        });
        Event::listen(MessageSending::class, [EmailSuppressionGuard::class, 'handle']);
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

        // D-61: read-only (trial ended) workspaces refuse every Livewire write call with the EX-50 style notice.
        ReadOnlyGuard::register();
    }
}
