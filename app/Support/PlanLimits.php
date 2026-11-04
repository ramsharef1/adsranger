<?php

namespace App\Support;

use App\Models\Agency;

/** Plan limits for an agency: seats (members + pending invites) and active ad accounts. */
class PlanLimits
{
    public static function seatsLimit(Agency $a): int
    {
        return (int) ($a->planModel?->seats ?? 3);
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
        return (int) ($a->planModel?->ad_accounts ?? 10);
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
        if ($a->status !== 'trial' || ! $a->trial_ends_at) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInHours($a->trial_ends_at, false) / 24));
    }
}
