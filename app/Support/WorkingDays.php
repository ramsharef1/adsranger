<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * DS-26 (R5): compare periods by working days per country, with weekend changes over time (the UAE moved from
 * Fri–Sat to Sat–Sun in 2022), so a period with more working days isn't read as growth.
 */
class WorkingDays
{
    /** @return list<int> ISO weekdays that were the weekend in this country on this date */
    public static function weekendOn(?string $country, string $date): array
    {
        $c = strtoupper((string) $country);
        foreach (config("push.weekend_history.$c", []) as $h) {
            if ($date <= $h['until']) {
                return $h['days'];
            }
        }
        $map = config('push.weekend', []);

        return $map[$c] ?? $map['default'] ?? [5, 6];
    }

    public static function count(?string $country, string $from, string $to): int
    {
        $n = 0;
        foreach (CarbonPeriod::create($from, $to) as $d) {
            if (! in_array($d->isoWeekday(), static::weekendOn($country, $d->toDateString()), true)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * Per-working-day comparison of two periods, or null when both have the same number of working days.
     *
     * @param  array<string, mixed>  $now  MetricsQuery totals
     * @param  array<string, mixed>  $before
     * @return array{days_now: int, days_before: int, spend_now: float, spend_before: float, spend_pct: ?float, results_now: float, results_before: float, results_pct: ?float}|null
     */
    public static function compare(?string $country, string $from, string $to, string $pf, string $pt, array $now, array $before): ?array
    {
        $a = static::count($country, $from, min($to, Carbon::today()->toDateString()));
        $b = static::count($country, $pf, $pt);
        if ($a === $b || $a === 0 || $b === 0) {
            return null;
        }
        $pct = fn ($x, $y) => $y > 0 ? round(($x / $y - 1) * 100, 1) : null;
        $sn = (float) $now['spend'] / $a;
        $sb = (float) $before['spend'] / $b;
        $rn = (float) $now['main_results'] / $a;
        $rb = (float) $before['main_results'] / $b;

        return ['days_now' => $a, 'days_before' => $b, 'spend_now' => $sn, 'spend_before' => $sb, 'spend_pct' => $pct($sn, $sb),
            'results_now' => $rn, 'results_before' => $rb, 'results_pct' => $pct($rn, $rb)];
    }
}
