<?php

namespace App\Support;

use App\Models\BudgetPlan;
use App\Models\Client;
use Carbon\Carbon;

/** Budget pacing (#36) and month-end forecast (#37) for one client, in its own currency. */
class Pacing
{
    /**
     * @param  Carbon|null  $today  the workspace's "today" (defaults to now in the agency time zone)
     */
    public static function month(Client $c, ?Carbon $today = null): array
    {
        $tz = $c->agency?->timezone ?: 'UTC';
        $today = ($today ?? now($tz))->copy()->startOfDay();
        $start = $today->copy()->startOfMonth();
        $daysIn = $start->daysInMonth;
        $full = $today->day - 1; // complete days so far
        $cfg = config('planning.pacing');

        $plan = BudgetPlan::for($c, $start->toDateString());
        $budget = $plan?->budget ?? $c->monthly_budget;
        $target = $plan?->target_results ?? $c->target_results;

        $mtd = $full > 0 ? MetricsQuery::totals($c, $start->toDateString(), $today->copy()->subDay()->toDateString()) : null;
        $spent = $mtd['spend'] ?? 0.0;
        $results = $mtd['main_results'] ?? 0.0;

        // Run rate: last N complete days (inside or before this month), per day.
        $n = (int) $cfg['run_rate_days'];
        $recent = MetricsQuery::totals($c, $today->copy()->subDays($n)->toDateString(), $today->copy()->subDay()->toDateString());
        $rateSpend = $recent['spend'] / $n;
        $rateResults = $recent['main_results'] / $n;
        $left = $daysIn - $full; // today included

        $forecastSpend = $spent + $rateSpend * $left;
        $forecastResults = $results + $rateResults * $left;
        $expected = $budget ? $budget * $full / $daysIn : null;
        $pace = $expected ? $spent / $expected * 100 : null;
        $tol = (float) $cfg['tolerance'];

        $status = match (true) {
            ! $budget => 'no_budget',
            $full < (int) $cfg['min_day'] => 'early',
            $pace < 100 - $tol => 'under',
            $pace > 100 + $tol => 'over',
            default => 'on',
        };

        return [
            'month' => $start->format('Y-m'), 'month_label' => $start->format('F Y'), 'days_in' => $daysIn, 'full_days' => $full, 'days_left' => $left,
            'budget' => $budget, 'from_plan' => (bool) $plan?->budget, 'target_results' => $target,
            'spent' => $spent, 'expected' => $expected, 'pace' => $pace, 'status' => $status,
            'main_type' => $mtd['main_type'] ?? $recent['main_type'], 'results' => $results,
            'cpr' => $mtd['cpr'] ?? null,
            'rate_spend' => $rateSpend, 'rate_results' => $rateResults,
            'forecast_spend' => $forecastSpend, 'forecast_results' => $forecastResults,
            'forecast_cpr' => $forecastResults > 0 ? $forecastSpend / $forecastResults : null,
            'forecast_vs_budget' => $budget ? $forecastSpend / $budget * 100 : null,
            'forecast_vs_target' => $target ? $forecastResults / $target * 100 : null,
            'daily_needed' => $budget && $left > 0 ? max(0, ($budget - $spent) / $left) : null,
        ];
    }

    public static function label(string $status): array
    {
        return [
            'no_budget' => ['No budget set', 'gray'], 'early' => ['Too early to tell', 'gray'],
            'under' => ['Under-pacing', 'warning'], 'over' => ['Over-pacing', 'danger'], 'on' => ['On pace', 'success'],
        ][$status] ?? [$status, 'gray'];
    }
}
