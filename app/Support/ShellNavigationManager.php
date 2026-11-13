<?php

namespace App\Support;

use Filament\Navigation\NavigationManager;

/** PX-01 (D-302): with the new shell on, the sidebar shows only the current area's pages (and pages of no area). */
class ShellNavigationManager extends NavigationManager
{
    public function get(): array
    {
        $groups = parent::get();

        return Shell::on() ? Shell::filter($groups, Shell::current()) : $groups;
    }
}
