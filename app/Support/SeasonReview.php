<?php

namespace App\Support;

use App\Models\BudgetPlan;
use App\Models\Client;
use App\Models\User;
use App\Support\AiWorkspace\Brain;
use Carbon\Carbon;

/**
 * FS-22 (R4): after a season (Ramadan, Eid, Dhul Hijjah …): plan vs actual by week, against the 28 days before, and what
 * over- or under-spent. Can be kept in the client brain as a dated learning for next year's plan.
 */
class SeasonReview
{
    public const KEYS = ['ramadan', 'eid_fitr', 'dhul_hijjah', 'eid_adha', 'white_friday'];

    /** The most recent season that ended in the last 90 days. @return array<string, mixed>|null */
    public static function latest(Client $c): ?array
    {
        $tz = $c->agency?->timezone ?: 'UTC';
        $today = now($tz)->toDateString();
        $e = collect(MenaCalendar::events(now($tz)->subDays(120)->toDateString(), $today))
            ->filter(fn ($e) => in_array($e['key'], self::KEYS, true) && $e['end'] < $today && $e['end'] >= now($tz)->subDays(90)->toDateString())
            // Prefer a full season (7+ days, e.g. Ramadan) over a short holiday that ended just after it (Eid).
            ->sortByDesc(fn ($e) => [$e['days'] >= 7 ? 1 : 0, $e['end']])->first();

        return $e ? static::for($c, $e) : null;
    }

    /** @param  array{key: string, label: string, ar: string, start: string, end: string, days: int}  $e */
    public static function for(Client $c, array $e): array
    {
        $start = Carbon::parse($e['start']);
        $end = Carbon::parse($e['end']);
        $base = MetricsQuery::totals($c, $start->copy()->subDays(28)->toDateString(), $start->copy()->subDay()->toDateString());
        $perDayBase = $base['spend'] / 28;
        $weeks = [];
        for ($w = $start->copy(); $w->lte($end); $w->addDays(7)) {
            $to = $w->copy()->addDays(6)->min($end);
            $t = MetricsQuery::totals($c, $w->toDateString(), $to->toDateString());
            $days = $w->diffInDays($to) + 1;
            $plan = BudgetPlan::for($c, $w->format('Y-m'))?->budget ?? $c->monthly_budget;
            $planned = $plan ? (float) $plan / $w->daysInMonth * $days : null;
            $weeks[] = ['from' => $w->toDateString(), 'to' => $to->toDateString(), 'spend' => (float) $t['spend'], 'planned' => $planned,
                'results' => (float) $t['main_results'], 'cpr' => $t['cpr'], 'vs_plan' => $planned ? round(($t['spend'] / $planned - 1) * 100) : null];
        }
        $all = MetricsQuery::totals($c, $e['start'], $e['end']);

        return ['key' => $e['key'], 'label' => app()->getLocale() === 'ar' ? $e['ar'] : $e['label'], 'start' => $e['start'], 'end' => $e['end'],
            'weeks' => $weeks, 'spend' => (float) $all['spend'], 'results' => (float) $all['main_results'], 'cpr' => $all['cpr'],
            'base_cpr' => $base['cpr'], 'spend_vs_before' => $perDayBase > 0 ? round((($all['spend'] / max(1, $e['days'])) / $perDayBase - 1) * 100) : null,
            'planned' => array_sum(array_filter(array_column($weeks, 'planned'))) ?: null];
    }

    /** One dated learning for the client brain («ذاكرة العميل»); plain text, numbers from the app only. */
    public static function text(Client $c, array $r): string
    {
        $cur = $c->currency ?: 'USD';
        $lines = [$r['label'].' '.$r['start'].' → '.$r['end'].':'];
        if (! $c->hide_spend) {
            $lines[] = 'Spend '.number_format($r['spend'], 2).' '.$cur.($r['planned'] ? ' vs plan '.number_format($r['planned'], 2).' '.$cur : '')
                .($r['spend_vs_before'] !== null ? ' ('.($r['spend_vs_before'] > 0 ? '+' : '').$r['spend_vs_before'].'% per day vs the 28 days before)' : '').'.';
        }
        $lines[] = 'Results '.number_format($r['results']).($r['cpr'] !== null && ! $c->hide_spend ? ', cost per result '.number_format((float) $r['cpr'], 2).' '.$cur
            .($r['base_cpr'] ? ' (before: '.number_format((float) $r['base_cpr'], 2).')' : '') : '').'.';

        return implode("\n", $lines);
    }

    public static function remember(Client $c, array $r, ?User $by): void
    {
        Brain::save($c, ['type' => 'learning', 'title' => mb_substr('Season review: '.$r['label'].' '.substr($r['start'], 0, 4), 0, 200),
            'body' => static::text($c, $r), 'playbook' => 'season_review'], $by);
    }
}
