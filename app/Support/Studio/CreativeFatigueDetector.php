<?php

namespace App\Support\Studio;

use App\Models\Client;
use App\Models\CreativeFatigueAlert;
use App\Support\MenaCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * E2-066: finds ads that are tiring — frequency saturation and a click-through (or ThruPlay) rate drop against the ad's own
 * baseline — from ad-level daily_metrics. Limits tighten while a MENA peak window runs (Ramadan's last ten nights, Eid,
 * White Friday). "fatigued" = frequency AND a rate drop over the limit; "watch" = one of them. Nothing is estimated:
 * an ad without enough impressions in both windows is skipped.
 *
 * @phpstan-type Row array{network: ?string, ad_ref: string, ad_name: ?string, status: string, frequency: ?float, ctr_recent: float,
 *   ctr_baseline: float, ctr_drop_pct: float, thruplay_drop_pct: ?float, spend_recent: float}
 */
class CreativeFatigueDetector
{
    /** @return array{key: string, label: string, ar: string, start: string, end: string}|null */
    public static function peakWindow(Client $client, Carbon $day): ?array
    {
        $date = $day->toDateString();
        $market = $client->agency?->country ?: null;
        $wanted = (array) config('creative_fatigue.peak_events', []);
        foreach (MenaCalendar::events($date, $date, $market) as $e) {
            if ($e['key'] === 'ramadan' && in_array('ramadan_last_10', $wanted, true)) {
                $from = Carbon::parse($e['start'])->addDays((int) config('creative_fatigue.ramadan_last_10_from_day', 20) - 1);
                if ($day->gte($from)) {
                    return ['key' => 'ramadan_last_10', 'label' => 'Last ten nights of Ramadan', 'ar' => 'العشر الأواخر من رمضان', 'start' => $from->toDateString(), 'end' => $e['end']];
                }

                continue;
            }
            if (in_array($e['key'], $wanted, true)) {
                return ['key' => $e['key'], 'label' => $e['label'], 'ar' => $e['ar'], 'start' => $e['start'], 'end' => $e['end']];
            }
        }

        return null;
    }

    /** @return array{frequency: float, ctr_drop_pct: float, thruplay_drop_pct: float} */
    public static function thresholds(?array $peak): array
    {
        return array_map('floatval', (array) config('creative_fatigue.'.($peak ? 'peak' : 'normal')));
    }

    /**
     * @return array{peak: ?array, thresholds: array, rows: list<Row>}
     */
    public static function evaluate(Client $client, ?Carbon $day = null): array
    {
        $end = ($day ?? now($client->agency?->timezone ?: 'UTC')->subDay())->copy()->startOfDay();
        $recentFrom = $end->copy()->subDays((int) config('creative_fatigue.recent_days', 7) - 1);
        $baseTo = $recentFrom->copy()->subDay();
        $baseFrom = $baseTo->copy()->subDays((int) config('creative_fatigue.baseline_days', 21) - 1);
        $peak = static::peakWindow($client, $end);
        $t = static::thresholds($peak);
        $min = (int) config('creative_fatigue.min_impressions', 1000);

        $agg = fn (Carbon $from, Carbon $to) => DB::table('daily_metrics')
            ->where('agency_id', $client->agency_id)->where('client_id', $client->id)->where('level', 'ad')->whereNotNull('ad_ref')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('ad_ref')
            ->selectRaw('ad_ref, MAX(network) as network, MAX(campaign_name) as campaign_name, SUM(impressions) as impressions, SUM(reach) as reach,
                SUM(COALESCE(NULLIF(link_clicks, 0), clicks)) as clicks, SUM(thruplays) as thruplays, SUM(spend) as spend')
            ->get()->keyBy('ad_ref');
        $recent = $agg($recentFrom, $end);
        $base = $agg($baseFrom, $baseTo);
        $names = DB::table('ads')->where('agency_id', $client->agency_id)->whereIn('external_id', $recent->keys()->all())->pluck('name', 'external_id');

        $rows = [];
        foreach ($recent as $ref => $r) {
            $b = $base->get($ref);
            if (! $b || (int) $r->impressions < $min || (int) $b->impressions < $min) {
                continue;
            }
            $ctrNow = (float) $r->clicks / (int) $r->impressions;
            $ctrBase = (float) $b->clicks / (int) $b->impressions;
            $ctrDrop = $ctrBase > 0 ? max(0.0, ($ctrBase - $ctrNow) / $ctrBase * 100) : 0.0;
            $freq = (float) $r->reach > 0 ? (int) $r->impressions / (float) $r->reach : null;
            $tpBase = (float) $b->thruplays / (int) $b->impressions;
            $tpDrop = (float) $b->thruplays > 0 ? max(0.0, ($tpBase - (float) $r->thruplays / (int) $r->impressions) / $tpBase * 100) : null;

            $saturated = $freq !== null && $freq >= $t['frequency'];
            $decaying = $ctrDrop >= $t['ctr_drop_pct'] || ($tpDrop !== null && $tpDrop >= $t['thruplay_drop_pct']);
            if (! $saturated && ! $decaying) {
                continue;
            }
            $rows[] = [
                'network' => $r->network, 'ad_ref' => (string) $ref, 'ad_name' => $names[$ref] ?? null,
                'status' => $saturated && $decaying ? 'fatigued' : 'watch',
                'frequency' => $freq !== null ? round($freq, 2) : null,
                'ctr_recent' => round($ctrNow * 100, 4), 'ctr_baseline' => round($ctrBase * 100, 4), 'ctr_drop_pct' => round($ctrDrop, 1),
                'thruplay_drop_pct' => $tpDrop !== null ? round($tpDrop, 1) : null, 'spend_recent' => round((float) $r->spend, 3),
            ];
        }
        usort($rows, fn ($a, $b) => [$a['status'] === 'fatigued' ? 0 : 1, -$a['spend_recent']] <=> [$b['status'] === 'fatigued' ? 0 : 1, -$b['spend_recent']]);

        return ['peak' => $peak, 'thresholds' => $t, 'rows' => $rows, 'window_end' => $end->toDateString()];
    }

    /**
     * Saves the result: one open alert per ad (updated in place), ads that recovered are resolved.
     *
     * @return array{open: int, resolved: int, peak: ?array}
     */
    public static function run(Client $client, ?Carbon $day = null): array
    {
        $result = static::evaluate($client, $day);
        $open = CreativeFatigueAlert::where('agency_id', $client->agency_id)->where('client_id', $client->id)->whereNull('resolved_at')->get()->keyBy('ad_ref');
        $seen = [];
        foreach ($result['rows'] as $row) {
            $seen[$row['ad_ref']] = true;
            $alert = $open->get($row['ad_ref']) ?? new CreativeFatigueAlert(['agency_id' => $client->agency_id, 'client_id' => $client->id, 'detected_at' => now()]);
            $alert->fill($row + ['peak_key' => $result['peak']['key'] ?? null, 'thresholds' => $result['thresholds'], 'window_end' => $result['window_end']])->save();
        }
        $resolved = 0;
        foreach ($open as $ref => $alert) {
            if (! isset($seen[$ref])) {
                $alert->update(['resolved_at' => now()]);
                $resolved++;
            }
        }

        return ['open' => count($seen), 'resolved' => $resolved, 'peak' => $result['peak']];
    }
}
