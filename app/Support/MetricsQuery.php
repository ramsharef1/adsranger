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
        return DailyMetric::query()->where('agency_id', $c->agency_id)->where('client_id', $c->id)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
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
    protected static function fold(iterable $rows, string $cur, ?callable $key = null, string $callBasis = 'placed'): array
    {
        $out = [];
        foreach ($rows as $r) {
            $k = $key ? $key($r) : '_';
            $o = $out[$k] ??= ['spend' => 0.0, 'revenue' => 0.0, 'roas_spend' => 0.0, 'by_type' => [], 'type_spend' => []] + array_fill_keys(self::COUNTS, 0.0);
            $rate = Fx::rate((string) ($r->currency ?: $cur), $cur, (string) $r->date);
            $spend = (float) $r->spend * $rate;
            $o['spend'] += $spend;
            $o['revenue'] += (float) $r->revenue * $rate;
            $res = CallResults::results($r, $callBasis); // #12: the client's chosen call count
            foreach (self::COUNTS as $c) {
                $o[$c] += $c === 'results' ? $res : (float) $r->{$c};
            }
            $t = $r->result_type ?: 'conversion';
            $o['by_type'][$t] = ($o['by_type'][$t] ?? 0) + $res;
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
        $rows = static::base($c, $from, $to)->selectRaw(implode(',', array_merge(['date', 'currency', 'network', 'result_type', 'roas_eligible'], static::sums())))
            ->groupBy('date', 'currency', 'network', 'result_type', 'roas_eligible')->get();

        return static::fold($rows, $c->currency, null, CallResults::basis($c->call_result))['_'] ?? static::empty();
    }

    /** Per day: [date => totals]. */
    public static function daily(Client $c, string $from, string $to): array
    {
        $rows = static::base($c, $from, $to)->selectRaw(implode(',', array_merge(['date', 'currency', 'network', 'result_type', 'roas_eligible'], static::sums())))
            ->groupBy('date', 'currency', 'network', 'result_type', 'roas_eligible')->orderBy('date')->get();
        $out = static::fold($rows, $c->currency, fn ($r) => substr((string) $r->date, 0, 10), CallResults::basis($c->call_result));
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
        $rows = $q->selectRaw(implode(',', array_merge(["$col as ref", 'date', 'currency', 'network', 'result_type', 'roas_eligible'], static::sums())))
            ->groupBy($col, 'date', 'currency', 'network', 'result_type', 'roas_eligible')->get();
        $out = static::fold($rows, $c->currency, fn ($r) => (int) $r->ref, CallResults::basis($c->call_result));
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

    /** Google conversions by action (#13): [action => [category, conversions, value]] for the period. */
    public static function conversionActions(Client $c, string $from, string $to): array
    {
        $out = [];
        foreach (static::base($c, $from, $to)->whereNotNull('breakdown')->pluck('breakdown') as $b) {
            foreach ((array) (is_string($b) ? json_decode($b, true) : $b) as $a) {
                $k = $a['action'] ?? '?';
                $o = $out[$k] ?? ['category' => $a['category'] ?? null, 'conversions' => 0.0, 'value' => 0.0];
                $o['conversions'] += (float) ($a['conversions'] ?? 0);
                $o['value'] += (float) ($a['value'] ?? 0);
                $out[$k] = $o;
            }
        }
        uasort($out, fn ($x, $y) => $y['conversions'] <=> $x['conversions']);

        return $out;
    }

    /** Full funnel (#16) from totals: steps with counts and step-to-step rates. Empty steps are dropped. */
    public static function funnel(array $t): array
    {
        $sales = $t['purchases'] > 0 || $t['add_to_cart'] > 0 || $t['checkouts'] > 0;
        $steps = array_filter([
            'Impressions' => $t['impressions'], 'Link clicks' => $t['link_clicks'] ?: $t['clicks'], 'Landing page views' => $t['landing_views'],
        ] + ($sales ? ['Add to cart' => $t['add_to_cart'], 'Checkouts' => $t['checkouts'], 'Purchases' => $t['purchases']]
            : ['Leads' => $t['leads'], 'Conversations' => $t['messages'], 'Calls' => $t['calls']]), fn ($v) => $v > 0);
        $out = [];
        $prev = null;
        foreach ($steps as $label => $n) {
            $out[] = ['label' => $label, 'n' => $n, 'rate' => $prev ? $n / $prev * 100 : null];
            $prev = $n;
        }

        return $out;
    }

    /** Freshest successful sync among the client's accounts. */
    public static function lastSynced(Client $c): ?\Carbon\CarbonInterface
    {
        $v = $c->adAccounts()->max('last_synced_at');

        return $v ? \Illuminate\Support\Carbon::parse($v) : null;
    }
}
