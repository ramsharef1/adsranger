<?php

namespace App\Sync;

use App\Models\AdAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Stores connector breakdowns. Re-pulling a range replaces that range for each dimension sent (no double counting). */
class BreakdownStore
{
    public const DIMENSIONS = ['hour', 'placement', 'device', 'region', 'age_gender', 'reach30'];

    /** @param array<string, array<int, array>> $byDimension */
    public static function replace(AdAccount $account, array $byDimension, string $from, string $to): int
    {
        $now = now();
        $n = 0;
        foreach ($byDimension as $dim => $rows) {
            if (! in_array($dim, self::DIMENSIONS, true) || ! is_array($rows)) {
                continue;
            }
            // reach30 is a rolling 30-day total stored on its end date; keep only the newest.
            $q = DB::table('metric_breakdowns')->where('ad_account_id', $account->id)->where('dimension', $dim);
            $dim === 'reach30' ? $q->delete() : $q->whereBetween('date', [$from, $to])->delete();
            $batch = [];
            foreach ($rows as $r) {
                $batch[] = [
                    'agency_id' => $account->agency_id, 'client_id' => $account->client_id, 'ad_account_id' => $account->id,
                    'network' => $account->network->key ?? 'unknown', 'dimension' => $dim, 'date' => $r['date'],
                    'key' => Str::limit((string) $r['key'], 118, ''), 'label' => isset($r['label']) ? Str::limit((string) $r['label'], 188, '') : null,
                    'spend' => (float) ($r['spend'] ?? 0), 'impressions' => (int) ($r['impressions'] ?? 0), 'reach' => (int) ($r['reach'] ?? 0),
                    'clicks' => (int) ($r['clicks'] ?? 0), 'link_clicks' => (int) ($r['link_clicks'] ?? 0), 'results' => (float) ($r['results'] ?? 0),
                    'result_type' => $r['result_type'] ?? null, 'currency' => $account->currency, 'created_at' => $now,
                ];
                if (count($batch) >= 500) {
                    DB::table('metric_breakdowns')->insert($batch);
                    $n += count($batch);
                    $batch = [];
                }
            }
            if ($batch) {
                DB::table('metric_breakdowns')->insert($batch);
                $n += count($batch);
            }
        }

        return $n;
    }

    /** Sum rows that share date + key + result type (connectors return them per campaign). */
    public static function collapse(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $k = $r['date'].'|'.$r['key'].'|'.($r['result_type'] ?? '');
            if (! isset($out[$k])) {
                $out[$k] = $r + ['spend' => 0, 'impressions' => 0, 'reach' => 0, 'clicks' => 0, 'link_clicks' => 0, 'results' => 0];

                continue;
            }
            foreach (['spend', 'impressions', 'reach', 'clicks', 'link_clicks', 'results'] as $m) {
                $out[$k][$m] += $r[$m] ?? 0;
            }
        }

        return array_values($out);
    }
}
