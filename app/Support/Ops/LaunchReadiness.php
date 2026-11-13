<?php

namespace App\Support\Ops;

use App\Support\RestoreTest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * EG-13: the launch-scorecard rows the code can check by itself (A-8). Each row is green or red with a reason; the
 * hand-ticked rows stay on /admin › Costs & launch.
 */
class LaunchReadiness
{
    /** @return list<array{key: string, label: string, ok: bool, detail: string}> */
    public static function checks(): array
    {
        $rows = [];
        $add = function (string $key, string $label, bool $ok, string $detail = '') use (&$rows): void {
            $rows[] = compact('key', 'label', 'ok', 'detail');
        };

        $hb = Heartbeat::status();
        $add('heartbeat', __('Scheduler heartbeat'), $hb['ok'], $hb['last'] ? __(':n minutes ago', ['n' => $hb['age_minutes']]) : __('never'));

        $restore = RestoreTest::problem();
        $add('restore', __('Monthly restore test'), $restore === null, $restore ?? __('passed, recent'));

        $missed = JobRuns::problems();
        $add('jobs', __('Scheduled tasks ran on time'), $missed === [], $missed === [] ? '' : implode(', ', $missed));

        $queueOk = true;
        if (Schema::hasTable('jobs')) {
            $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
            $queueOk = $oldest === null || (int) $oldest >= now()->subMinutes(15)->getTimestamp();
        }
        $add('queue', __('Queue is moving'), $queueOk);

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count() : 0;
        $add('failed', __('No failed jobs in 24 hours'), $failed === 0, $failed ? (string) $failed : '');

        $errors = self::errorsSince(now()->subDay());
        $add('errors', __('No server errors in 24 hours'), $errors === 0, $errors ? (string) $errors : '');

        if (ConfigDrift::applies()) {
            $drift = ConfigDrift::problems();
            $add('config', __('Production settings'), $drift === [], implode(' ', $drift));
            $certs = CertExpiry::problems();
            $add('certs', __('HTTPS certificates valid 14+ days'), $certs === [], implode(' ', $certs));
        }

        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        $pct = $free && $total ? (int) round($free * 100 / $total) : null;
        $add('disk', __('Disk at least 15% free'), $pct === null || $pct >= 15, $pct !== null ? $pct.'%' : '');

        $pending = self::pendingMigrations();
        $add('migrations', __('No pending migrations'), $pending === [], implode(', ', $pending));

        if (config('health.check_urls', true) && app()->environment('production')) {
            try {
                $status = Http::timeout(10)->get(rtrim((string) config('app.url'), '/').'/sitemap.xml')->status();
            } catch (Throwable) {
                $status = 0;
            }
            $add('sitemap', __('Sitemap answers 200'), $status === 200, (string) $status);
        }

        return $rows;
    }

    public static function errorsSince(\DateTimeInterface $since): int
    {
        $log = storage_path('logs/laravel.log');
        if (! is_file($log) || ! ($fh = fopen($log, 'rb'))) {
            return 0;
        }
        $from = $since->format('Y-m-d H:i:s');
        $n = 0;
        while (($line = fgets($fh)) !== false) {
            if (preg_match('/^\[(\d{4}-\d\d-\d\d[ T]\d\d:\d\d:\d\d)[^\]]*\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line, $m) && str_replace('T', ' ', $m[1]) >= $from) {
                $n++;
            }
        }
        fclose($fh);

        return $n;
    }

    /** @return list<string> */
    public static function pendingMigrations(): array
    {
        try {
            $migrator = app('migrator');
            if (! $migrator->repositoryExists()) {
                return ['(no migrations table)'];
            }
            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));

            return array_values(array_diff($files, $migrator->getRepository()->getRan()));
        } catch (Throwable) {
            return [];
        }
    }
}
