<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Devices;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Records last sign-in time for the admin Users page, and the device check (A-17). */
class ActivityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, function (Login $e) {
            $e->user->forceFill(['last_login_at' => now()])->saveQuietly();
            if ($e->user instanceof User) {
                Devices::onLogin($e->user, request()?->userAgent());
            }
        });
    }
}
