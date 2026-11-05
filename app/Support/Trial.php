<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AuditLog;
use Illuminate\Support\Carbon;

/**
 * The 14-day trial (D-61): Pro tier, capped at 5 tracked ad accounts and 3 seats, no card.
 * A trial is running while the agency's status is `trial` and the end date has not passed (no end date = running).
 * After the end date the agency keeps its own plan (Starter for new signups): EX-50 locks and plan limits apply.
 */
class Trial
{
    public static function isOn(Agency $a): bool
    {
        return $a->status === 'trial' && (! $a->trial_ends_at || $a->trial_ends_at->isFuture());
    }

    public static function hasEnded(Agency $a): bool
    {
        return $a->status === 'trial' && $a->trial_ends_at && ! $a->trial_ends_at->isFuture();
    }

    public static function daysLeft(Agency $a): ?int
    {
        if (! static::isOn($a) || ! $a->trial_ends_at) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInHours($a->trial_ends_at, false) / 24));
    }

    public static function cap(string $key): ?int
    {
        $v = config("adsranger.trial_caps.$key");

        return $v === null ? null : (int) $v;
    }

    /** Extend from the current end date, or from now when it has passed. Audit-logged `agency.trial_extended`. */
    public static function extend(Agency $a, int $days): Carbon
    {
        $days = max(1, min(365, $days));
        $from = $a->trial_ends_at;
        $base = $from && $from->isFuture() ? $from->copy() : now();
        $to = $base->addDays($days);
        $a->forceFill(['trial_ends_at' => $to])->save();
        AuditLog::record('agency.trial_extended', $a, ['days' => $days, 'from' => $from?->toIso8601String(), 'to' => $to->toIso8601String()]);

        return $to;
    }
}
