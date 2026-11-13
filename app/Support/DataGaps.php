<?php

namespace App\Support;

use App\Models\AdAccount;
use App\Models\Client;
use App\Models\DailyMetric;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * FS-24 / XS-44 (R4, D-303): days without data are shown as gaps with the reason, never as zero. A day with rows (even
 * 0 spend) is data; a day with no rows is a gap: not connected yet, sync failed, or simply nothing came back.
 */
class DataGaps
{
    public const REASONS = ['not_connected', 'sync_failed', 'no_data'];

    /** @return array<string, string> */
    public static function reasonLabels(): array
    {
        return ['not_connected' => __('No account or upload yet'), 'sync_failed' => __('Sync failed'), 'no_data' => __('Nothing came back from the platform')];
    }

    /**
     * @return array{days: array<string, array{spend: ?float, results: ?float, gap: ?string}>, gaps: list<array{from: string, to: string, days: int, reason: string}>}
     */
    public static function series(Client $c, string $from, string $to): array
    {
        $daily = MetricsQuery::daily($c, $from, $to);
        $accounts = AdAccount::where('client_id', $c->id)->get(['id', 'created_at']);
        $firstImport = DailyMetric::where('client_id', $c->id)->where('source', 'import')->min('date');
        $since = collect([$accounts->min('created_at') ? Carbon::parse($accounts->min('created_at'))->toDateString() : null, $firstImport])->filter()->min();
        // Days covered by a failed sync with no successful run for the same account and day.
        $failed = [];
        if ($accounts->isNotEmpty()) {
            $runs = DB::table('sync_runs')->whereIn('ad_account_id', $accounts->pluck('id'))->where('to', '>=', $from)->where('from', '<=', $to)
                ->whereIn('status', ['ok', 'error'])->get(['ad_account_id', 'from', 'to', 'status']);
            $ok = [];
            foreach ($runs->where('status', 'ok') as $r) {
                foreach (CarbonPeriod::create($r->from, $r->to) as $d) {
                    $ok[$d->toDateString()] = true;
                }
            }
            foreach ($runs->where('status', 'error') as $r) {
                foreach (CarbonPeriod::create($r->from, $r->to) as $d) {
                    if (! isset($ok[$d->toDateString()])) {
                        $failed[$d->toDateString()] = true;
                    }
                }
            }
        }

        $days = [];
        foreach (CarbonPeriod::create($from, $to) as $d) {
            $k = $d->toDateString();
            if (isset($daily[$k])) {
                $days[$k] = ['spend' => (float) $daily[$k]['spend'], 'results' => (float) $daily[$k]['main_results'], 'gap' => null];

                continue;
            }
            $reason = (! $since || $k < $since) ? 'not_connected' : (isset($failed[$k]) ? 'sync_failed' : 'no_data');
            $days[$k] = ['spend' => null, 'results' => null, 'gap' => $reason];
        }

        // Runs of consecutive gap days with the same reason.
        $gaps = [];
        foreach ($days as $k => $v) {
            if ($v['gap'] === null) {
                continue;
            }
            $last = array_key_last($gaps);
            if ($last !== null && $gaps[$last]['reason'] === $v['gap'] && Carbon::parse($gaps[$last]['to'])->addDay()->toDateString() === $k) {
                $gaps[$last]['to'] = $k;
                $gaps[$last]['days']++;
            } else {
                $gaps[] = ['from' => $k, 'to' => $k, 'days' => 1, 'reason' => $v['gap']];
            }
        }

        return ['days' => $days, 'gaps' => $gaps];
    }

    /**
     * Gap runs for client-facing reports (/r and the monthly PDF): only days after data started (sync failed or nothing
     * came back), never future days, at most 6 runs. Empty when the client has no data in the period at all.
     *
     * @return list<array{from: string, to: string, days: int, reason: string}>
     */
    public static function forReport(Client $c, string $from, string $to): array
    {
        $to = min($to, now($c->agency?->timezone ?: 'UTC')->subDay()->toDateString());
        if ($to < $from) {
            return [];
        }
        $s = static::series($c, $from, $to);
        if (collect($s['days'])->whereNull('gap')->isEmpty()) {
            return [];
        }

        return array_slice(array_values(array_filter($s['gaps'], fn ($g) => $g['reason'] !== 'not_connected')), 0, 6);
    }
}
