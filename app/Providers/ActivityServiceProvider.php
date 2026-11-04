<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Records last sign-in time for the admin Users page. */
class ActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $e) {
            $e->user->forceFill(['last_login_at' => now()])->saveQuietly();
        });
    }
}
