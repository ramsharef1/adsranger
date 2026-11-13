<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\User;

/** Who may do what inside an agency workspace. */
class TeamRoles
{
    /** Roles that can be given through an invite (owner is never invited). */
    public static function invitable(): array
    {
        return collect(config('adsranger.member_roles'))->except('owner')->all();
    }

    public static function label(?string $role): string
    {
        return config('adsranger.member_roles')[$role] ?? (string) $role;
    }

    public static function canManageTeam(?User $user, ?Agency $agency): bool
    {
        return $user && $agency && in_array($user->roleIn($agency), ['owner', 'admin'], true);
    }

    public static function isOwner(?User $user, ?Agency $agency): bool
    {
        return $user && $agency && $user->roleIn($agency) === 'owner';
    }

    /** Owners, admins and account managers can connect networks and import data. */
    public static function canManageData(?User $user, ?Agency $agency): bool
    {
        return $user && $agency && in_array($user->roleIn($agency), ['owner', 'admin', 'manager'], true);
    }

    public static function isReadOnly(?User $user, ?Agency $agency): bool
    {
        return $user && $agency && $user->roleIn($agency) === 'analyst';
    }
}
