<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use UnitEnum;

/** Resource labels through the JSON translations; the English plural is the key, since Arabic has no automatic plural. */
trait TranslatesResourceLabels
{
    public static function getModelLabel(): string
    {
        return __(parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        return __(static::$pluralModelLabel ?? Str::plural(parent::getModelLabel()));
    }

    public static function getNavigationLabel(): string|Htmlable
    {
        return static::$navigationLabel !== null ? __(static::$navigationLabel) : static::getTitleCasePluralModelLabel();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = parent::getNavigationGroup();

        return is_string($group) ? __($group) : $group;
    }
}
