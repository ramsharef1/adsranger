<?php

namespace App\Support;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationManager;

/** PX-01 (D-302) + E2-068: when the /app or /admin PX shell is active, the sidebar shows only the current area's pages. */
class ShellNavigationManager extends NavigationManager
{
    protected bool $unfiltered = false;

    protected ?string $mountedKey = null;

    /** @return array<NavigationGroup> */
    public static function allGroups(): array
    {
        $manager = app(NavigationManager::class);
        if (! $manager instanceof self) {
            return Filament::getNavigation();
        }

        $manager->unfiltered = true;
        try {
            return Filament::getNavigation();
        } finally {
            $manager->unfiltered = false;
        }
    }

    public function get(): array
    {
        $key = (Filament::getCurrentPanel()?->getId() ?? '').'|'.app()->getLocale().'|'.(Filament::getTenant()?->getKey() ?? '');
        if ($this->mountedKey !== null && $this->mountedKey !== $key) {
            $this->isNavigationMounted = false;
            $this->navigationItems = [];
        }
        $this->mountedKey = $key;

        $groups = parent::get();

        if ($this->unfiltered) {
            return $groups;
        }

        if (Shell::on()) {
            return Shell::filter($groups, Shell::current());
        }

        if (AdminShell::on()) {
            return AdminShell::filter($groups, AdminShell::current());
        }

        return $groups;
    }
}
