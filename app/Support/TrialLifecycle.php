<?php

namespace App\Support;

use App\Mail\TrialLifecycleMail;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * D-61 daily trial lifecycle, for **self-signup agencies only** (signup_source = self_signup). Agencies that came
 * in by invite or were created by an admin are never selected here, so they are never locked, closed or deleted.
 *
 * Per agency, each step at most once (timestamps on the agency make a second run a no-op):
 * 1. 3 days before the end: "trial ends in 3 days" mail.
 * 2. End date passed: status `read_only` (audit `agency.trial_readonly`) + "trial ended" mail.
 * 3. 14 days later: status `expired` (audit `agency.trial_expired`), the workspace closes.
 * 4. 7 days before deletion: "data deleted in 7 days" mail to the owner (audit `agency.deletion_scheduled`).
 * 5. 30 days after closing (and never sooner than 7 days after that mail): delete per `trial.delete_mode`
 *    (audit `agency.deleted`): `archive` (default) = soft delete, reversible from /admin; `delete` = purge.
 * 6. Archives older than `trial.purge_after_days` are purged (null = keep until purged by hand).
 */
class TrialLifecycle
{
    /** @return array<string, int> counts per step (for logs and tests) */
    public static function run(): array
    {
        $c = ['ending' => 0, 'read_only' => 0, 'expired' => 0, 'deletion_notice' => 0, 'deleted' => 0, 'purged' => 0];

        Agency::withTrashed()->where('signup_source', 'self_signup')->whereNotNull('trial_ends_at')
            ->where(fn ($q) => $q->whereIn('status', Trial::LIFECYCLE_STATUSES)->orWhereNotNull('deleted_at'))
            ->orderBy('id')->each(function (Agency $a) use (&$c) {
                static::step($a, $c);
            });

        return $c;
    }

    protected static function step(Agency $a, array &$c): void
    {
        $stage = Trial::stage($a);

        if ($stage === Trial::ARCHIVED) {
            $days = config('trial.purge_after_days');
            if ($days !== null && $days !== '' && $a->deleted_at->lte(now()->subDays((int) $days))) {
                static::purge($a, 'archive_purged');
                $c['purged']++;
            }

            return;
        }

        if ($stage === Trial::RUNNING) {
            $notice = (int) config('trial.ending_notice_days', 3);
            if ($a->trial_ends_at && ! $a->trial_ending_notified_at && $a->trial_ends_at->lte(now()->addDays($notice))) {
                static::mail($a, 'ending', static::planUrl($a));
                $a->forceFill(['trial_ending_notified_at' => now()])->saveQuietly();
                $c['ending']++;
            }

            return;
        }

        if ($stage === Trial::READ_ONLY || $stage === Trial::EXPIRED) {
            if (! $a->trial_readonly_at) {
                $a->forceFill(['status' => 'read_only', 'trial_readonly_at' => now()])->saveQuietly();
                AuditLog::record('agency.trial_readonly', $a, ['trial_ends_at' => $a->trial_ends_at->toIso8601String(),
                    'read_only_until' => Trial::readOnlyUntil($a)->toIso8601String()]);
                $c['read_only']++;
            }
            if (! $a->trial_ended_notified_at) {
                static::mail($a, 'ended', static::planUrl($a));
                $a->forceFill(['trial_ended_notified_at' => now()])->saveQuietly();
            }
        }

        if ($stage !== Trial::EXPIRED) {
            return;
        }

        if (! $a->trial_expired_at) {
            $a->forceFill(['status' => 'expired', 'trial_expired_at' => now()])->saveQuietly();
            AuditLog::record('agency.trial_expired', $a, ['delete_on' => Trial::deleteOn($a)->toIso8601String()]);
            $c['expired']++;
        }

        if (! $a->deletion_notified_at && now()->gte(Trial::deletionNoticeOn($a))) {
            static::mail($a, 'deletion', route('filament.app.export.workspace', ['tenant' => $a->slug]));
            $a->forceFill(['deletion_notified_at' => now()])->saveQuietly();
            AuditLog::record('agency.deletion_scheduled', $a, ['delete_on' => static::dueOn($a)->toIso8601String(), 'mode' => static::mode()]);
            $c['deletion_notice']++;
        }

        // The owner always gets the full notice period, even if a daily run was missed.
        if ($a->deletion_notified_at && now()->gte(static::dueOn($a))) {
            if (static::mode() === 'delete') {
                static::purge($a, 'deleted');
            } else {
                static::archive($a);
            }
            $c['deleted']++;
        }
    }

    public static function mode(): string
    {
        return config('trial.delete_mode') === 'delete' ? 'delete' : 'archive';
    }

    /** Deletion day: D-61 date, but never sooner than the notice period after the notice was sent. */
    public static function dueOn(Agency $a)
    {
        $due = Trial::deleteOn($a);
        $notice = $a->deletion_notified_at?->copy()->addDays((int) config('trial.deletion_notice_days', 7));

        return $notice && $notice->gt($due) ? $notice : $due;
    }

    /** Self-serve workspace closure by the workspace owner (#79): reversible soft-delete archive + audit log. */
    public static function archiveSelfServe(Agency $a, User $actor): void
    {
        AgencyInvitation::where('agency_id', $a->id)->pending()->update(['expires_at' => now()]);
        $a->delete();
        $days = config('trial.purge_after_days');
        AuditLog::record('agency.deleted', $a, [
            'mode' => 'self_serve_archive',
            'by_user_id' => $actor->id,
            'purge_after_days' => $days === null || $days === '' ? null : (int) $days,
        ]);
    }

    /** Self-serve personal account deletion (#79): detaches memberships and removes user row + audit log. */
    public static function deletePersonalAccount(User $user): void
    {
        if ($user->isSuperAdmin()) {
            throw new \RuntimeException('Super admin accounts cannot be deleted from the workspace panel.');
        }

        $ownsActive = $user->agencies()->wherePivot('role', 'owner')->exists();
        if ($ownsActive) {
            throw new \RuntimeException('Delete or transfer your owned workspaces before deleting your account.');
        }

        DB::transaction(function () use ($user) {
            $uid = $user->id;
            DB::table('agency_user')->where('user_id', $uid)->delete();
            DB::table('client_user')->where('user_id', $uid)->delete();
            if (\Illuminate\Support\Facades\Schema::hasTable('push_subscriptions')) {
                DB::table('push_subscriptions')->where('user_id', $uid)->delete();
            }
            AuditLog::create([
                'user_id' => null,
                'action' => 'user.deleted',
                'subject_type' => User::class,
                'subject_id' => $uid,
                'meta' => ['mode' => 'self_serve'],
                'ip' => request()?->ip(),
            ]);
            $user->delete();
        });
    }

    /** Reversible: soft delete, pending invites expired; every row stays until the purge. */
    protected static function archive(Agency $a): void
    {
        AgencyInvitation::where('agency_id', $a->id)->pending()->update(['expires_at' => now()]); // pending invites stop working
        $a->delete();
        $days = config('trial.purge_after_days');
        AuditLog::record('agency.deleted', $a, ['mode' => 'archive', 'purge_after_days' => $days === null || $days === '' ? null : (int) $days]);
    }

    /**
     * Not reversible: delete the agency and, through the foreign keys, every tenant row (clients, accounts,
     * metrics, alerts, links, reports …). Member accounts that belong to no other workspace are deleted too
     * (never a super admin). The audit log keeps the event with counts only, no personal data.
     */
    protected static function purge(Agency $a, string $mode): void
    {
        DB::transaction(function () use ($a, $mode) {
            $memberIds = $a->members()->pluck('users.id');
            $orphans = User::whereIn('id', $memberIds)->where(fn ($q) => $q->whereNull('role')->orWhere('role', '!=', 'super_admin'))
                ->whereDoesntHave('agencies', fn ($q) => $q->withTrashed()->where('agencies.id', '!=', $a->id))->pluck('id');
            $id = $a->id;
            $a->forceDelete();
            User::whereIn('id', $orphans)->delete();
            AuditLog::create(['user_id' => null, 'action' => 'agency.deleted', 'subject_type' => Agency::class, 'subject_id' => $id,
                'meta' => ['mode' => $mode, 'users_deleted' => $orphans->count()], 'ip' => null]);
        });
    }

    protected static function mail(Agency $a, string $kind, string $url): void
    {
        $owner = $a->owner ?? $a->members()->wherePivot('role', 'owner')->first();
        if ($owner) {
            Mail::to($owner->email)->queue(new TrialLifecycleMail($a, $owner, $kind, $url));
        }
    }

    protected static function planUrl(Agency $a): string
    {
        return route('filament.app.pages.plan', ['tenant' => $a->slug]);
    }
}
