<?php

namespace App\Filament\Concerns;

use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/** Page nav label, nav group and title go through the JSON translations (lang/ar.json); English stays the key. */
trait TranslatesPageLabels
{
    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        $group = parent::getNavigationGroup();

        return is_string($group) ? __($group) : $group;
    }

    public function getTitle(): string|Htmlable
    {
        $title = parent::getTitle();

        return is_string($title) ? __($title) : $title;
    }
}
