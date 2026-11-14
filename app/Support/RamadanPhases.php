<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\BudgetPlan;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Step 1b (E2-079, EH-19): Cross-client Ramadan 6-phase performance & pacing board.
 * Reads Ramadan & Eid al-Fitr windows strictly from MenaCalendar (including holiday_statuses expected/confirmed)
 * and workspace Ramadan hours from RamadanHours, computing per-phase spend, results, CPR, phase-over-phase delta,
 * and BudgetPlan pacing via MetricsQuery + ResultResolver + SectorPacks while enforcing Client::visibleTo and hide_spend.
 */
class RamadanPhases
{
    /** @var list<string> */
    public const PHASE_KEYS = [
        'before',
        'days_1_10',
        'days_11_20',
        'last_ten_nights',
        'eid_fitr',
        'after',
    ];

    /**
     * @return array<string, string>
     */
    public static function phaseLabels(?string $locale = null): array
    {
        $loc = $locale ?: app()->getLocale();

        return [
            'before' => __('14 days before Ramadan', [], $loc),
            'days_1_10' => __('Days 1–10', [], $loc),
            'days_11_20' => __('Days 11–20', [], $loc),
            'last_ten_nights' => __('Last ten nights', [], $loc),
            'eid_fitr' => __('Eid al-Fitr', [], $loc),
            'after' => __('14 days after Eid', [], $loc),
        ];
    }

    /**
     * Discover past and upcoming Ramadan cycles within ±15 months of the anchor date from MenaCalendar (read-only).
     *
     * @return list<array{
     *     key: string,
     *     hijri_label: string,
     *     ramadan: array{start: string, end: string, days: int, calc_start: string, status: string, source: ?string, estimated: bool},
     *     eid_fitr: array{start: string, end: string, days: int, calc_start: string, status: string, source: ?string, estimated: bool},
     *     window_start: string,
     *     window_end: string,
     *     phases: list<array{key: string, label: string, start: string, end: string, days: int, state: string}>
     * }>
     */
    public static function cycles(?CarbonInterface $today = null, ?string $locale = null): array
    {
        $loc = $locale ?: app()->getLocale();
        $anchor = ($today ? Carbon::instance($today) : now())->copy()->startOfDay();
        $todayStr = $anchor->toDateString();

        $events = MenaCalendar::events(
            $anchor->copy()->subMonths(15)->toDateString(),
            $anchor->copy()->addMonths(15)->toDateString(),
        );

        $ramadans = array_values(array_filter($events, fn (array $event): bool => $event['key'] === 'ramadan'));
        $eids = array_values(array_filter($events, fn (array $event): bool => $event['key'] === 'eid_fitr'));
        $labels = static::phaseLabels($loc);
        $cycles = [];

        foreach ($ramadans as $ramadan) {
            $ramadanStart = Carbon::parse($ramadan['start'])->startOfDay();
            $ramadanRawEnd = Carbon::parse($ramadan['end'])->startOfDay();
            $ramadanCalcStart = (string) ($ramadan['calc_start'] ?? $ramadan['start']);

            $matchedEid = null;
            foreach ($eids as $candidateEid) {
                $diffFromRamadan = $ramadanStart->diffInDays(Carbon::parse($candidateEid['start'])->startOfDay(), false);
                if ($diffFromRamadan >= 25 && $diffFromRamadan <= 35) {
                    $matchedEid = $candidateEid;
                    break;
                }
            }

            if ($matchedEid !== null) {
                $eidRawStart = Carbon::parse($matchedEid['start'])->startOfDay();
                $eidDays = max(1, (int) ($matchedEid['days'] ?? 3));
                $diffDays = $ramadanStart->diffInDays($eidRawStart, false);

                if ($diffDays >= 29 && $diffDays <= 30) {
                    $ramadanEnd = $eidRawStart->copy()->subDay();
                    $eidStart = $eidRawStart->copy();
                } else {
                    $ramadanEnd = $ramadanRawEnd->copy();
                    $eidStart = $eidRawStart->gt($ramadanEnd) ? $eidRawStart->copy() : $ramadanEnd->copy()->addDay();
                }

                $eidEnd = $eidStart->copy()->addDays($eidDays - 1);
                $eidMeta = [
                    'start' => $eidStart->toDateString(),
                    'end' => $eidEnd->toDateString(),
                    'days' => $eidDays,
                    'calc_start' => (string) ($matchedEid['calc_start'] ?? $matchedEid['start']),
                    'status' => (string) ($matchedEid['status'] ?? 'expected'),
                    'source' => isset($matchedEid['source']) && $matchedEid['source'] !== '' ? (string) $matchedEid['source'] : null,
                    'estimated' => (bool) ($matchedEid['estimated'] ?? false),
                ];
            } else {
                $ramadanEnd = $ramadanRawEnd->copy();
                $eidStart = $ramadanEnd->copy()->addDay();
                $eidEnd = $eidStart->copy()->addDays(2);
                $eidMeta = [
                    'start' => $eidStart->toDateString(),
                    'end' => $eidEnd->toDateString(),
                    'days' => 3,
                    'calc_start' => $eidStart->toDateString(),
                    'status' => (string) ($ramadan['status'] ?? 'expected'),
                    'source' => null,
                    'estimated' => (bool) ($ramadan['estimated'] ?? false),
                ];
            }

            $ramadanDays = (int) $ramadanStart->diffInDays($ramadanEnd) + 1;
            $beforeStart = $ramadanStart->copy()->subDays(14);
            $beforeEnd = $ramadanStart->copy()->subDay();
            $days1Start = $ramadanStart->copy();
            $days1End = $ramadanStart->copy()->addDays(9);
            $days11Start = $ramadanStart->copy()->addDays(10);
            $days11End = $ramadanStart->copy()->addDays(19);
            $lastTenStart = $ramadanStart->copy()->addDays(20);
            $lastTenEnd = $ramadanEnd->copy();
            $afterStart = $eidEnd->copy()->addDay();
            $afterEnd = $eidEnd->copy()->addDays(14);

            $rawPhases = [
                ['key' => 'before', 'start' => $beforeStart, 'end' => $beforeEnd],
                ['key' => 'days_1_10', 'start' => $days1Start, 'end' => $days1End],
                ['key' => 'days_11_20', 'start' => $days11Start, 'end' => $days11End],
                ['key' => 'last_ten_nights', 'start' => $lastTenStart, 'end' => $lastTenEnd],
                ['key' => 'eid_fitr', 'start' => $eidStart, 'end' => $eidEnd],
                ['key' => 'after', 'start' => $afterStart, 'end' => $afterEnd],
            ];

            $phases = [];
            foreach ($rawPhases as $phaseDef) {
                $startStr = $phaseDef['start']->toDateString();
                $endStr = $phaseDef['end']->toDateString();
                $daysCount = max(1, (int) $phaseDef['start']->diffInDays($phaseDef['end']) + 1);
                $state = match (true) {
                    $todayStr < $startStr => 'upcoming',
                    $todayStr > $endStr => 'completed',
                    default => 'active',
                };

                $phases[] = [
                    'key' => $phaseDef['key'],
                    'label' => $labels[$phaseDef['key']],
                    'start' => $startStr,
                    'end' => $endStr,
                    'days' => $daysCount,
                    'state' => $state,
                ];
            }

            $hijriLabel = MenaCalendar::hijriLabel($ramadanStart->toDateString(), $loc);
            if ($hijriLabel === '') {
                [$hijriYear] = MenaCalendar::hijriYearMonth($ramadanStart->toDateString());
                $hijriLabel = $loc === 'ar' ? "رمضان {$hijriYear}" : "Ramadan {$hijriYear}";
            }

            $cycles[] = [
                'key' => $ramadanStart->toDateString(),
                'hijri_label' => $hijriLabel,
                'ramadan' => [
                    'start' => $ramadanStart->toDateString(),
                    'end' => $ramadanEnd->toDateString(),
                    'days' => $ramadanDays,
                    'calc_start' => $ramadanCalcStart,
                    'status' => (string) ($ramadan['status'] ?? 'expected'),
                    'source' => isset($ramadan['source']) && $ramadan['source'] !== '' ? (string) $ramadan['source'] : null,
                    'estimated' => (bool) ($ramadan['estimated'] ?? false),
                ],
                'eid_fitr' => $eidMeta,
                'window_start' => $beforeStart->toDateString(),
                'window_end' => $afterEnd->toDateString(),
                'phases' => $phases,
            ];
        }

        return $cycles;
    }

    /**
     * Workspace Ramadan hours snapshot strictly from RamadanHours.
     *
     * @return array{enabled: bool, active: bool, is_ramadan_today: bool, digest_at: string, quiet_start: string, quiet_end: string}
     */
    public static function hoursSummary(Agency $agency, ?CarbonInterface $now = null): array
    {
        $local = ($now ? Carbon::instance($now) : now())->copy()->setTimezone($agency->timezone ?: 'UTC');

        return [
            'enabled' => RamadanHours::enabled($agency),
            'active' => RamadanHours::active($agency, $local),
            'is_ramadan_today' => RamadanHours::isRamadan($local->toDateString()),
            'digest_at' => RamadanHours::digestAt($agency),
            'quiet_start' => RamadanHours::QUIET[0],
            'quiet_end' => RamadanHours::QUIET[1],
        ];
    }

    /**
     * Build the full cross-client Ramadan phase board for visible active clients of an agency.
     *
     * @return array{
     *     today: string,
     *     timezone: string,
     *     cycles: list<array<string, mixed>>,
     *     selected_cycle: ?array<string, mixed>,
     *     hours: array{enabled: bool, active: bool, is_ramadan_today: bool, digest_at: string, quiet_start: string, quiet_end: string},
     *     visible_clients: array<int, string>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public static function board(
        Agency $agency,
        ?User $user,
        ?string $cycleStart = null,
        ?int $clientId = null,
        ?CarbonInterface $now = null,
        ?string $locale = null,
    ): array {
        $loc = $locale ?: app()->getLocale();
        $timezone = $agency->timezone ?: 'UTC';
        $localNow = ($now ? Carbon::instance($now) : now())->copy()->setTimezone($timezone);
        $today = $localNow->copy()->startOfDay();
        $todayStr = $today->toDateString();

        $cycles = static::cycles($today, $loc);
        $selectedCycle = static::pickCycle($cycles, $cycleStart, $todayStr);
        $hours = static::hoursSummary($agency, $localNow);

        $allVisible = CurrentClient::apply(
            Client::visibleTo($user, $agency)->where('status', 'active'),
            'clients.id',
            $agency,
        )->with('agency')->orderBy('name')->get();

        $visibleOptions = $allVisible->pluck('name', 'id')->all();
        $clients = $clientId !== null
            ? $allVisible->where('id', $clientId)->values()
            : $allVisible;

        if ($selectedCycle === null || $clients->isEmpty()) {
            return [
                'today' => $todayStr,
                'timezone' => $timezone,
                'cycles' => $cycles,
                'selected_cycle' => $selectedCycle,
                'hours' => $hours,
                'visible_clients' => $visibleOptions,
                'rows' => [],
            ];
        }

        $months = static::monthsInWindow($selectedCycle['window_start'], $selectedCycle['window_end']);
        $plansByClient = [];

        if ($months !== []) {
            $firstMonthDate = $months[0].'-01';
            $lastMonthDate = $months[array_key_last($months)].'-01';

            $planRows = BudgetPlan::query()
                ->whereIn('client_id', $clients->pluck('id')->all())
                ->whereDate('month', '>=', $firstMonthDate)
                ->whereDate('month', '<=', $lastMonthDate)
                ->get();

            foreach ($planRows as $planRow) {
                $ym = Carbon::parse($planRow->month)->format('Y-m');
                $plansByClient[(int) $planRow->client_id][$ym] = $planRow;
            }
        }

        $rows = [];
        foreach ($clients as $client) {
            $rows[] = static::clientRow($client, $selectedCycle, $plansByClient[(int) $client->id] ?? [], $todayStr, $loc);
        }

        return [
            'today' => $todayStr,
            'timezone' => $timezone,
            'cycles' => $cycles,
            'selected_cycle' => $selectedCycle,
            'hours' => $hours,
            'visible_clients' => $visibleOptions,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cycles
     * @return array<string, mixed>|null
     */
    protected static function pickCycle(array $cycles, ?string $cycleStart, string $todayStr): ?array
    {
        if ($cycles === []) {
            return null;
        }

        if ($cycleStart !== null && $cycleStart !== '') {
            foreach ($cycles as $cycle) {
                if ($cycle['key'] === $cycleStart || $cycle['ramadan']['calc_start'] === $cycleStart) {
                    return $cycle;
                }
            }
        }

        foreach ($cycles as $cycle) {
            if ($cycle['window_end'] >= $todayStr) {
                return $cycle;
            }
        }

        return $cycles[array_key_last($cycles)];
    }

    /**
     * @return list<string>
     */
    protected static function monthsInWindow(string $from, string $to): array
    {
        $cursor = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to)->startOfMonth();
        $months = [];

        while ($cursor->lte($end)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    /**
     * @param  array<string, mixed>  $cycle
     * @param  array<string, BudgetPlan>  $clientPlans
     * @return array<string, mixed>
     */
    protected static function clientRow(
        Client $client,
        array $cycle,
        array $clientPlans,
        string $todayStr,
        string $locale,
    ): array {
        $hideSpend = (bool) $client->hide_spend;
        $currency = $client->currency ?: 'USD';
        $tolerance = (float) config('planning.pacing.tolerance', 10);

        $latestDates = DailyMetric::query()
            ->where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('date', '>=', $cycle['window_start'])
            ->whereDate('date', '<=', $cycle['window_end'])
            ->pluck('date')
            ->map(fn ($d): string => substr((string) $d, 0, 10))
            ->all();

        $phaseCells = [];
        $prevStats = null;

        foreach ($cycle['phases'] as $phase) {
            $totals = MetricsQuery::totals($client, $phase['start'], $phase['end']);
            $hasData = $totals['main_type'] !== null;
            $days = max(1, (int) $phase['days']);

            $budgetInfo = $hideSpend
                ? ['planned' => null, 'expected' => null, 'from_plan' => false]
                : static::phaseBudget($client, $clientPlans, $phase['start'], $phase['end'], $phase['state'], $todayStr, $latestDates);

            if (! $hasData) {
                $phaseCells[$phase['key']] = [
                    'key' => $phase['key'],
                    'label' => $phase['label'],
                    'start' => $phase['start'],
                    'end' => $phase['end'],
                    'days' => $days,
                    'state' => $phase['state'],
                    'has_data' => false,
                    'spend' => null,
                    'spend_formatted' => null,
                    'results' => null,
                    'results_formatted' => null,
                    'result_type' => null,
                    'result_label' => null,
                    'cpr' => null,
                    'cpr_formatted' => null,
                    'spend_delta_pct' => null,
                    'results_delta_pct' => null,
                    'cpr_delta_pct' => null,
                    'planned_budget' => $budgetInfo['planned'],
                    'planned_formatted' => $budgetInfo['planned'] !== null ? ResultResolver::money($budgetInfo['planned'], $currency) : null,
                    'from_plan' => $budgetInfo['from_plan'],
                    'pace_pct' => null,
                    'pace_status' => $hideSpend ? 'hidden' : ($budgetInfo['planned'] !== null ? 'no_data' : 'no_budget'),
                ];
                $prevStats = null;

                continue;
            }

            $results = (float) $totals['main_results'];
            $resultType = (string) $totals['main_type'];
            $resultLabel = SectorPacks::resultLabel($client, $resultType, $locale);
            $perDayResults = $results / $days;
            $resultsDeltaPct = ($prevStats !== null && $prevStats['per_day_results'] > 0)
                ? round((($perDayResults / $prevStats['per_day_results']) - 1.0) * 100.0, 1)
                : null;

            if ($hideSpend) {
                $phaseCells[$phase['key']] = [
                    'key' => $phase['key'],
                    'label' => $phase['label'],
                    'start' => $phase['start'],
                    'end' => $phase['end'],
                    'days' => $days,
                    'state' => $phase['state'],
                    'has_data' => true,
                    'spend' => null,
                    'spend_formatted' => null,
                    'results' => $results,
                    'results_formatted' => number_format($results, $results == floor($results) ? 0 : 1),
                    'result_type' => $resultType,
                    'result_label' => $resultLabel,
                    'cpr' => null,
                    'cpr_formatted' => null,
                    'spend_delta_pct' => null,
                    'results_delta_pct' => $resultsDeltaPct,
                    'cpr_delta_pct' => null,
                    'planned_budget' => null,
                    'planned_formatted' => null,
                    'from_plan' => false,
                    'pace_pct' => null,
                    'pace_status' => 'hidden',
                ];
                $prevStats = [
                    'per_day_spend' => null,
                    'per_day_results' => $perDayResults,
                    'cpr' => null,
                    'result_type' => $resultType,
                ];

                continue;
            }

            $spend = (float) $totals['spend'];
            $cpr = $totals['cpr'] !== null ? (float) $totals['cpr'] : null;
            $perDaySpend = $spend / $days;

            $spendDeltaPct = ($prevStats !== null && $prevStats['per_day_spend'] !== null && $prevStats['per_day_spend'] > 0)
                ? round((($perDaySpend / $prevStats['per_day_spend']) - 1.0) * 100.0, 1)
                : null;

            $cprDeltaPct = ($prevStats !== null && $cpr !== null && $prevStats['cpr'] !== null && $prevStats['cpr'] > 0 && $prevStats['result_type'] === $resultType)
                ? round((($cpr / $prevStats['cpr']) - 1.0) * 100.0, 1)
                : null;

            $expectedBudget = $budgetInfo['expected'];
            $pacePct = ($expectedBudget !== null && $expectedBudget > 0)
                ? round(($spend / $expectedBudget) * 100.0, 1)
                : null;

            $paceStatus = match (true) {
                $pacePct === null => 'no_budget',
                $pacePct < (100.0 - $tolerance) => 'under',
                $pacePct > (100.0 + $tolerance) => 'over',
                default => 'on',
            };

            $phaseCells[$phase['key']] = [
                'key' => $phase['key'],
                'label' => $phase['label'],
                'start' => $phase['start'],
                'end' => $phase['end'],
                'days' => $days,
                'state' => $phase['state'],
                'has_data' => true,
                'spend' => $spend,
                'spend_formatted' => ResultResolver::money($spend, $currency),
                'results' => $results,
                'results_formatted' => number_format($results, $results == floor($results) ? 0 : 1),
                'result_type' => $resultType,
                'result_label' => $resultLabel,
                'cpr' => $cpr,
                'cpr_formatted' => $cpr !== null ? ResultResolver::money($cpr, $currency) : null,
                'spend_delta_pct' => $spendDeltaPct,
                'results_delta_pct' => $resultsDeltaPct,
                'cpr_delta_pct' => $cprDeltaPct,
                'planned_budget' => $budgetInfo['planned'],
                'planned_formatted' => $budgetInfo['planned'] !== null ? ResultResolver::money($budgetInfo['planned'], $currency) : null,
                'from_plan' => $budgetInfo['from_plan'],
                'pace_pct' => $pacePct,
                'pace_status' => $paceStatus,
            ];

            $prevStats = [
                'per_day_spend' => $perDaySpend,
                'per_day_results' => $perDayResults,
                'cpr' => $cpr,
                'result_type' => $resultType,
            ];
        }

        return [
            'client' => $client,
            'hide_spend' => $hideSpend,
            'currency' => $currency,
            'phases' => $phaseCells,
        ];
    }

    /**
     * Compute prorated planned budget and expected-to-date budget across a phase's calendar days from BudgetPlan
     * (falling back to the client's monthly_budget when no BudgetPlan row exists for that month).
     *
     * @param  array<string, BudgetPlan>  $clientPlans
     * @param  list<string>  $metricDates
     * @return array{planned: ?float, expected: ?float, from_plan: bool}
     */
    protected static function phaseBudget(
        Client $client,
        array $clientPlans,
        string $start,
        string $end,
        string $state,
        string $todayStr,
        array $metricDates,
    ): array {
        $phaseStart = Carbon::parse($start)->startOfDay();
        $phaseEnd = Carbon::parse($end)->startOfDay();

        $maxMetricInPhase = null;
        foreach ($metricDates as $metricDate) {
            if ($metricDate >= $start && $metricDate <= $end && ($maxMetricInPhase === null || $metricDate > $maxMetricInPhase)) {
                $maxMetricInPhase = $metricDate;
            }
        }

        $effectiveEndStr = $end;
        if ($state === 'active') {
            $yesterdayStr = Carbon::parse($todayStr)->subDay()->toDateString();
            if ($maxMetricInPhase !== null && $maxMetricInPhase >= $todayStr) {
                $effectiveEndStr = min($end, $maxMetricInPhase);
            } elseif ($yesterdayStr >= $start) {
                $effectiveEndStr = min($end, $yesterdayStr);
            } else {
                $effectiveEndStr = $start;
            }
        }

        $plannedSum = 0.0;
        $expectedSum = 0.0;
        $plannedDays = 0;
        $expectedDays = 0;
        $fromPlan = false;

        for ($d = $phaseStart->copy(); $d->lte($phaseEnd); $d->addDay()) {
            $ym = $d->format('Y-m');
            $plan = $clientPlans[$ym] ?? null;
            $monthBudget = ($plan !== null && $plan->budget !== null && (float) $plan->budget > 0)
                ? (float) $plan->budget
                : ((float) $client->monthly_budget > 0 ? (float) $client->monthly_budget : null);

            if ($monthBudget === null) {
                continue;
            }

            if ($plan !== null && $plan->budget !== null && (float) $plan->budget > 0) {
                $fromPlan = true;
            }

            $dailyShare = $monthBudget / max(1, $d->daysInMonth);
            $plannedSum += $dailyShare;
            $plannedDays++;

            if ($d->toDateString() <= $effectiveEndStr) {
                $expectedSum += $dailyShare;
                $expectedDays++;
            }
        }

        return [
            'planned' => $plannedDays > 0 ? round($plannedSum, 3) : null,
            'expected' => $expectedDays > 0 ? round($expectedSum, 3) : null,
            'from_plan' => $fromPlan,
        ];
    }

    /**
     * Format a signed percentage delta (e.g. +18% or −12%), or '—' when null.
     */
    public static function formatDelta(?float $deltaPct): string
    {
        if ($deltaPct === null) {
            return '—';
        }

        $rounded = round($deltaPct, abs($deltaPct) < 10 && fmod(abs($deltaPct), 1.0) > 0.05 ? 1 : 0);
        if ($rounded == 0.0) {
            return '0%';
        }

        $sign = $rounded > 0 ? '+' : '−';
        $decimals = fmod(abs($rounded), 1.0) > 0.01 ? 1 : 0;

        return $sign.number_format(abs($rounded), $decimals).'%';
    }
}
