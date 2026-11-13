<?php

namespace App\Support\Ops;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * EG-07: one row per scheduled task or queued job with its last start, end, time taken and result, so /admin shows
 * "did it run" and not only "something broke". Queued jobs are recorded from the queue events (AppServiceProvider);
 * in-process tasks call track().
 */
class JobRuns
{
    public static function started(string $key): void
    {
        self::write($key, ['last_started_at' => now()]);
    }

    public static function finished(string $key, float $seconds, ?string $error = null): void
    {
        $row = ['last_finished_at' => now(), 'last_seconds' => round($seconds, 2), 'last_status' => $error ? 'failed' : 'ok', 'runs' => DB::raw('runs + 1')];
        if ($error !== null) {
            $row['last_error'] = mb_substr($error, 0, 300);
            $row['last_failed_at'] = now();
        }
        self::write($key, $row);
    }

    /** Runs an in-process task and records it. */
    public static function track(string $key, callable $task): mixed
    {
        $t = microtime(true);
        self::started($key);
        try {
            $result = $task();
            self::finished($key, microtime(true) - $t);

            return $result;
        } catch (Throwable $e) {
            self::finished($key, microtime(true) - $t, $e->getMessage());
            throw $e;
        }
    }

    protected static function write(string $key, array $values): void
    {
        try {
            $key = mb_substr($key, 0, 120);
            if (! DB::table('job_runs')->where('job', $key)->exists()) {
                DB::table('job_runs')->insertOrIgnore(['job' => $key, 'runs' => 0]);
            }
            DB::table('job_runs')->where('job', $key)->update($values);
        } catch (Throwable) {
            // Recording a run must never break the run itself.
        }
    }

    /**
     * The scheduled tasks with their last run and whether one was missed.
     *
     * @return list<array{name: string, cron: string, key: string, last: ?Carbon, seconds: ?float, status: ?string, error: ?string, missed: bool, runs: int}>
     */
    public static function board(): array
    {
        $rows = DB::table('job_runs')->get()->keyBy('job');
        $map = (array) config('ops.schedule');
        $out = [];
        $trackingSince = DB::table('job_runs')->min('last_started_at');
        foreach (app(Schedule::class)->events() as $event) {
            $name = (string) ($event->description ?? '');
            if ($name === '' || ! isset($map[$name])) {
                continue;
            }
            $key = $map[$name];
            $r = $rows[$key] ?? null;
            $last = $r?->last_finished_at ? Carbon::parse($r->last_finished_at) : null;
            $missed = false;
            try {
                // The run before the latest one is the newest run that must have finished by now (queue + run time).
                $due = Carbon::instance((new CronExpression($event->expression))->getPreviousRunDate(now(), 1));
                $missed = $last === null ? ($trackingSince !== null && Carbon::parse($trackingSince)->lt($due)) : $last->lt($due);
            } catch (Throwable) {
            }
            $out[] = ['name' => $name, 'cron' => $event->expression, 'key' => $key, 'last' => $last, 'seconds' => $r?->last_seconds !== null ? (float) $r->last_seconds : null,
                'status' => $r?->last_status, 'error' => $r?->last_error, 'missed' => $missed, 'runs' => (int) ($r?->runs ?? 0)];
        }

        return $out;
    }

    /** Names of tasks that missed a run or failed last time (for the health check). */
    public static function problems(): array
    {
        return collect(self::board())->filter(fn ($r) => $r['missed'] || $r['status'] === 'failed')->pluck('name')->values()->all();
    }
}
