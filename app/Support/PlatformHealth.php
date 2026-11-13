<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * A-3 (R2-69): every 10 minutes, look for trouble and tell the super admins by phone push (no email needed, D-266):
 * new ERROR lines in laravel.log, new failed queue jobs, a queue that stopped moving, ad-network syncs that failed,
 * and the public site / app login not answering 200. One push per problem kind per hour; every alert is audit-logged and
 * the last result is shown in /admin › Live verification.
 */
class PlatformHealth
{
    public const LAST_KEY = 'health.last';

    public const OFFSET_KEY = 'health.log_offset';

    /** @return list<array{kind: string, text: string}> problems found in this run */
    public static function check(): array
    {
        $problems = [];

        // 1. New ERROR / CRITICAL lines in laravel.log since the last run.
        $log = storage_path('logs/laravel.log');
        if (is_file($log)) {
            $size = (int) filesize($log);
            $stored = PlatformSetting::get(self::OFFSET_KEY);
            $offset = $stored === null ? $size : (int) $stored; // first run starts from now, old errors are not re-sent
            if ($offset > $size) {
                $offset = 0; // log was rotated
            }
            $errors = 0;
            $first = null;
            if ($size > $offset && ($fh = fopen($log, 'r'))) {
                fseek($fh, $offset);
                while (($line = fgets($fh)) !== false) {
                    if (preg_match('/^\[\d{4}-\d\d-\d\d[^\]]*\] \w+\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line)) {
                        $errors++;
                        $first ??= mb_substr(trim(preg_replace('/^\[[^\]]+\] /', '', $line) ?? ''), 0, 140);
                    }
                }
                fclose($fh);
            }
            PlatformSetting::put(self::OFFSET_KEY, $size);
            if ($errors > 0) {
                $problems[] = ['kind' => 'log', 'text' => trans_choice('{1} 1 new error in the server log: :first|[2,*] :n new errors in the server log. First: :first', $errors, ['n' => $errors, 'first' => $first])];
            }
        }

        // 2. Failed queue jobs in the last 15 minutes.
        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes(15))->count();
            if ($failed > 0) {
                $problems[] = ['kind' => 'failed_jobs', 'text' => trans_choice('{1} 1 queued job failed in the last 15 minutes.|[2,*] :n queued jobs failed in the last 15 minutes.', $failed, ['n' => $failed])];
            }
        }

        // 3. Queue not moving: a job waiting for more than 15 minutes.
        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            $oldest = DB::table('jobs')->whereNull('reserved_at')->min('available_at');
            if ($oldest !== null && (int) $oldest < now()->subMinutes(15)->getTimestamp()) {
                $problems[] = ['kind' => 'queue', 'text' => __('The queue has a job waiting for more than 15 minutes. The worker may be down.')];
            }
        }

        // 4. Ad-network syncs that failed in the last hour.
        if (Schema::hasTable('sync_runs')) {
            $syncErrors = DB::table('sync_runs')->where('status', 'error')->where('updated_at', '>=', now()->subHour())->count();
            if ($syncErrors > 0) {
                $problems[] = ['kind' => 'sync', 'text' => trans_choice('{1} 1 ad-account sync failed in the last hour.|[2,*] :n ad-account syncs failed in the last hour.', $syncErrors, ['n' => $syncErrors])];
            }
        }

        // 5. Public site and app login answer 200.
        if (config('health.check_urls', true)) {
            $base = rtrim((string) config('app.url'), '/');
            foreach (['/', '/app/login'] as $path) {
                try {
                    $status = Http::timeout(10)->withoutRedirecting()->get($base.$path)->status();
                } catch (\Throwable) {
                    $status = 0;
                }
                if ($status !== 200) {
                    $problems[] = ['kind' => 'uptime'.$path, 'text' => __(':path answered :status instead of 200.', ['path' => $path, 'status' => $status ?: __('nothing')])];
                }
            }
        }

        // 6. A-5: the monthly restore test passed and is recent.
        if (config('health.restore_test', true) && ($restore = RestoreTest::problem()) !== null) {
            $problems[] = ['kind' => 'restore', 'text' => $restore];
        }

        PlatformSetting::put(self::LAST_KEY, ['at' => now()->toIso8601String(), 'problems' => $problems]);

        $sent = [];
        foreach ($problems as $p) {
            if (Cache::add('health.alerted.'.$p['kind'], 1, now()->addHour())) {
                AuditLog::record('platform.health_alert', null, $p);
                $sent[] = $p['text'];
            }
        }
        if ($sent !== []) {
            static::push(implode("\n", $sent));
        }

        return $problems;
    }

    protected static function push(string $body): void
    {
        if (! config('push.enabled')) {
            return;
        }
        $admins = User::where('role', 'super_admin')->pluck('id');
        foreach (PushSubscription::whereIn('user_id', $admins)->get() as $sub) {
            try {
                WebPush::send($sub, ['title' => __('AdsRanger server needs a look'), 'body' => mb_substr($body, 0, 400), 'url' => '/admin/live-verification']);
            } catch (\Throwable) {
                // A failed push must not stop the check; the alert is still in the audit log and on the board.
            }
        }
    }
}
