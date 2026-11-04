<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\User;

/**
 * Plan-tier gating (D-30): which features an agency's plan unlocks, read from config/plan_features.php.
 * Plan limits lock (EX-50); role limits hide (EX-37) and are checked separately by the caller.
 */
class PlanGate
{
    /** The agency's effective tier key: its plan, raised to the trial tier while on trial (D-61). */
    public static function tier(Agency $agency): string
    {
        $tiers = config('plan_features.tiers');
        $tier = array_key_exists($agency->plan, $tiers) ? $agency->plan : 'starter';

        if ($agency->status === 'trial') {
            $trial = config('plan_features.trial_tier', 'starter');
            if (($tiers[$trial] ?? 0) > $tiers[$tier]) {
                $tier = $trial;
            }
        }

        return $tier;
    }

    /** Tier a feature needs, or null when the feature is not gated. */
    public static function requiredTier(string $feature): ?string
    {
        return config("plan_features.features.$feature.tier");
    }

    public static function allows(Agency $agency, string $feature): bool
    {
        $need = static::requiredTier($feature);
        if ($need === null) {
            return true;
        }
        $tiers = config('plan_features.tiers');

        return $tiers[static::tier($agency)] >= ($tiers[$need] ?? PHP_INT_MAX);
    }

    public static function locked(Agency $agency, string $feature): bool
    {
        return ! static::allows($agency, $feature);
    }

    /** Owners and admins can upgrade; other roles are told to ask the owner (EX-50 point 4). */
    public static function canUpgrade(?User $user, Agency $agency): bool
    {
        return TeamRoles::canManageTeam($user, $agency);
    }

    public static function tierLabel(?string $tier, ?string $locale = null): string
    {
        $locale = $locale ?? (app()->getLocale() === 'ar' ? 'ar' : 'en');

        return config("plan_features.tier_labels.$tier.$locale") ?? ucfirst((string) $tier);
    }

    public static function featureText(string $feature, string $field, ?string $locale = null): string
    {
        $locale = $locale ?? (app()->getLocale() === 'ar' ? 'ar' : 'en');

        return (string) (config("plan_features.features.$feature.$field.$locale") ?? config("plan_features.features.$feature.$field.en") ?? $feature);
    }

    /** Gated features grouped by the tier that unlocks them, for Settings › Plan (EX-50 point 7). */
    public static function byTier(): array
    {
        $out = array_fill_keys(array_keys(config('plan_features.tiers')), []);
        foreach (config('plan_features.features') as $key => $f) {
            $out[$f['tier']][] = $key;
        }

        return $out;
    }

    /** Whether an alert rule is available to the agency (#23: 7 rules on Starter, the rest on Pro). */
    public static function allowsAlertRule(Agency $agency, string $rule): bool
    {
        return ! in_array($rule, config('plan_features.pro_alert_rules', []), true) || static::allows($agency, 'alert_rules_pro');
    }
}
