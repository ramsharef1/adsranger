<?php

use App\Jobs\DailySync;
use App\Jobs\RunAlerts;
use App\Support\Fx;
use Illuminate\Support\Facades\Schedule;

// proc_open is disabled on this server, so every task runs in-process (job / call), never ->command().
Schedule::job(new DailySync)->dailyAt('02:30')->name('daily-sync')->withoutOverlapping();
Schedule::job(new RunAlerts)->everyFifteenMinutes()->name('alerts')->withoutOverlapping();
Schedule::call(fn () => Fx::pull())->dailyAt('01:15')->name('fx-pull')->withoutOverlapping();
