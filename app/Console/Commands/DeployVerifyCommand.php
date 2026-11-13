<?php

namespace App\Console\Commands;

use App\Support\Ops\Heartbeat;
use App\Support\Ops\LaunchReadiness;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * EG-05: the checks every deploy script used to write by hand, in one command. Read-only. Exit 1 if any check failed.
 *   php artisan deploy:verify --since="2026-11-13 17:00:00"
 */
class DeployVerifyCommand extends Command
{
    protected $signature = 'deploy:verify {--since= : Count server errors from this UTC time (Y-m-d H:i:s)} {--no-http : Skip the URL checks}';

    protected $description = 'Read-only checks after a deploy: URLs, 301s, migrations, schedule, modules, disk, errors since the deploy';

    private int $failed = 0;

    public function handle(): int
    {
        if (! $this->option('no-http')) {
            $base = rtrim((string) config('app.url'), '/');
            foreach (['/', '/en/pricing', '/app/login', '/admin/login', '/up', '/sitemap.xml'] as $path) {
                $this->url($base.$path, [200]);
            }
            foreach ((array) config('redirects.renames') as $old => $new) {
                $this->url($base.$old, [301], $new);
            }
        }
        $pending = LaunchReadiness::pendingMigrations();
        $this->check('pending migrations: '.($pending === [] ? '0' : implode(', ', $pending)), $pending === []);
        $this->line('scheduled tasks: '.count(app(Schedule::class)->events()));
        $this->line('modules on: '.(collect((array) config('modules.enabled'))->filter()->keys()->implode(', ') ?: 'none'));
        $this->line('queue lanes: '.(config('ops.lanes_enabled') ? 'on (high, default, slow)' : 'off').' · cache: '.config('cache.default').' · sessions: '.config('session.driver'));
        $hb = Heartbeat::status();
        $this->line('heartbeat: '.($hb['last'] ? $hb['age_minutes'].' min ago' : 'none yet'));
        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        if ($free && $total) {
            $pct = (int) round($free * 100 / $total);
            $this->check("disk free: {$pct}%", $pct >= 15);
        }
        if ($since = $this->option('since')) {
            $n = LaunchReadiness::errorsSince(Carbon::parse($since));
            $this->check("server errors since {$since} UTC: {$n}", $n === 0);
        }
        $this->line($this->failed ? "FAILED: {$this->failed} check(s)" : 'ALL OK');

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function url(string $url, array $want, ?string $location = null): void
    {
        try {
            $r = Http::timeout(15)->withoutRedirecting()->get($url);
            $status = $r->status();
            $loc = (string) $r->header('Location');
        } catch (Throwable) {
            $status = 0;
            $loc = '';
        }
        $ok = in_array($status, $want, true) && ($location === null || str_ends_with($loc, $location));
        $this->check("{$status} {$url}".($location ? " → {$loc}" : ''), $ok);
    }

    private function check(string $text, bool $ok): void
    {
        $this->line(($ok ? 'ok   ' : 'FAIL ').$text);
        $this->failed += $ok ? 0 : 1;
    }
}
