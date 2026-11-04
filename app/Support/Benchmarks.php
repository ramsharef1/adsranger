<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Internal benchmarks (#41, inside one agency) and the MENA benchmarks network (#42, across opt-in agencies).
 * Cells = vertical × market × main result type, last 30 days, money in USD. Only medians leave a cell: no names, no IDs.
 */
class Benchmarks
{
    public const METRICS = ['cpr' => 'Cost / result (USD)', 'ctr' => 'Link CTR %', 'cpm' => 'CPM (USD)', 'roas' => 'ROAS'];

    /** Per-client 30-day KPIs in USD for the given agencies, computed in SQL then folded. */
    protected static function clientKpis(array $agencyIds, string $from, string $to): array
    {
        if (! $agencyIds) {
            return [];
        }
        $rows = DB::table('daily_metrics')->whereIn('daily_metrics.agency_id', $agencyIds)->whereNotNull('daily_metrics.client_id')
            ->whereBetween('date', [$from, $to])
            ->selectRaw('client_id, currency, result_type, roas_eligible, sum(spend) as spend, sum(revenue) as revenue, sum(impressions) as impressions, sum(link_clicks) as link_clicks, sum(results) as results')
            ->groupBy('client_id', 'currency', 'result_type', 'roas_eligible')->get();
        $out = [];
        foreach ($rows as $r) {
            $usd = Fx::rate((string) ($r->currency ?: 'USD'), 'USD', $to);
            $o = $out[$r->client_id] ?? ['spend' => 0.0, 'revenue' => 0.0, 'roas_spend' => 0.0, 'impressions' => 0, 'link_clicks' => 0, 'type_spend' => [], 'type_res' => []];
            $s = (float) $r->spend * $usd;
            $o['spend'] += $s;
            $o['revenue'] += (float) $r->revenue * $usd;
            $o['impressions'] += (int) $r->impressions;
            $o['link_clicks'] += (int) $r->link_clicks;
            $t = $r->result_type ?: 'conversion';
            $o['type_spend'][$t] = ($o['type_spend'][$t] ?? 0) + $s;
            $o['type_res'][$t] = ($o['type_res'][$t] ?? 0) + (float) $r->results;
            if ($r->roas_eligible) {
                $o['roas_spend'] += $s;
            }
            $out[$r->client_id] = $o;
        }
        $clients = Client::whereIn('id', array_keys($out))->get(['id', 'agency_id', 'name', 'vertical', 'market'])->keyBy('id');
        foreach ($out as $id => $o) {
            arsort($o['type_spend']);
            $main = array_key_first($o['type_spend']);
            $res = $o['type_res'][$main] ?? 0;
            $c = $clients[$id] ?? null;
            $out[$id] = ['client_id' => $id, 'agency_id' => $c?->agency_id, 'name' => $c?->name, 'vertical' => $c?->vertical ?: 'other', 'market' => $c?->market ?: '—',
                'type' => $main, 'spend' => $o['spend'],
                'cpr' => $res > 0 ? $o['type_spend'][$main] / $res : null,
                'ctr' => $o['impressions'] > 0 ? $o['link_clicks'] / $o['impressions'] * 100 : null,
                'cpm' => $o['impressions'] > 0 ? $o['spend'] / $o['impressions'] * 1000 : null,
                'roas' => $o['roas_spend'] > 0 ? $o['revenue'] / $o['roas_spend'] : null];
        }

        return array_values(array_filter($out, fn ($x) => $x['spend'] > 0 && $x['agency_id']));
    }

    public static function median(array $v): ?float
    {
        $v = array_values(array_filter($v, fn ($x) => $x !== null));
        if (! $v) {
            return null;
        }
        sort($v);
        $n = count($v);

        return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }

    /** Cells keyed "vertical|market|type" with medians, client count and agency count. */
    protected static function cells(array $kpis): array
    {
        $groups = [];
        foreach ($kpis as $k) {
            $groups[$k['vertical'].'|'.$k['market'].'|'.$k['type']][] = $k;
        }
        $out = [];
        foreach ($groups as $key => $list) {
            [$v, $m, $t] = explode('|', $key);
            $cell = ['vertical' => $v, 'market' => $m, 'type' => $t, 'clients' => count($list), 'agencies' => count(array_unique(array_column($list, 'agency_id')))];
            foreach (array_keys(self::METRICS) as $met) {
                $cell[$met] = static::median(array_column($list, $met));
            }
            $out[$key] = $cell;
        }

        return $out;
    }

    protected static function range(): array
    {
        $to = now()->subDay()->toDateString();

        return [now()->subDays((int) config('insights.benchmarks.days'))->toDateString(), $to];
    }

    /** #41: this agency's clients vs the median of their own cell inside the agency. */
    public static function internal(Agency $a, array $visibleClientIds): array
    {
        [$from, $to] = static::range();
        $kpis = static::clientKpis([$a->id], $from, $to);
        $cells = array_filter(static::cells($kpis), fn ($c) => $c['clients'] >= (int) config('insights.benchmarks.min_clients'));
        $rows = [];
        foreach ($kpis as $k) {
            if (! in_array($k['client_id'], $visibleClientIds, true)) {
                continue;
            }
            $rows[] = $k + ['cell' => $cells[$k['vertical'].'|'.$k['market'].'|'.$k['type']] ?? null];
        }
        usort($rows, fn ($x, $y) => strcmp((string) $x['name'], (string) $y['name']));

        return ['rows' => $rows, 'cells' => array_values($cells), 'from' => $from, 'to' => $to];
    }

    /**
     * #42: medians across every opted-in agency; a cell is shown only with min_agencies distinct agencies.
     * Cached; the viewing agency must itself be opted in.
     */
    public static function network(): array
    {
        return Cache::remember('benchmarks:network:v1', now()->addHours((int) config('insights.benchmarks.cache_hours')), function () {
            [$from, $to] = static::range();
            $ids = Agency::where('benchmarks_opt_in', true)->pluck('id')->all();
            $min = (int) config('insights.benchmarks.min_agencies');
            $cells = array_values(array_filter(static::cells(static::clientKpis($ids, $from, $to)), fn ($c) => $c['agencies'] >= $min));
            // Drop the client and agency counts below the threshold detail level: keep counts only as "5+" style bands.
            foreach ($cells as &$c) {
                $c['agencies_band'] = $c['agencies'] >= 20 ? '20+' : ($c['agencies'] >= 10 ? '10+' : $min.'+');
                unset($c['agencies'], $c['clients']);
            }
            unset($c);

            return ['cells' => $cells, 'from' => $from, 'to' => $to, 'participants' => count($ids) >= $min ? (count($ids) >= 20 ? '20+' : count($ids)) : null];
        });
    }

    /** Compare a value with a median; lower is better for costs. */
    public static function versus(?float $v, ?float $median, string $metric): ?array
    {
        if ($v === null || ! $median) {
            return null;
        }
        $pct = ($v / $median - 1) * 100;
        $good = in_array($metric, ['cpr', 'cpm'], true) ? $pct < 0 : $pct > 0;

        return ['pct' => $pct, 'good' => abs($pct) < 3 ? null : $good];
    }
}
