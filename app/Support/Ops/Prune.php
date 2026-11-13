<?php

namespace App\Support\Ops;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EG-01: nightly clean-up of ops rows only: expired sessions and cache rows, failed jobs, read notifications, AI night-run
 * logs and 404 paths nobody hits any more. Customer data is never touched here (workspace purge stays manual, D-164 / D-166).
 */
class Prune
{
    public const LAST_KEY = 'ops.prune.last';

    /** @return array<string, int> table → rows deleted */
    public static function run(): array
    {
        $days = (array) config('ops.retention_days');
        $out = [];
        $del = function (string $label, string $table, callable $where) use (&$out): void {
            if (! Schema::hasTable($table)) {
                return;
            }
            $q = DB::table($table);
            $where($q);
            $out[$label] = $q->delete();
        };

        if (config('session.driver') === 'database') {
            $del('sessions', 'sessions', fn ($q) => $q->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp()));
        }
        $del('cache', 'cache', fn ($q) => $q->where('expiration', '<', now()->getTimestamp()));
        $del('cache_locks', 'cache_locks', fn ($q) => $q->where('expiration', '<', now()->getTimestamp()));
        $del('failed_jobs', 'failed_jobs', fn ($q) => $q->where('failed_at', '<', now()->subDays((int) ($days['failed_jobs'] ?? 30))));
        $del('notifications', 'notifications', fn ($q) => $q->whereNotNull('read_at')->where('read_at', '<', now()->subDays((int) ($days['notifications_read'] ?? 90))));
        $del('ai_night_runs', 'ai_night_runs', fn ($q) => $q->where('created_at', '<', now()->subDays((int) ($days['ai_night_runs'] ?? 90))));
        $del('ai_tool_calls', 'ai_tool_calls', fn ($q) => $q->where('created_at', '<', now()->subDays((int) ($days['ai_tool_calls'] ?? 30))));
        $del('missing_pages', 'missing_pages', fn ($q) => $q->where('ignored', false)->where('last_seen_at', '<', now()->subDays((int) ($days['missing_pages'] ?? 180))));

        PlatformSetting::put(self::LAST_KEY, ['at' => now()->toIso8601String(), 'deleted' => $out]);

        return $out;
    }

    /** @return list<array{what: string, keep: string}> the retention table shown in /admin */
    public static function table(): array
    {
        $d = (array) config('ops.retention_days');

        return [
            ['what' => __('Expired sessions and cache rows'), 'keep' => __('until they expire')],
            ['what' => __('Failed queue jobs'), 'keep' => __(':n days', ['n' => $d['failed_jobs'] ?? 30])],
            ['what' => __('Read notifications'), 'keep' => __(':n days', ['n' => $d['notifications_read'] ?? 90])],
            ['what' => __('AI night shift run logs'), 'keep' => __(':n days', ['n' => $d['ai_night_runs'] ?? 90])],
            ['what' => __('AI connector tool calls'), 'keep' => __(':n days', ['n' => $d['ai_tool_calls'] ?? 30])],
            ['what' => __('404 paths no one visits'), 'keep' => __(':n days after the last visit', ['n' => $d['missing_pages'] ?? 180])],
            ['what' => __('Server log files'), 'keep' => __(':n weekly files', ['n' => config('ops.log_keep', 8)])],
            ['what' => __('Customer data and the audit log'), 'keep' => __('not pruned here (workspace purge stays manual)')],
        ];
    }
}
