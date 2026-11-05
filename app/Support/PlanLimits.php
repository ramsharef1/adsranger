<?php

namespace App\Support;

use App\Models\Agency;

/** Plan limits for an agency: seats (members + pending invites) and active ad accounts. */
class PlanLimits
{
    public static function seatsLimit(Agency $a): int
    {
        return static::capped($a, 'seats', (int) ($a->planModel?->seats ?? 3));
    }

    public static function seatsUsed(Agency $a): int
    {
        return $a->members()->count() + $a->invitations()->pending()->count();
    }

    public static function canAddSeat(Agency $a): bool
    {
        return static::seatsUsed($a) < static::seatsLimit($a);
    }

    public static function adAccountsLimit(Agency $a): int
    {
        return static::capped($a, 'ad_accounts', (int) ($a->planModel?->ad_accounts ?? 10));
    }

    /** D-61: while the trial runs, the trial cap applies when it is lower than the plan's own limit. */
    private static function capped(Agency $a, string $key, int $planLimit): int
    {
        $cap = Trial::cap($key);

        return ($cap !== null && Trial::isOn($a)) ? min($planLimit, $cap) : $planLimit;
    }

    /** Whether the limit that applies right now is the trial cap (for the quota notice wording). */
    public static function isTrialCap(Agency $a, string $key): bool
    {
        $cap = Trial::cap($key);

        return $cap !== null && Trial::isOn($a) && ($key === 'seats' ? static::seatsLimit($a) : static::adAccountsLimit($a)) === $cap;
    }

    public static function adAccountsUsed(Agency $a): int
    {
        return $a->adAccounts()->where('is_active', true)->count();
    }

    public static function canActivateAdAccount(Agency $a): bool
    {
        return static::adAccountsUsed($a) < static::adAccountsLimit($a);
    }

    public static function trialDaysLeft(Agency $a): ?int
    {
        return Trial::daysLeft($a);
    }
}
