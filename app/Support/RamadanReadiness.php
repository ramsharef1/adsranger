<?php

namespace App\Support;

use App\Alerts\AlertEngine;
use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\BudgetPlan;
use App\Models\Client;
use App\Models\ClientMonthNote;
use App\Models\DailyMetric;
use App\Models\ReportSchedule;
use App\Models\User;
use Carbon\Carbon;

/**
 * FS-39 (R4): before Ramadan, one score per client with the missing steps: Ramadan-month budget planned, targets set,
 * data fresh (connected and synced, or uploaded) in the last 3 days, a report schedule, alerts on, and next month's plan
 * written. Counts what is in the app; it does not judge the plan itself.
 */
class RamadanReadiness
{
    /** @return array{start: string, days: int, estimated: bool}|null the next Ramadan start (within ~13 months) */
    public static function next(?Carbon $today = null): ?array
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $e = collect(MenaCalendar::events($today->toDateString(), $today->copy()->addMonths(13)->toDateString()))
            ->first(fn ($e) => $e['key'] === 'ramadan' && $e['start'] >= $today->toDateString());

        return $e ? ['start' => $e['start'], 'days' => (int) $today->diffInDays($e['start']), 'estimated' => (bool) $e['estimated']] : null;
    }

    /** @return list<array{client: Client, score: int, done: int, total: int, missing: list<string>}> lowest score first */
    public static function for(Agency $a, ?User $user, string $ramadanStart): array
    {
        $month = substr($ramadanStart, 0, 7);
        $alertsOn = collect(AlertEngine::rules($a))->where('enabled', true)->count() > 0;
        $out = [];
        foreach (Client::visibleTo($user, $a)->where('status', 'active')->orderBy('name')->get() as $c) {
            $checks = [
                'budget' => (bool) BudgetPlan::for($c, $month)?->budget || (float) $c->monthly_budget > 0,
                'season_budget' => (bool) BudgetPlan::for($c, $month)?->budget,
                'targets' => (bool) ($c->target_cpr || $c->target_results || $c->target_roas),
                'fresh' => AdAccount::where('client_id', $c->id)->where('last_synced_at', '>=', now()->subDays(3))->exists()
                    || DailyMetric::where('client_id', $c->id)->where('source', 'import')->where('created_at', '>=', now()->subDays(3))->exists(),
                'report' => ReportSchedule::where('client_id', $c->id)->where('active', true)->exists(),
                'alerts' => $alertsOn,
                'plan' => ClientMonthNote::where('client_id', $c->id)->where('month', $month)->whereNotNull('next')->exists()
                    || ClientMonthNote::where('client_id', $c->id)->where('month', Carbon::parse($month.'-01')->subMonthNoOverflow()->format('Y-m'))->whereNotNull('next')->exists(),
            ];
            $done = count(array_filter($checks));
            $missing = array_values(array_intersect_key(static::labels(), array_filter($checks, fn ($v) => ! $v)));
            $out[] = ['client' => $c, 'score' => (int) round($done / count($checks) * 100), 'done' => $done, 'total' => count($checks), 'missing' => $missing];
        }
        usort($out, fn ($x, $y) => [$x['score'], $x['client']->name] <=> [$y['score'], $y['client']->name]);

        return $out;
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return ['budget' => __('Monthly budget set'), 'season_budget' => __('Ramadan month budget planned (Budget planner)'), 'targets' => __('Targets set'),
            'fresh' => __('Fresh data in the last 3 days'), 'report' => __('Report schedule on'), 'alerts' => __('Alert rules on'),
            'plan' => __('Plan for the month written (month notes)')];
    }
}
