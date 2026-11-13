<?php

namespace App\Support;

use App\Models\AdNetwork;
use Illuminate\Support\Facades\DB;

/**
 * A-11 (FR-031): for each ad network, what has really been proven: keys saved, keys tested, a real (non-demo) account
 * connected, and a real sync that brought rows back. Only the last one counts as "Verified". Nothing here is typed by
 * hand, so a network can't look live because someone said so.
 */
class LiveVerification
{
    public const VERIFIED_DAYS = 30;

    /** @return list<array{key: string, name: string, stage: int, enabled: bool, keys: bool, test: ?string, tested_at: ?string, connections: int, last_ok_sync: ?string, last_error: ?string, level: string}> */
    public static function board(): array
    {
        $since = now()->subDays(self::VERIFIED_DAYS);
        $realConn = fn ($q) => $q->where('connections.environment', '!=', DemoData::TAG)->where(fn ($w) => $w->whereNull('connections.external_user_id')->orWhere('connections.external_user_id', '!=', DemoData::TAG));

        $connections = DB::table('connections')->where('status', 'active')->where($realConn)
            ->selectRaw('ad_network_id, count(*) as n')->groupBy('ad_network_id')->pluck('n', 'ad_network_id');

        $syncs = DB::table('sync_runs')
            ->join('ad_accounts', 'ad_accounts.id', '=', 'sync_runs.ad_account_id')
            ->join('connections', 'connections.id', '=', 'ad_accounts.connection_id')
            ->where($realConn)
            ->selectRaw("ad_accounts.ad_network_id as nid,
                max(case when sync_runs.status = 'ok' and sync_runs.rows > 0 then sync_runs.finished_at end) as last_ok,
                max(case when sync_runs.status = 'error' then sync_runs.finished_at end) as last_err")
            ->groupBy('ad_accounts.ad_network_id')->get()->keyBy('nid');

        $rows = [];
        foreach (AdNetwork::orderBy('stage')->orderBy('name')->get() as $n) {
            $keys = $n->credentials() !== [];
            $test = $n->last_test_result ? (str_contains($n->last_test_result, 'FAILED') ? 'failed' : (str_contains($n->last_test_result, 'OK') ? 'ok' : 'unclear')) : null;
            $s = $syncs->get($n->id);
            $lastOk = $s?->last_ok;
            $level = match (true) {
                $lastOk !== null && $lastOk >= $since->toDateTimeString() => 'verified',
                $lastOk !== null => 'stale',
                ($connections[$n->id] ?? 0) > 0 => 'connected',
                $test === 'ok' => 'keys_tested',
                $keys => 'keys_set',
                default => 'not_set',
            };
            $rows[] = [
                'key' => $n->key,
                'name' => $n->name,
                'stage' => (int) $n->stage,
                'enabled' => (bool) $n->enabled,
                'keys' => $keys,
                'test' => $test,
                'tested_at' => $n->last_tested_at?->toDateString(),
                'connections' => (int) ($connections[$n->id] ?? 0),
                'last_ok_sync' => $lastOk ? substr((string) $lastOk, 0, 10) : null,
                'last_error' => $s?->last_err ? substr((string) $s->last_err, 0, 10) : null,
                'level' => $level,
            ];
        }

        return $rows;
    }
}
