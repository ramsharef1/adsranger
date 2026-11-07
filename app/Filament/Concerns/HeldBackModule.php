<?php

namespace App\Filament\Concerns;

use App\Support\Modules;

/**
 * ADSR-110 step 0.2: a page whose module is off is hidden from agencies (no nav item, 403 on the URL).
 * The using page sets `protected static string $module`.
 */
trait HeldBackModule
{
    public static function canAccess(): bool
    {
        return Modules::on(static::$module) && parent::canAccess();
    }
}
