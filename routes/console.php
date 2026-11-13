<?php

use App\Jobs\CheckLandingPages;
use App\Jobs\DailySync;
use App\Jobs\QueueScheduledReports;
use App\Jobs\RunAlerts;
use App\Jobs\RunNightShift;
use App\Jobs\RunTrialLifecycle;
use App\Jobs\SnapshotHealth;
use App\Jobs\VerifyOwnLinks;
use App\Support\Fx;
use App\Support\Ops\Heartbeat;
use App\Support\Ops\JobRuns;
use App\Support\Ops\LogRoll;
use App\Support\Ops\Prune;
use App\Support\PlatformHealth;
use Illuminate\Support\Facades\Schedule;

// proc_open is disabled on this server, so every task runs in-process (job / call), never ->command().
Schedule::job(new DailySync)->dailyAt('02:30')->name('daily-sync')->withoutOverlapping();
Schedule::job(new RunAlerts)->everyFifteenMinutes()->name('alerts')->withoutOverlapping();
// A-3 (R2-69): server log, failed jobs, queue, syncs, uptime → push to super admins. Runs in-process: the server's PHP has
// proc_open disabled, so Schedule::command() (which starts a child process) fails there; use job / call only.
Schedule::call(fn () => JobRuns::track('platform-health', fn () => PlatformHealth::check()))->everyTenMinutes()->name('platform-health')->withoutOverlapping();
Schedule::job(new SnapshotHealth)->dailyAt('04:00')->name('health-snapshot')->withoutOverlapping();
Schedule::job(new CheckLandingPages)->dailyAt('05:10')->name('landing-pages')->withoutOverlapping();
Schedule::call(fn () => JobRuns::track('fx-pull', fn () => Fx::pull()))->dailyAt('01:15')->name('fx-pull')->withoutOverlapping();
// #106 scheduled email reports: daily, sends whatever is due on each workspace's date (off with REPORTS_EMAIL_ENABLED=false).
Schedule::job(new QueueScheduledReports)->dailyAt(config('reports.send_at_utc', '05:20'))->name('scheduled-reports')->withoutOverlapping();
// D-61 trial lifecycle (self-signup agencies only): ending / ended / deletion mails, read-only → closed → archived.
Schedule::job(new RunTrialLifecycle)->dailyAt(config('trial.run_at_utc', '03:10'))->name('trial-lifecycle')->withoutOverlapping();
// AI Workspace night shift v0 (M1): each workspace at its own hour, once a day; does nothing while no workspace has it on.
Schedule::job(new RunNightShift)->hourlyAt(5)->name('ai-night-shift')->withoutOverlapping();
// BX-07: recheck every known link to us once a month (1st, 06:20 UTC).
Schedule::job(new VerifyOwnLinks)->monthlyOn(1, '06:20')->name('links-verify')->withoutOverlapping();
// R1 ops (D-289): EG-03 heartbeat every 5 min (watched from outside via /up/heartbeat) · EG-01 nightly clean-up of ops rows ·
// EG-02 log roll-over (size or Sunday).
Schedule::call(fn () => JobRuns::track('ops-heartbeat', fn () => Heartbeat::beat()))->everyFiveMinutes()->name('ops-heartbeat');
Schedule::call(fn () => JobRuns::track('ops-prune', fn () => Prune::run()))->dailyAt('03:40')->name('ops-prune')->withoutOverlapping();
Schedule::call(fn () => JobRuns::track('ops-log-roll', fn () => LogRoll::run()))->dailyAt('03:50')->name('ops-log-roll')->withoutOverlapping();
