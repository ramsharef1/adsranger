<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\User;

/**
 * #71 "View as" preview switcher for workspace owners, admins, and super admins.
 * Stores per-workspace preview state in session (`view_as.{agency_id}`) as either:
 * - `r:admin` | `r:manager` | `r:analyst` (role preview)
 * - `u:{user_id}` (specific workspace member preview, including their client restrictions)
 */
class ViewAs
{
    private static bool $resolving = false;

    public static function key(Agency $agency): string
    {
        return 'view_as.'.$agency->id;
    }

    /** Can the real authenticated user use "View as" in this workspace? */
    public static function canUse(?User $user, ?Agency $agency): bool
    {
        if (! $user || ! $agency) {
            return false;
        }
        // D-163 (UX-97): held back for agencies until the fix passes the re-check.
        if (! Modules::on('view_as', $user, $agency)) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($user->rawRoleIn($agency), ['owner', 'admin'], true);
    }

    /** Sync `?view_as=` query param into session if permitted, and return active preview descriptor or null. */
    public static function current(?User $user, ?Agency $agency): ?array
    {
        if (self::$resolving || ! $user || ! $agency) {
            return null;
        }

        self::$resolving = true;
        try {
            if (! self::canUse($user, $agency)) {
                return null;
            }

            $req = app()->bound('request') ? request() : null;
            $hasSession = $req && $req->hasSession();

            if ($req && $req->query->has('view_as')) {
                $pick = trim((string) $req->query('view_as'));
                if ($pick === '' || $pick === 'off' || $pick === 'reset') {
                    if ($hasSession) {
                        $req->session()->forget(self::key($agency));
                    }
                    return null;
                }
                if (self::isValidTarget($pick, $agency, $user) && $hasSession) {
                    $req->session()->put(self::key($agency), $pick);
                }
            }

            $stored = $hasSession ? $req->session()->get(self::key($agency)) : null;
            if (! is_string($stored) || $stored === '') {
                return null;
            }
            if (! self::isValidTarget($stored, $agency, $user)) {
                $req->session()->forget(self::key($agency));

                return null;
            }

            return self::describe($stored, $agency);
        } finally {
            self::$resolving = false;
        }
    }

    public static function set(Agency $agency, string $target): void
    {
        if (app()->bound('request') && request()->hasSession()) {
            request()->session()->put(self::key($agency), $target);
        }
    }

    public static function clear(Agency $agency): void
    {
        if (app()->bound('request') && request()->hasSession()) {
            request()->session()->forget(self::key($agency));
        }
    }

    public static function effectiveRole(User $user, Agency $agency): ?string
    {
        $cur = self::current($user, $agency);
        if ($cur) {
            return $cur['role'];
        }

        return $user->rawRoleIn($agency);
    }

    public static function effectiveUser(?User $user, Agency $agency): ?User
    {
        if (! $user) {
            return null;
        }
        $cur = self::current($user, $agency);
        if ($cur && ! empty($cur['user'])) {
            return $cur['user'];
        }

        return $user;
    }

    private static function isValidTarget(string $target, Agency $agency, User $actor): bool
    {
        if (str_starts_with($target, 'r:')) {
            $role = substr($target, 2);
            return in_array($role, ['admin', 'manager', 'analyst'], true) && self::mayPreviewRole($actor, $agency, $role);
        }
        if (str_starts_with($target, 'u:')) {
            $uid = (int) substr($target, 2);
            if ($uid <= 0 || $uid === (int) $actor->id) {
                return false;
            }
            $member = $agency->members()->where('users.id', $uid)->first();

            return $member !== null && self::mayPreviewRole($actor, $agency, $member->pivot?->role ?: 'analyst');
        }

        return false;
    }

    /** UX-97: preview only roles with fewer rights than your own (super admins excepted); never the owner. */
    private static function mayPreviewRole(User $actor, Agency $agency, string $role): bool
    {
        $rank = ['owner' => 4, 'admin' => 3, 'manager' => 2, 'analyst' => 1];
        if (! isset($rank[$role]) || $role === 'owner') {
            return false;
        }
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $rank[$role] < ($rank[$actor->rawRoleIn($agency)] ?? 0);
    }

    private static function describe(string $target, Agency $agency): ?array
    {
        if (str_starts_with($target, 'r:')) {
            $role = substr($target, 2);
            if (! in_array($role, ['admin', 'manager', 'analyst'], true)) {
                return null;
            }
            return [
                'target' => $target,
                'type' => 'role',
                'role' => $role,
                'user' => null,
                'label' => __(TeamRoles::label($role)),
            ];
        }

        if (str_starts_with($target, 'u:')) {
            $uid = (int) substr($target, 2);
            $member = $agency->members()->where('users.id', $uid)->first();
            if (! $member) {
                return null;
            }
            $role = $member->pivot?->role ?: 'analyst';
            return [
                'target' => $target,
                'type' => 'user',
                'role' => $role,
                'user' => $member,
                'label' => $member->name.' ('.__(TeamRoles::label($role)).')',
            ];
        }

        return null;
    }
}
