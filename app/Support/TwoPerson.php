<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\User;

/**
 * DS-35 (R4, D-305): when a workspace has two or more owners / admins, deleting a client and downloading all workspace
 * data need a second owner or admin to approve first (the request expires after 48 hours; an approval is used once,
 * by the person who asked). A one-person agency is never blocked; it confirms by typing instead.
 */
class TwoPerson
{
    public const ACTIONS = ['delete_client', 'export_workspace'];

    public const HOURS = 48;

    public static function needed(Agency $a): bool
    {
        return $a->members()->wherePivotIn('role', ['owner', 'admin'])->count() >= 2;
    }

    /** @return array<string, string> */
    public static function actionLabels(): array
    {
        return ['delete_client' => __('Delete client'), 'export_workspace' => __('Download all workspace data')];
    }

    public static function pending(Agency $a, string $action, ?int $subjectId, User $user): ?ApprovalRequest
    {
        return static::scope($a, $action, $subjectId, $user)->where('status', 'pending')->where('expires_at', '>', now())->first();
    }

    public static function approved(Agency $a, string $action, ?int $subjectId, User $user): ?ApprovalRequest
    {
        return static::scope($a, $action, $subjectId, $user)->where('status', 'approved')->where('expires_at', '>', now())->first();
    }

    public static function request(Agency $a, string $action, ?int $subjectId, ?string $subjectName, User $user): ApprovalRequest
    {
        $r = static::pending($a, $action, $subjectId, $user) ?? ApprovalRequest::create(['agency_id' => $a->id, 'action' => $action, 'subject_id' => $subjectId,
            'subject_name' => $subjectName, 'requested_by' => $user->id, 'expires_at' => now()->addHours(self::HOURS)]);
        AuditLog::record('approval.requested', $a, ['request' => $r->id, 'action' => $action, 'subject' => $subjectId]);

        return $r;
    }

    /** A second owner / admin (never the requester) approves or declines; approval gives the requester 48 hours to act. */
    public static function decide(ApprovalRequest $r, User $by, bool $approve): bool
    {
        $a = Agency::find($r->agency_id);
        if (! $a || $r->status !== 'pending' || $r->expires_at->isPast() || $r->requested_by === $by->id
            || ! in_array($by->roleIn($a), ['owner', 'admin'], true)) {
            return false;
        }
        $r->update(['status' => $approve ? 'approved' : 'declined', 'decided_by' => $by->id, 'decided_at' => now(), 'expires_at' => $approve ? now()->addHours(self::HOURS) : $r->expires_at]);
        AuditLog::record($approve ? 'approval.approved' : 'approval.declined', $a, ['request' => $r->id, 'action' => $r->action]);

        return true;
    }

    /** True when the action may run now; an approval is spent here. */
    public static function consume(Agency $a, string $action, ?int $subjectId, User $user): bool
    {
        if (! static::needed($a)) {
            return true;
        }
        $r = static::approved($a, $action, $subjectId, $user);
        if (! $r) {
            return false;
        }
        $r->update(['status' => 'used', 'used_at' => now()]);

        return true;
    }

    /** Requests this person could decide (pending, not theirs, not expired). */
    public static function forApprover(Agency $a, User $user)
    {
        if (! in_array($user->roleIn($a), ['owner', 'admin'], true)) {
            return collect();
        }

        return ApprovalRequest::with('requester')->where('agency_id', $a->id)->where('status', 'pending')->where('expires_at', '>', now())
            ->where('requested_by', '!=', $user->id)->latest()->get();
    }

    protected static function scope(Agency $a, string $action, ?int $subjectId, User $user)
    {
        return ApprovalRequest::where('agency_id', $a->id)->where('action', $action)->where('requested_by', $user->id)
            ->when($subjectId, fn ($q) => $q->where('subject_id', $subjectId), fn ($q) => $q->whereNull('subject_id'));
    }
}
