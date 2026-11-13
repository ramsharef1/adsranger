<?php

namespace App\Support;

use App\Models\Agency;
use Carbon\CarbonInterface;

/**
 * PS-19 (R5, D-307): during Ramadan the daily summary moves to after iftar (default 21:00) and the default push quiet
 * hours move to 03:00–12:00. On by default; it switches itself on and off from the Ramadan dates. An agency can turn it
 * off or change the time. A member who chose their own quiet hours keeps them.
 */
class RamadanHours
{
    public const DEFAULT_DIGEST = '21:00';

    public const QUIET = ['03:00', '12:00'];

    /** @return array<string, string> */
    public static function digestTimes(): array
    {
        return collect(['19:00', '20:00', '21:00', '22:00', '23:00'])->mapWithKeys(fn ($t) => [$t => $t])->all();
    }

    public static function enabled(Agency $a): bool
    {
        return $a->ramadan_hours === null || (bool) $a->ramadan_hours;
    }

    public static function isRamadan(string $date): bool
    {
        return collect(MenaCalendar::events($date, $date))->contains(fn ($e) => $e['key'] === 'ramadan');
    }

    public static function active(Agency $a, ?CarbonInterface $now = null): bool
    {
        $local = ($now ?? now())->copy()->setTimezone($a->timezone ?: 'UTC');

        return static::enabled($a) && static::isRamadan($local->toDateString());
    }

    public static function digestAt(Agency $a): string
    {
        $t = (string) $a->ramadan_digest_at;

        return array_key_exists($t, static::digestTimes()) ? $t : self::DEFAULT_DIGEST;
    }

    /** Local time from which today's digest may go out: 08:00 normally, the Ramadan time while the mode is active. */
    public static function digestFrom(Agency $a, CarbonInterface $now): string
    {
        return static::active($a, $now) ? static::digestAt($a) : '08:00';
    }
}
