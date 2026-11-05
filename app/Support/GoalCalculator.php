<?php

namespace App\Support;

use App\Models\Client;

/**
 * Reverse-funnel goal calculator (#88): from a target (sales, revenue or leads) back to the landing page views,
 * clicks, impressions and budget needed, using the client's own last-90-day step rates where it has them and
 * clearly marked starting assumptions where it doesn't.
 */
class GoalCalculator
{
    /** Funnel steps per goal; the first step is always impressions, the last is the goal. */
    public const STEPS = [
        'purchases' => ['impressions', 'link_clicks', 'landing_views', 'add_to_cart', 'checkouts', 'purchases'],
        'leads' => ['impressions', 'link_clicks', 'landing_views', 'leads'],
    ];

    /** Starting assumptions (% from the step before) when the client has no history for a step. */
    public const DEFAULT_RATES = ['link_clicks' => 1.0, 'landing_views' => 75.0, 'add_to_cart' => 8.0, 'checkouts' => 45.0, 'purchases' => 55.0, 'leads' => 8.0];

    public const LABELS = ['impressions' => 'Impressions', 'link_clicks' => 'Link clicks', 'landing_views' => 'Landing page views',
        'add_to_cart' => 'Add to cart', 'checkouts' => 'Checkouts', 'purchases' => 'Purchases', 'leads' => 'Leads'];

    public const HISTORY_DAYS = 90;

    /** Steps used for a goal type ('revenue' runs through purchases). */
    public static function steps(string $goal): array
    {
        return self::STEPS[$goal === 'leads' ? 'leads' : 'purchases'];
    }

    /**
     * The client's last-90-day numbers: step rates (% of the step before, only where both steps have data),
     * CPM, average order value and cost per goal, all in the client's currency.
     *
     * @return array{rates: array<string, float>, cpm: ?float, aov: ?float, spend_per_day: ?float, cost_per: array<string, ?float>, from: string, to: string}
     */
    public static function history(Client $c): array
    {
        $tz = $c->agency?->timezone ?: 'UTC';
        $to = now($tz)->subDay()->toDateString();
        $from = now($tz)->subDays(self::HISTORY_DAYS)->toDateString();
        $t = MetricsQuery::totals($c, $from, $to);
        $t['link_clicks'] = $t['link_clicks'] ?: $t['clicks'];
        $rates = [];
        foreach (self::STEPS as $steps) {
            for ($i = 1; $i < count($steps); $i++) {
                [$prev, $cur] = [$steps[$i - 1], $steps[$i]];
                if ($t[$prev] > 0 && $t[$cur] > 0 && $t[$cur] <= $t[$prev]) {
                    $rates[$cur] = round($t[$cur] / $t[$prev] * 100, 2);
                }
            }
        }

        return [
            'rates' => $rates,
            'cpm' => $t['impressions'] >= 1000 && $t['spend'] > 0 ? round($t['spend'] / $t['impressions'] * 1000, 2) : null,
            'aov' => $t['purchases'] > 0 && $t['revenue'] > 0 ? round($t['revenue'] / $t['purchases'], 2) : null,
            'spend_per_day' => $t['spend'] > 0 ? round($t['spend'] / self::HISTORY_DAYS, 2) : null,
            'cost_per' => ['purchases' => $t['purchases'] > 0 ? $t['spend'] / $t['purchases'] : null, 'leads' => $t['leads'] > 0 ? $t['spend'] / $t['leads'] : null],
            'from' => $from, 'to' => $to,
        ];
    }

    /**
     * Work back from the target. A step whose rate is left empty is skipped (the next rate then counts from the step
     * before it). Returns null when the inputs can't produce a plan.
     *
     * @param  array<string, float|string|null>  $rates  % per step
     * @return array{steps: list<array{key: string, label: string, rate: ?float, needed: float}>, budget: ?float, per_day: ?float, goal_count: float}|null
     */
    public static function plan(string $goal, float $target, array $rates, ?float $cpm, ?float $aov, int $days): ?array
    {
        if ($target <= 0 || $days < 1) {
            return null;
        }
        $goalCount = $target;
        if ($goal === 'revenue') {
            if (! $aov || $aov <= 0) {
                return null;
            }
            $goalCount = $target / $aov;
        }
        $steps = array_values(array_filter(static::steps($goal), function (string $k) use ($rates) {
            return $k === 'impressions' || static::rate($rates[$k] ?? null) !== null;
        }));
        if (count($steps) < 2 || end($steps) !== static::steps($goal)[count(static::steps($goal)) - 1]) {
            return null; // the goal step itself needs a rate
        }
        $needed = [];
        $needed[count($steps) - 1] = ceil($goalCount);
        for ($i = count($steps) - 1; $i > 0; $i--) {
            $r = static::rate($rates[$steps[$i]]);
            $needed[$i - 1] = ceil($needed[$i] / ($r / 100));
        }
        $out = [];
        foreach ($steps as $i => $k) {
            $out[] = ['key' => $k, 'label' => self::LABELS[$k], 'rate' => $i ? static::rate($rates[$k]) : null, 'needed' => (float) $needed[$i]];
        }
        $budget = $cpm && $cpm > 0 ? round($needed[0] / 1000 * $cpm, 2) : null;

        return ['steps' => $out, 'budget' => $budget, 'per_day' => $budget !== null ? round($budget / $days, 2) : null, 'goal_count' => (float) ceil($goalCount)];
    }

    /** A usable % (0 < r ≤ 100), or null. */
    public static function rate(float|string|null $r): ?float
    {
        if ($r === null || $r === '' || ! is_numeric($r)) {
            return null;
        }
        $r = (float) $r;

        return $r > 0 && $r <= 100 ? $r : null;
    }
}
