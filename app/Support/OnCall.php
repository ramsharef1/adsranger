<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * FS-21 (R4): an alert on-call rota, one person per weekday (Fri and Sat included). New Critical alerts are assigned to
 * that day's person, and their phone hears Critical alerts even inside their quiet hours. Nobody set = nothing changes.
 */
class OnCall
{
    public static function userFor(Agency $a, ?CarbonInterface $at = null): ?User
    {
        $day = (string) ($at ?? now($a->timezone ?: 'UTC'))->copy()->setTimezone($a->timezone ?: 'UTC')->isoWeekday();
        $id = (int) (((array) ($a->on_call ?? []))[$day] ?? 0);
        if (! $id) {
            return null;
        }

        return $a->members()->whereKey($id)->first();
    }

    /** @param  array<int|string, mixed>  $days */
    public static function save(Agency $a, array $days): void
    {
        $members = $a->members()->pluck('users.id')->all();
        $clean = [];
        foreach (range(1, 7) as $d) {
            $v = (int) ($days[$d] ?? $days[(string) $d] ?? 0);
            if ($v && in_array($v, $members, true)) {
                $clean[(string) $d] = $v;
            }
        }
        $a->forceFill(['on_call' => $clean ?: null])->save();
    }
}
