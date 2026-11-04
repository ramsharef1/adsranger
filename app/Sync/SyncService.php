<?php

namespace App\Sync;

use App\Connectors\ConnectorRegistry;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdSet;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Models\SyncRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Pulls one ad account from its network and stores structure + daily metrics. */
class SyncService
{
    public const BACKFILL_MONTHS = 13;

    public static function sync(AdAccount $account, string $from, string $to, string $kind = 'daily'): SyncRun
    {
        $run = SyncRun::create(['agency_id' => $account->agency_id, 'ad_account_id' => $account->id, 'kind' => $kind,
            'from' => $from, 'to' => $to, 'status' => 'running', 'started_at' => now()]);
        $account->update(['sync_status' => 'running']);
        try {
            $net = $account->network;
            $connector = ConnectorRegistry::for($net);
            $payload = $connector->pull($account, $from, $to);
            $rows = static::ingest($account, $payload, $from, $to);
            $run->update(['status' => 'ok', 'rows' => $rows, 'finished_at' => now()]);
            $account->update(['sync_status' => 'ok', 'sync_error' => null, 'sync_failures' => 0, 'last_synced_at' => now()]);
            $account->connection?->update(['last_synced_at' => now(), 'status' => 'active', 'last_error' => null]);
        } catch (\Throwable $e) {
            $msg = Str::limit($e->getMessage(), 500);
            $run->update(['status' => 'error', 'error' => $msg, 'finished_at' => now()]);
            $account->update(['sync_status' => 'error', 'sync_error' => $msg, 'sync_failures' => $account->sync_failures + 1]);
            if (preg_match('/token|session|OAuth|expired|revoked|invalid_grant|permission/i', $msg)) {
                $account->connection?->update(['status' => 'error', 'last_error' => $msg]);
            }
        }

        return $run;
    }

    /** Store a normalised payload. Re-pulling a date range replaces that range. Returns metric rows written. */
    public static function ingest(AdAccount $account, array $payload, string $from, string $to): int
    {
        return DB::transaction(function () use ($account, $payload, $from, $to) {
            $base = ['agency_id' => $account->agency_id, 'ad_account_id' => $account->id];
            $now = now();
            $ts = fn ($v) => $v ? Carbon::parse($v) : null;

            $campIds = [];
            foreach ($payload['campaigns'] ?? [] as $c) {
                $m = Campaign::updateOrCreate($base + ['external_id' => (string) $c['external_id']], [
                    'name' => $c['name'] ?? null, 'status' => $c['status'] ?? null, 'objective' => $c['objective'] ?? null,
                    'channel' => $c['channel'] ?? null, 'daily_budget' => $c['daily_budget'] ?? null, 'lifetime_budget' => $c['lifetime_budget'] ?? null,
                    'start_time' => $ts($c['start_time'] ?? null), 'end_time' => $ts($c['end_time'] ?? null)]);
                $campIds[(string) $c['external_id']] = $m->id;
            }
            $campId = function (?string $ext) use (&$campIds, $account) {
                if (! $ext) {
                    return null;
                }

                return $campIds[$ext] ??= Campaign::firstOrCreate(['agency_id' => $account->agency_id, 'ad_account_id' => $account->id, 'external_id' => $ext])->id;
            };

            $setIds = [];
            foreach ($payload['ad_sets'] ?? [] as $s) {
                $m = AdSet::updateOrCreate($base + ['external_id' => (string) $s['external_id']], [
                    'campaign_id' => $campId($s['campaign'] ?? null), 'name' => $s['name'] ?? null, 'status' => $s['status'] ?? null,
                    'optimization_goal' => $s['optimization_goal'] ?? null, 'custom_event' => $s['custom_event'] ?? null,
                    'daily_budget' => $s['daily_budget'] ?? null, 'lifetime_budget' => $s['lifetime_budget'] ?? null,
                    'start_time' => $ts($s['start_time'] ?? null), 'end_time' => $ts($s['end_time'] ?? null)]);
                $setIds[(string) $s['external_id']] = $m->id;
            }
            $adIds = [];
            foreach ($payload['ads'] ?? [] as $a) {
                $m = Ad::updateOrCreate($base + ['external_id' => (string) $a['external_id']], [
                    'campaign_id' => $campId($a['campaign'] ?? null), 'ad_set_id' => $setIds[$a['ad_set'] ?? ''] ?? null,
                    'name' => $a['name'] ?? null, 'status' => $a['status'] ?? null, 'thumbnail_url' => $a['thumbnail_url'] ?? null,
                    'review_feedback' => $a['review_feedback'] ?? null]);
                $adIds[(string) $a['external_id']] = $m->id;
            }

            DailyMetric::where('ad_account_id', $account->id)->where('source', 'api')->whereBetween('date', [$from, $to])->delete();

            $cols = ['spend', 'impressions', 'reach', 'clicks', 'link_clicks', 'results', 'calls', 'calls_confirmed', 'calls_20s', 'messages',
                'leads', 'purchases', 'thruplays', 'landing_views', 'add_to_cart', 'checkouts', 'revenue'];
            $names = Campaign::where('ad_account_id', $account->id)->pluck('name', 'external_id');
            $batch = [];
            $count = 0;
            foreach ($payload['rows'] ?? [] as $r) {
                $row = $base + [
                    'client_id' => $account->client_id, 'network' => $account->network->key, 'level' => $r['level'] ?? 'campaign',
                    'date' => $r['date'], 'campaign_id' => $r['campaign'] ?? null, 'campaign_name' => Str::limit((string) ($names[$r['campaign'] ?? ''] ?? ''), 250, '') ?: null,
                    'campaign_ref' => $campId($r['campaign'] ?? null), 'ad_set_ref' => $setIds[$r['ad_set'] ?? ''] ?? null, 'ad_ref' => $adIds[$r['ad'] ?? ''] ?? null,
                    'result_type' => $r['result_type'] ?? null, 'roas_eligible' => (bool) ($r['roas_eligible'] ?? false),
                    'breakdown' => isset($r['breakdown']) ? json_encode($r['breakdown']) : null,
                    'currency' => $account->currency, 'source' => 'api', 'created_at' => $now, 'updated_at' => $now,
                ];
                foreach ($cols as $c) {
                    $row[$c] = $r[$c] ?? 0;
                }
                $batch[] = $row;
                $count++;
                if (count($batch) >= 500) {
                    DailyMetric::insert($batch);
                    $batch = [];
                }
            }
            if ($batch) {
                DailyMetric::insert($batch);
            }

            return $count;
        });
    }

    /** Month-sized chunks covering the last 13 months, newest first. */
    public static function backfillRanges(?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $start = $today->copy()->subMonthsNoOverflow(self::BACKFILL_MONTHS)->startOfMonth();
        $out = [];
        for ($m = $today->copy()->startOfMonth(); $m->gte($start); $m->subMonthNoOverflow()) {
            $to = $m->copy()->endOfMonth()->min($today);
            $out[] = [$m->toDateString(), $to->toDateString()];
        }

        return $out;
    }

    public static function queueBackfill(AdAccount $account): void
    {
        foreach (static::backfillRanges() as [$from, $to]) {
            \App\Jobs\SyncAdAccount::dispatch($account->id, $from, $to, 'backfill');
        }
        $account->update(['backfilled_at' => now()]);
        AuditLog::record('sync.backfill_queued', $account->agency, ['ad_account_id' => $account->id, 'months' => self::BACKFILL_MONTHS]);
    }
}
