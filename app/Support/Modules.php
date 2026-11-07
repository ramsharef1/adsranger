<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\User;
use Filament\Facades\Filament;

/** ADSR-110 step 0.2: is a held-back module available to this user in this workspace? (config/modules.php) */
class Modules
{
    public static function enabled(string $module): bool
    {
        return (bool) config("modules.enabled.$module", false);
    }

    public static function on(string $module, ?User $user = null, ?Agency $agency = null): bool
    {
        if (static::enabled($module)) {
            return true;
        }
        $user ??= auth()->user();
        if ($user instanceof User && $user->isSuperAdmin()) {
            return true;
        }
        $agency ??= static::tenant();

        return $agency instanceof Agency && in_array($agency->slug, (array) config('modules.preview_agencies', []), true);
    }

    /** True when this user sees the module only as a preview (agencies don't see it yet). */
    public static function previewOnly(string $module, ?User $user = null, ?Agency $agency = null): bool
    {
        return ! static::enabled($module) && static::on($module, $user, $agency);
    }

    protected static function tenant(): ?Agency
    {
        try {
            $t = Filament::getTenant();
        } catch (\Throwable) {
            return null;
        }

        return $t instanceof Agency ? $t : null;
    }
}
