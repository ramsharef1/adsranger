<?php

namespace App\Support;

/** Turns raw network rows into goal-based results (spec module 2). */
class ResultResolver
{
    /** Result type for a Meta ad set / campaign. */
    public static function metaType(?string $goal, ?string $objective, ?string $customEvent = null): string
    {
        $cfg = config('results');
        $goal = strtoupper((string) $goal);
        if ($goal && isset($cfg['meta_goals'][$goal])) {
            $type = $cfg['meta_goals'][$goal];
            if (in_array($goal, ['OFFSITE_CONVERSIONS', 'VALUE'], true) && $customEvent) {
                $type = $cfg['meta_custom_events'][strtoupper($customEvent)] ?? $type;
            }

            return $type;
        }

        return $cfg['meta_objectives'][strtoupper((string) $objective)] ?? 'link_click';
    }

    /** Sum of the first action list in $types that has any value (avoids double counting aliases). */
    protected static function pick(array $actions, array $types): float
    {
        foreach ($types as $t) {
            if (isset($actions[$t]) && $actions[$t] > 0) {
                return (float) $actions[$t];
            }
        }

        return 0.0;
    }

    protected static function index(?array $list): array
    {
        $out = [];
        foreach ($list ?? [] as $a) {
            $k = $a['action_type'] ?? null;
            if ($k !== null) {
                $out[$k] = ($out[$k] ?? 0) + (float) ($a['value'] ?? 0);
            }
        }

        return $out;
    }

    /**
     * One Meta insights row → metric columns.
     * @param  array  $row  insights row (spend, impressions, reach, clicks, inline_link_clicks, actions, action_values, video_thruplay_watched_actions)
     */
    public static function meta(array $row, string $type): array
    {
        $acts = static::index($row['actions'] ?? []);
        $vals = static::index($row['action_values'] ?? []);
        $map = config('results.actions');
        $p = fn (string $k) => static::pick($acts, $map[$k] ?? []);

        $m = [
            'spend' => (float) ($row['spend'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'reach' => (int) ($row['reach'] ?? 0),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'link_clicks' => (int) ($row['inline_link_clicks'] ?? ($acts['link_click'] ?? 0)),
            'purchases' => $p('purchase'),
            'leads' => $p('lead'),
            'messages' => $p('message'),
            'calls' => (float) ($acts['click_to_call_native_call_placed'] ?? 0),
            'calls_confirmed' => (float) ($acts['click_to_call_call_confirm'] ?? 0),
            'calls_20s' => (float) ($acts['click_to_call_native_20s_call_connect'] ?? 0),
            'thruplays' => (float) collect($row['video_thruplay_watched_actions'] ?? [])->sum(fn ($a) => (float) ($a['value'] ?? 0)),
            'landing_views' => $p('landing_page_view'),
            'add_to_cart' => $p('add_to_cart'),
            'checkouts' => $p('checkout'),
            'revenue' => static::pick($vals, $map['purchase']),
        ];

        $results = match ($type) {
            'purchase' => $m['purchases'],
            'lead' => $m['leads'],
            'message' => $m['messages'],
            'call' => $m['calls'] ?: $m['calls_confirmed'],
            'link_click' => (float) $m['link_clicks'],
            'landing_page_view' => $m['landing_views'],
            'thruplay' => $m['thruplays'],
            'reach' => (float) $m['reach'],
            'impressions' => (float) $m['impressions'],
            'add_to_cart' => $m['add_to_cart'],
            'checkout' => $m['checkouts'],
            default => $p($type),
        };
        // A conversions goal with 0 results but purchases > 0 counts purchases (donations, sales).
        if ($type === 'conversion') {
            $results = $p('registration') ?: ($m['leads'] ?: $m['purchases']);
            if ($m['purchases'] > 0 && $results == 0) {
                $results = $m['purchases'];
            }
        }

        $m['results'] = (float) $results;
        $m['result_type'] = $type;
        $m['roas_eligible'] = in_array($type, config('results.roas_types'), true) || ($type === 'conversion' && $m['purchases'] > 0);

        return $m;
    }

    /**
     * Google Ads campaign-day: metrics + per-action rows.
     * @param  array  $base  ['cost_micros','impressions','clicks','conversions','conversions_value']
     * @param  array  $actions  list of ['name','category','conversions','value']
     */
    public static function google(array $base, array $actions = []): array
    {
        $m = [
            'spend' => ((float) ($base['cost_micros'] ?? 0)) / 1_000_000,
            'impressions' => (int) ($base['impressions'] ?? 0), 'reach' => 0,
            'clicks' => (int) ($base['clicks'] ?? 0), 'link_clicks' => (int) ($base['clicks'] ?? 0),
            'purchases' => 0.0, 'leads' => 0.0, 'messages' => 0.0, 'calls' => 0.0, 'calls_confirmed' => 0.0, 'calls_20s' => 0.0,
            'thruplays' => 0.0, 'landing_views' => 0.0, 'add_to_cart' => 0.0, 'checkouts' => 0.0,
            'revenue' => (float) ($base['conversions_value'] ?? 0),
        ];
        $cat = config('results.google_categories');
        $breakdown = [];
        foreach ($actions as $a) {
            $col = $cat[strtoupper((string) ($a['category'] ?? ''))] ?? null;
            if ($col) {
                $m[$col] += (float) ($a['conversions'] ?? 0);
            }
            $breakdown[] = ['action' => $a['name'] ?? '?', 'category' => $a['category'] ?? null,
                'conversions' => round((float) ($a['conversions'] ?? 0), 2), 'value' => round((float) ($a['value'] ?? 0), 2)];
        }
        $m['results'] = (float) ($base['conversions'] ?? 0); // primary conversion actions only
        $m['result_type'] = 'conversion';
        $m['roas_eligible'] = $m['revenue'] > 0;
        $m['breakdown'] = $breakdown ?: null;

        return $m;
    }

    public static function label(?string $type, string $locale = 'en'): string
    {
        $l = config('results.labels')[$type] ?? [ucfirst(str_replace('_', ' ', (string) $type)), (string) $type];

        return $locale === 'ar' ? $l[1] : $l[0];
    }

    /** Costs under 1 show 3 decimals. */
    public static function money(?float $v, ?string $cur = null): string
    {
        if ($v === null) {
            return '–';
        }
        $s = number_format($v, abs($v) < 1 && $v != 0 ? 3 : 2);

        return $cur ? $s.' '.$cur : $s;
    }
}
