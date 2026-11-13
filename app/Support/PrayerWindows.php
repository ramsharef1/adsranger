<?php

namespace App\Support;

use App\Models\Agency;
use Carbon\CarbonInterface;

/**
 * WK-12 (R5, D-308): non-critical pushes wait during Friday prayer and, in Ramadan, Taraweeh. Fixed windows in the
 * workspace's local time, labelled approximate; Critical alerts still send. On by default with Ramadan hours' switch
 * pattern (null = on).
 */
class PrayerWindows
{
    public static function enabled(Agency $a): bool
    {
        return $a->prayer_quiet === null || (bool) $a->prayer_quiet;
    }

    public static function now(Agency $a, ?CarbonInterface $at = null): ?string
    {
        if (! static::enabled($a)) {
            return null;
        }
        $local = ($at ?? now())->copy()->setTimezone($a->timezone ?: 'UTC');
        $t = $local->format('H:i');
        $j = config('push.prayer_windows.jumuah');
        if ($local->isoWeekday() === (int) $j['day'] && $t >= $j['from'] && $t < $j['to']) {
            return 'jumuah';
        }
        $tr = config('push.prayer_windows.taraweeh');
        if ($t >= $tr['from'] && $t < $tr['to'] && RamadanHours::isRamadan($local->toDateString())) {
            return 'taraweeh';
        }

        return null;
    }
}
