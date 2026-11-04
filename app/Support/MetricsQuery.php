<?php

namespace App\Support;

use App\Models\Client;
use App\Models\DailyMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Read side for results: totals, series and drill-downs in the client's currency. */
class MetricsQuery
{
    public const COUNTS = ['impressions', 'reach', 'clicks', 'link_clicks', 'results', 'calls', 'calls_confirmed', 'calls_20s',
        'messages', 'leads', 'purchases', 'thruplays', 'landing_views', 'add_to_cart', 'checkouts'];

    protected static function base(Client $c, string $from, string $to): Builder
    {
        return DailyMetric::query()->where('agency_id', $c->agency_id)->where('client_id', $c->id)->whereBetween('date', [$from, $to]);
    }

    protected static function sums(array $extraGroup = []): array
    {
        $s = ['SUM(spend) as spend', 'SUM(revenue) as revenue'];
        foreach (self::COUNTS as $k) {
            $s[] = "SUM($k) as $k";
        }

        return $s;
    }

    /** Fold grouped rows (with date + currency) into totals in the client's currency. */
    protected static function fold(iterable $rows, string $cur, ?callable $key = null): array
    {
        $out = [];
        foreach ($rows as $r) {
            $k = $key ? $key($r) : '_';
            $o = $out[$k] ??= ['spend' => 0.0, 'revenue' => 0.0, 'roas_spend' => 0.0, 'by_type' => [], 'type_spend' => []] + array_fill_keys(self::COUNTS, 0.0);
            $rate = Fx::rate((string) ($r->currency ?: $cur), $cur, (string) $r->date);
            $spend = (float) $r->spend * $rate;
            $o['spend'] += $spend;
            $o['revenue'] += (float) $r->revenue * $rate;
            foreach (self::COUNTS as $c) {
                $o[$c] += (float) $r->{$c};
            }
            $t = $r->result_type ?: 'conversion';
            $o['by_type'][$t] = ($o['by_type'][$t] ?? 0) + (float) $r->results;
            $o['type_spend'][$t] = ($o['type_spend'][$t] ?? 0) + $spend;
            if ($r->roas_eligible) {
                $o['roas_spend'] += $spend;
            }
            $out[$k] = $o;
        }
        foreach ($out as $k => $o) {
            arsort($o['type_spend']);
            $main = array_key_first($o['type_spend']);
            $o['main_type'] = $main;
            $o['main_results'] = $main ? ($o['by_type'][$main] ?? 0) : 0;
            $o['cpr'] = $o['main_results'] > 0 ? ($o['type_spend'][$main] / $o['main_results']) : null;
            $o['roas'] = $o['roas_spend'] > 0 ? $o['revenue'] / $o['roas_spend'] : null;
            $o['ctr'] = $o['impressions'] > 0 ? $o['link_clicks'] / $o['impressions'] * 100 : null;
            $out[$k] = $o;
        }

        return $out;
    }

    protected static function empty(): array
    {
        return ['spend' => 0.0, 'revenue' => 0.0, 'roas_spend' => 0.0, 'by_type' => [], 'type_spend' => [], 'main_type' => null,
            'main_results' => 0, 'cpr' => null, 'roas' => null, 'ctr' => null] + array_fill_keys(self::COUNTS, 0.0);
    }

    public static function totals(Client $c, string $from, string $to): array
    {
        $rows = static::base($c, $from, $to)->selectRaw(implode(',', array_merge(['date', 'currency', 'result_type', 'roas_eligible'], static::sums())))
            ->groupBy('date', 'currency', 'result_type', 'roas_eligible')->get();

        return static::fold($rows, $c->currency)['_'] ?? static::empty();
    }

    /** Per day: [date => totals]. */
    public static function daily(Client $c, string $from, string $to): array
    {
        $rows = static::base($c, $from, $to)->selectRaw(implode(',', array_merge(['date', 'currency', 'result_type', 'roas_eligible'], static::sums())))
            ->groupBy('date', 'currency', 'result_type', 'roas_eligible')->orderBy('date')->get();
        $out = static::fold($rows, $c->currency, fn ($r) => substr((string) $r->date, 0, 10));
        ksort($out);

        return $out;
    }

    /** Per month for the last $months months: ['2026-06' => totals]. */
    public static function monthly(Client $c, int $months = 6): array
    {
        $from = now()->startOfMonth()->subMonthsNoOverflow($months - 1)->toDateString();
        $out = [];
        foreach (static::daily($c, $from, now()->toDateString()) as $d => $t) {
            $m = substr($d, 0, 7);
            $o = $out[$m] ?? static::empty();
            foreach (['spend', 'revenue', 'roas_spend', ...self::COUNTS] as $k) {
                $o[$k] += $t[$k];
            }
            foreach ($t['by_type'] as $ty => $v) {
                $o['by_type'][$ty] = ($o['by_type'][$ty] ?? 0) + $v;
                $o['type_spend'][$ty] = ($o['type_spend'][$ty] ?? 0) + ($t['type_spend'][$ty] ?? 0);
            }
            $out[$m] = $o;
        }
        for ($i = $months - 1; $i >= 0; $i--) {
            $out[now()->startOfMonth()->subMonthsNoOverflow($i)->format('Y-m')] ??= static::empty();
        }
        ksort($out);
        foreach ($out as $m => $o) {
            arsort($o['type_spend']);
            $main = array_key_first($o['type_spend']);
            $o['main_type'] = $main;
            $o['main_results'] = $main ? ($o['by_type'][$main] ?? 0) : 0;
            $o['cpr'] = $o['main_results'] > 0 ? $o['type_spend'][$main] / $o['main_results'] : null;
            $out[$m] = $o;
        }

        return $out;
    }

    /**
     * Drill-down rows: level = campaign | ad_set | ad, optionally inside a parent.
     * @return array<int, array> totals + ref, name, status, thumbnail
     */
    public static function breakdown(Client $c, string $from, string $to, string $level = 'campaign', ?int $parentRef = null): array
    {
        $col = ['campaign' => 'campaign_ref', 'ad_set' => 'ad_set_ref', 'ad' => 'ad_ref'][$level];
        $q = static::base($c, $from, $to)->whereNotNull($col);
        if ($parentRef) {
            $q->where($level === 'ad_set' ? 'campaign_ref' : 'ad_set_ref', $parentRef);
        }
        $rows = $q->selectRaw(implode(',', array_merge(["$col as ref", 'date', 'currency', 'result_type', 'roas_eligible'], static::sums())))
            ->groupBy($col, 'date', 'currency', 'result_type', 'roas_eligible')->get();
        $out = static::fold($rows, $c->currency, fn ($r) => (int) $r->ref);
        $table = ['campaign' => 'campaigns', 'ad_set' => 'ad_sets', 'ad' => 'ads'][$level];
        $meta = DB::table($table)->whereIn('id', array_keys($out))->get()->keyBy('id');
        $list = [];
        foreach ($out as $ref => $t) {
            $m = $meta[$ref] ?? null;
            $list[] = $t + ['ref' => $ref, 'name' => $m->name ?? ('#'.$ref), 'status' => $m->status ?? null,
                'thumbnail' => $m->thumbnail_url ?? null, 'goal' => $m->optimization_goal ?? ($m->objective ?? null)];
        }
        usort($list, fn ($a, $b) => $b['spend'] <=> $a['spend']);

        return $list;
    }

    /** Top ads by main results (then spend). */
    public static function topAds(Client $c, string $from, string $to, int $limit = 6): array
    {
        $ads = static::breakdown($c, $from, $to, 'ad');
        usort($ads, fn ($a, $b) => [$b['main_results'], $b['spend']] <=> [$a['main_results'], $a['spend']]);

        return array_slice($ads, 0, $limit);
    }

    /** Freshest successful sync among the client's accounts. */
    public static function lastSynced(Client $c): ?\Carbon\CarbonInterface
    {
        $v = $c->adAccounts()->max('last_synced_at');

        return $v ? \Illuminate\Support\Carbon::parse($v) : null;
    }
}
