<?php

namespace App\Support\Reach;

use App\Models\Agency;
use App\Models\User;
use App\Support\Modules;

/** FR-REACH gate: every Reach route, page and job asks here first. Off by default (config/modules.php `reach`). */
class Reach
{
    public const MODULE = 'reach';

    public static function on(?User $user = null, ?Agency $agency = null): bool
    {
        return Modules::on(self::MODULE, $user, $agency);
    }

    /** Anonymous endpoints (postbacks, serve, landing): on for everyone, or for a signed-in super admin checking it. */
    public static function publicOn(): bool
    {
        $user = auth()->user();

        return Modules::enabled(self::MODULE) || static::on($user, $user instanceof User ? $user->agencies()->first() : null);
    }

    /** Round to 3 decimals (3-decimal currencies: KWD, BHD, OMR, JOD). */
    public static function amount(float $v): float
    {
        return round($v, 3);
    }
}
