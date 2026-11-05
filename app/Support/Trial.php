<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AuditLog;
use Illuminate\Support\Carbon;

/**
 * The 14-day trial (D-61): Pro tier, capped at 5 tracked ad accounts and 3 seats, no card.
 * A trial is running while the agency's status is `trial` and the end date has not passed (no end date = running).
 *
 * After the end date:
 * - **Self-signup agencies** (signup_source = self_signup) follow the D-61 lifecycle: 14 days read-only →
 *   closed (status `expired`) for 30 days → archived, then purged (config/trial.php, App\Support\TrialLifecycle).
 * - **Invited and admin-created agencies are never locked, closed or deleted** by the lifecycle: they keep their
 *   own plan (EX-50 locks and plan limits apply), exactly as before. stage() returns `none` for them.
 */
class Trial
{
    /** Lifecycle stages. */
    public const NONE = 'none';         // not in the lifecycle (invited/admin agency, paying, suspended)

    public const RUNNING = 'trial';

    public const READ_ONLY = 'read_only';

    public const EXPIRED = 'expired';

    public const ARCHIVED = 'archived';

    /** Statuses the lifecycle moves a self-signup agency through. */
    public const LIFECYCLE_STATUSES = ['trial', 'read_only', 'expired'];

    public static function isOn(Agency $a): bool
    {
        return $a->status === 'trial' && (! $a->trial_ends_at || $a->trial_ends_at->isFuture());
    }

    public static function hasEnded(Agency $a): bool
    {
        return in_array($a->status, static::LIFECYCLE_STATUSES, true) && $a->trial_ends_at && ! $a->trial_ends_at->isFuture();
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

    /** Only self-signup agencies are ever locked, closed or deleted at trial end (D-61). */
    public static function lifecycleApplies(Agency $a): bool
    {
        return $a->signup_source === 'self_signup';
    }

    /**
     * Where a self-signup agency is in the D-61 lifecycle, from its dates (so a late daily run never leaves a
     * workspace writable past its end date). The daily run (TrialLifecycle) records the same moves in `status`.
     */
    public static function stage(Agency $a): string
    {
        if (! static::lifecycleApplies($a)) {
            return static::NONE;
        }
        if ($a->trashed()) {
            return static::ARCHIVED;
        }
        if (! in_array($a->status, static::LIFECYCLE_STATUSES, true)) {
            return static::NONE; // active (paying) or suspended: handled elsewhere
        }
        if (! $a->trial_ends_at || $a->trial_ends_at->isFuture()) {
            return static::RUNNING;
        }

        return now()->lt(static::readOnlyUntil($a)) ? static::READ_ONLY : static::EXPIRED;
    }

    /** Read-only for viewing: no change of any kind, no syncs, no alert runs, no scheduled emails. */
    public static function isReadOnly(Agency $a): bool
    {
        return in_array(static::stage($a), [static::READ_ONLY, static::EXPIRED, static::ARCHIVED], true);
    }

    /** Closed: only the "trial ended" page, Plan & usage and the data export open. */
    public static function isClosed(Agency $a): bool
    {
        return in_array(static::stage($a), [static::EXPIRED, static::ARCHIVED], true);
    }

    /** Syncs, health checks, alert runs and scheduled reports stop for read-only, closed and archived agencies. */
    public static function jobsStopped(?Agency $a): bool
    {
        return ! $a || $a->trashed() || static::isReadOnly($a);
    }

    public static function readOnlyUntil(Agency $a): ?Carbon
    {
        return $a->trial_ends_at?->copy()->addDays((int) config('trial.read_only_days', 14));
    }

    /** The day the closed workspace's data is deleted (archived, or purged in `delete` mode). */
    public static function deleteOn(Agency $a): ?Carbon
    {
        return static::readOnlyUntil($a)?->addDays((int) config('trial.keep_days', 30));
    }

    public static function deletionNoticeOn(Agency $a): ?Carbon
    {
        return static::deleteOn($a)?->subDays((int) config('trial.deletion_notice_days', 7));
    }

    /**
     * Extend from the current end date, or from now when it has passed. Audit-logged `agency.trial_extended`.
     * D-61: this also lifts a read-only, closed or archived self-signup agency back to a running trial
     * (status `trial`, restored from the archive, notice flags reset so the "ends in 3 days" mail goes again).
     */
    public static function extend(Agency $a, int $days): Carbon
    {
        $days = max(1, min(365, $days));
        $from = $a->trial_ends_at;
        $stage = static::stage($a);
        $base = $from && $from->isFuture() ? $from->copy() : now();
        $to = $base->addDays($days);
        if ($a->trashed()) {
            $a->restore();
        }
        $fill = ['trial_ends_at' => $to];
        if (in_array($a->status, ['read_only', 'expired'], true) || in_array($stage, [static::READ_ONLY, static::EXPIRED, static::ARCHIVED], true)) {
            $fill += ['status' => 'trial', 'trial_readonly_at' => null, 'trial_expired_at' => null,
                'trial_ending_notified_at' => null, 'trial_ended_notified_at' => null, 'deletion_notified_at' => null];
        }
        $a->forceFill($fill)->save();
        AuditLog::record('agency.trial_extended', $a, ['days' => $days, 'from' => $from?->toIso8601String(), 'to' => $to->toIso8601String(),
            'lifted_from' => in_array($stage, [static::READ_ONLY, static::EXPIRED, static::ARCHIVED], true) ? $stage : null]);

        return $to;
    }

    /** Short label for a stage (admin column, Plan & usage). */
    public static function stageLabel(string $stage): string
    {
        return match ($stage) {
            static::RUNNING => __('Trial running'),
            static::READ_ONLY => __('Read-only (trial ended)'),
            static::EXPIRED => __('Closed (trial ended)'),
            static::ARCHIVED => __('Archived'),
            default => '—',
        };
    }
}
