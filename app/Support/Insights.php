<?php

namespace App\Support;

use App\Models\AdSet;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Client insights: breakdowns (#43 heatmap, #44 placements + audience, #97 geo), spend → results curve (#38),
 * budget reallocation (#39), saturation + overlap (#98). Money is folded into the client's currency per day.
 */
class Insights
{
    public const PLACEMENT_LABELS = [
        'facebook:feed' => 'Facebook Feed', 'facebook:facebook_reels' => 'Facebook Reels', 'facebook:story' => 'Facebook Stories',
        'facebook:marketplace' => 'Marketplace', 'facebook:video_feeds' => 'Facebook Video feeds', 'facebook:right_hand_column' => 'Right column',
        'facebook:search' => 'Facebook Search', 'facebook:instream_video' => 'In-stream video', 'instagram:feed' => 'Instagram Feed',
        'instagram:story' => 'Instagram Stories', 'instagram:instagram_reels' => 'Instagram Reels', 'instagram:instagram_explore' => 'Instagram Explore',
        'instagram:instagram_explore_grid_home' => 'Explore home', 'instagram:profile_feed' => 'Instagram Profile', 'messenger:messenger_inbox' => 'Messenger',
        'messenger:story' => 'Messenger Stories', 'audience_network:classic' => 'Audience Network', 'audience_network:rewarded_video' => 'AN Rewarded video',
        'threads:threads_feed' => 'Threads', 'google:search' => 'Google Search', 'google:search_partners' => 'Search partners',
        'google:content' => 'Display network', 'google:youtube_search' => 'YouTube search', 'google:youtube_watch' => 'YouTube',
        'google:mixed' => 'Cross-network (PMax)',
    ];

    /** Per-key totals for one dimension, in the client's currency, sorted by spend. Main result type = the client's main type in the range. */
    public static function breakdown(Client $c, string $dim, string $from, string $to): array
    {
        $rows = DB::table('metric_breakdowns')->where('agency_id', $c->agency_id)->where('client_id', $c->id)->where('dimension', $dim)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('key, max(label) as label, date, currency, result_type, sum(spend) as spend, sum(impressions) as impressions, sum(reach) as reach, sum(clicks) as clicks, sum(link_clicks) as link_clicks, sum(results) as results')
            ->groupBy('key', 'date', 'currency', 'result_type')->get();
        $main = MetricsQuery::totals($c, $from, $to)['main_type'];
        $out = [];
        foreach ($rows as $r) {
            $o = $out[$r->key] ?? ['key' => $r->key, 'label' => $r->label, 'spend' => 0.0, 'impressions' => 0, 'reach' => 0, 'clicks' => 0, 'link_clicks' => 0,
                'results' => 0.0, 'type_spend' => 0.0, 'dates' => []];
            $spend = (float) $r->spend * Fx::rate((string) ($r->currency ?: $c->currency), $c->currency, (string) $r->date);
            $o['spend'] += $spend;
            $o['impressions'] += (int) $r->impressions;
            $o['reach'] += (int) $r->reach;
            $o['clicks'] += (int) $r->clicks;
            $o['link_clicks'] += (int) $r->link_clicks;
            if (! $main || $r->result_type === $main) {
                $o['results'] += (float) $r->results;
                $o['type_spend'] += $spend;
            }
            $o['label'] ??= $r->label;
            $out[$r->key] = $o;
        }
        $total = max(0.0001, array_sum(array_column($out, 'spend')));
        foreach ($out as $k => $o) {
            $o['share'] = $o['spend'] / $total * 100;
            $o['cpr'] = $o['results'] > 0 ? $o['type_spend'] / $o['results'] : null;
            $o['ctr'] = $o['impressions'] > 0 ? $o['link_clicks'] / $o['impressions'] * 100 : null;
            $o['cpm'] = $o['impressions'] > 0 ? $o['spend'] / $o['impressions'] * 1000 : null;
            $o['name'] = static::name($dim, $k, $o['label']);
            unset($o['dates']);
            $out[$k] = $o;
        }
        uasort($out, fn ($a, $b) => $b['spend'] <=> $a['spend']);

        return ['rows' => array_values($out), 'main_type' => $main, 'has' => (bool) $out];
    }

    public static function name(string $dim, string $key, ?string $label): string
    {
        return match ($dim) {
            'placement' => self::PLACEMENT_LABELS[$key] ?? ucwords(str_replace(['_', ':'], [' ', ' · '], $key)),
            'device' => ['mobile' => 'Mobile', 'desktop' => 'Desktop', 'tablet' => 'Tablet', 'connected_tv' => 'TV', 'other' => 'Other'][$key] ?? ucfirst($key),
            'age_gender' => str_replace('|', ' · ', $key),
            'region' => $label ?: (str_starts_with($key, 'geoTargetConstants/') ? 'Location '.substr($key, 19) : $key),
            'hour' => sprintf('%02d:00', (int) $key),
            default => $label ?: $key,
        };
    }

    /** Weekday × hour grid (#43): [dow 0=Sun..6][hour] => spend, results, cpr. Weekday comes from the account-time-zone date. */
    public static function heatmap(Client $c, string $from, string $to): array
    {
        $rows = DB::table('metric_breakdowns')->where('agency_id', $c->agency_id)->where('client_id', $c->id)->where('dimension', 'hour')
            ->whereBetween('date', [$from, $to])->selectRaw('date, key, currency, result_type, sum(spend) as spend, sum(results) as results, sum(impressions) as impressions')
            ->groupBy('date', 'key', 'currency', 'result_type')->get();
        $main = MetricsQuery::totals($c, $from, $to)['main_type'];
        $grid = [];
        foreach ($rows as $r) {
            $dow = Carbon::parse($r->date)->dayOfWeek;
            $h = (int) $r->key;
            $cell = $grid[$dow][$h] ?? ['spend' => 0.0, 'results' => 0.0, 'impressions' => 0];
            $spend = (float) $r->spend * Fx::rate((string) ($r->currency ?: $c->currency), $c->currency, (string) $r->date);
            $cell['spend'] += $spend;
            $cell['impressions'] += (int) $r->impressions;
            if (! $main || $r->result_type === $main) {
                $cell['results'] += (float) $r->results;
            }
            $grid[$dow][$h] = $cell;
        }
        $maxRes = 0;
        foreach ($grid as $d => $hs) {
            foreach ($hs as $h => $cell) {
                $grid[$d][$h]['cpr'] = $cell['results'] > 0 ? $cell['spend'] / $cell['results'] : null;
                $maxRes = max($maxRes, $cell['results']);
            }
        }
        // Best / weakest hours by cost per result, among hours with enough results to judge.
        $byHour = [];
        foreach ($grid as $hs) {
            foreach ($hs as $h => $cell) {
                $byHour[$h]['spend'] = ($byHour[$h]['spend'] ?? 0) + $cell['spend'];
                $byHour[$h]['results'] = ($byHour[$h]['results'] ?? 0) + $cell['results'];
            }
        }
        $judged = array_filter($byHour, fn ($x) => $x['results'] >= 3);
        uasort($judged, fn ($a, $b) => ($a['spend'] / $a['results']) <=> ($b['spend'] / $b['results']));

        return ['grid' => $grid, 'max_results' => $maxRes, 'main_type' => $main, 'has' => (bool) $grid,
            'best' => array_slice(array_keys($judged), 0, 3), 'worst' => array_slice(array_reverse(array_keys($judged)), 0, 3)];
    }

    /**
     * Spend → results curve (#38): results/day = a · spend^b, fitted on log–log daily points of the last N days.
     *
     * @param  ?int  $campaignRef  limit to one campaign
     */
    public static function curve(Client $c, ?int $campaignRef = null, ?string $to = null): ?array
    {
        $cfg = config('insights.curve');
        $to ??= now($c->agency?->timezone ?: 'UTC')->subDay()->toDateString();
        $from = Carbon::parse($to)->subDays($cfg['days'] - 1)->toDateString();
        $q = DB::table('daily_metrics')->where('agency_id', $c->agency_id)->where('client_id', $c->id)->whereBetween('date', [$from, $to]);
        if ($campaignRef) {
            $q->where('campaign_ref', $campaignRef);
        }
        $main = MetricsQuery::totals($c, $from, $to)['main_type'];
        $days = [];
        foreach ($q->selectRaw('date, currency, result_type, sum(spend) as spend, sum(results) as results')->groupBy('date', 'currency', 'result_type')->get() as $r) {
            $d = substr((string) $r->date, 0, 10);
            $days[$d]['spend'] = ($days[$d]['spend'] ?? 0) + (float) $r->spend * Fx::rate((string) ($r->currency ?: $c->currency), $c->currency, $d);
            if ($r->result_type === $main) {
                $days[$d]['results'] = ($days[$d]['results'] ?? 0) + (float) $r->results;
            }
        }
        $pts = array_values(array_filter($days, fn ($x) => ($x['spend'] ?? 0) > 0 && ($x['results'] ?? 0) > 0));
        if (count($pts) < $cfg['min_points']) {
            return null;
        }
        $xs = array_map(fn ($p) => log($p['spend']), $pts);
        $ys = array_map(fn ($p) => log($p['results']), $pts);
        $n = count($pts);
        $mx = array_sum($xs) / $n;
        $my = array_sum($ys) / $n;
        $sxx = $sxy = $syy = 0.0;
        foreach ($xs as $i => $x) {
            $sxx += ($x - $mx) ** 2;
            $sxy += ($x - $mx) * ($ys[$i] - $my);
            $syy += ($ys[$i] - $my) ** 2;
        }
        $rawB = $sxx > 0 ? $sxy / $sxx : 1.0;
        $b = max($cfg['elasticity_min'], min($cfg['elasticity_max'], $rawB));
        $a = exp($my - $b * $mx);
        $r2 = $sxx > 0 && $syy > 0 ? ($sxy * $sxy) / ($sxx * $syy) : 0.0;
        $avg = array_sum(array_column($pts, 'spend')) / $n;
        $f = fn (float $s) => $a * $s ** $b;
        $steps = [];
        foreach ($cfg['steps'] as $m) {
            $s = $avg * $m;
            $res = $f($s);
            $steps[] = ['mult' => $m, 'spend' => $s, 'results' => $res, 'cpr' => $res > 0 ? $s / $res : null,
                'marginal_cpr' => $res > 0 ? ($s / $res) / $b : null, 'month_spend' => $s * 30, 'month_results' => $res * 30];
        }

        return ['a' => $a, 'b' => $b, 'raw_b' => $rawB, 'r2' => $r2, 'points' => $n, 'avg_spend' => $avg, 'main_type' => $main, 'steps' => $steps,
            'scatter' => array_map(fn ($p) => [$p['spend'], $p['results']], $pts), 'fit' => fn (float $s) => $f($s),
            'confidence' => $r2 >= 0.5 && $n >= 30 ? 'good' : ($r2 >= 0.25 ? 'fair' : 'low')];
    }

    /**
     * Reallocation (#39): campaigns with the client's main result type; move part of the daily spend from the campaign
     * with the highest marginal cost per result to the lowest, when the curves say results go up enough.
     */
    public static function reallocation(Client $c): array
    {
        $to = now($c->agency?->timezone ?: 'UTC')->subDay()->toDateString();
        $from = Carbon::parse($to)->subDays(13)->toDateString();
        $camps = array_filter(MetricsQuery::breakdown($c, $from, $to, 'campaign'), fn ($x) => $x['spend'] > 0);
        $fits = [];
        foreach ($camps as $x) {
            $curve = static::curve($c, (int) $x['ref'], $to);
            if ($curve && $curve['main_type'] === ($x['main_type'] ?? null)) {
                $cur = $x['spend'] / 14;
                $res = ($curve['fit'])($cur);
                $fits[] = ['ref' => $x['ref'], 'name' => $x['name'], 'daily' => $cur, 'curve' => $curve, 'results' => $res,
                    'marginal' => $res > 0 ? ($cur / $res) / $curve['b'] : null];
            }
        }
        $fits = array_values(array_filter($fits, fn ($f) => $f['marginal'] !== null));
        if (count($fits) < 2) {
            return ['suggestion' => null, 'campaigns' => $fits, 'reason' => 'Needs at least 2 campaigns with the same main result and 14+ days of results each.'];
        }
        usort($fits, fn ($x, $y) => $x['marginal'] <=> $y['marginal']);
        $best = $fits[0];
        $worst = $fits[count($fits) - 1];
        $move = $worst['daily'] * (float) config('insights.reallocation.shift');
        $before = $best['results'] + $worst['results'];
        $after = ($best['curve']['fit'])($best['daily'] + $move) + ($worst['curve']['fit'])($worst['daily'] - $move);
        $gain = $before > 0 ? ($after / $before - 1) * 100 : 0;
        $s = $gain >= (float) config('insights.reallocation.min_gain_pct') ? [
            'from' => $worst['name'], 'to' => $best['name'], 'amount' => $move, 'gain_pct' => $gain,
            'results_before' => $before * 30, 'results_after' => $after * 30,
        ] : null;

        return ['suggestion' => $s, 'campaigns' => $fits, 'reason' => $s ? null : 'Budgets look balanced: moving 20% between campaigns would gain less than :pct% results.',
            'reason_params' => ['pct' => config('insights.reallocation.min_gain_pct')]];
    }

    /** Saturation per ad set (#98): 30-day frequency + CTR last 7 days vs the 7 days 3 weeks earlier; overlap estimate from 30-day reach. */
    public static function saturation(Client $c): array
    {
        $acctIds = $c->adAccounts()->pluck('id');
        $r30 = DB::table('metric_breakdowns')->whereIn('ad_account_id', $acctIds)->where('client_id', $c->id)->where('dimension', 'reach30')->get();
        $sets = AdSet::whereIn('ad_account_id', $acctIds)->get()->keyBy('external_id');
        $to = now($c->agency?->timezone ?: 'UTC')->subDay();
        $ctr = fn (string $f, string $t) => DB::table('daily_metrics')->where('client_id', $c->id)->whereBetween('date', [$f, $t])->whereNotNull('ad_set_ref')
            ->selectRaw('ad_set_ref, sum(impressions) as i, sum(link_clicks) as l')->groupBy('ad_set_ref')->get()->keyBy('ad_set_ref');
        $now = $ctr($to->copy()->subDays(6)->toDateString(), $to->toDateString());
        $then = $ctr($to->copy()->subDays(27)->toDateString(), $to->copy()->subDays(21)->toDateString());
        $cfg = config('insights.saturation');
        $rows = [];
        $sumReach = 0;
        $accountReach = 0;
        foreach ($r30 as $r) {
            if ($r->key === 'account') {
                $accountReach += (int) $r->reach;

                continue;
            }
            $set = $sets[substr($r->key, 6)] ?? null;
            if (! $set || $r->reach <= 0) {
                continue;
            }
            $sumReach += (int) $r->reach;
            $freq = $r->impressions / $r->reach;
            $n = $now[$set->id] ?? null;
            $t = $then[$set->id] ?? null;
            $ctrNow = $n && $n->i > 0 ? $n->l / $n->i * 100 : null;
            $ctrThen = $t && $t->i > 0 ? $t->l / $t->i * 100 : null;
            $drop = $ctrNow !== null && $ctrThen ? (1 - $ctrNow / $ctrThen) * 100 : null;
            $status = $freq >= $cfg['frequency'] && $drop !== null && $drop >= $cfg['ctr_drop_pct'] ? 'saturating'
                : ($freq >= $cfg['frequency'] ? 'high_frequency' : 'ok');
            $rows[] = ['ref' => $set->id, 'name' => $set->name ?? $set->external_id, 'status_api' => $set->status, 'reach' => (int) $r->reach, 'frequency' => $freq,
                'ctr_now' => $ctrNow, 'ctr_then' => $ctrThen, 'ctr_drop' => $drop, 'state' => $status];
        }
        usort($rows, fn ($a, $b) => $b['frequency'] <=> $a['frequency']);
        $overlap = $sumReach > 0 && $accountReach > 0 && count($rows) > 1 ? max(0, (1 - $accountReach / $sumReach) * 100) : null;

        return ['rows' => $rows, 'overlap' => $overlap, 'account_reach' => $accountReach, 'sum_reach' => $sumReach,
            'as_of' => $r30->max('date'), 'has' => (bool) $rows];
    }
}
