<?php

namespace App\Filament\Concerns;

use App\Support\PlanGate;
use Filament\Facades\Filament;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

/**
 * For a Filament page that a plan tier unlocks (EX-50). The class sets `protected static string $planFeature`.
 * Locked: the page stays in its nav group with a lock icon + PlanBadge, renders LockedState instead of its content,
 * and refuses every Livewire action (the locked view has none, so only crafted calls get a 403).
 */
trait GatedByPlan
{
    public static function planLocked(): bool
    {
        $tenant = Filament::getTenant();

        return $tenant !== null && PlanGate::locked($tenant, static::$planFeature);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::planLocked() ? PlanGate::tierShort(PlanGate::requiredTier(static::$planFeature)) : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::planLocked()
            ? __('plan.available_on', ['tier' => PlanGate::tierLabel(PlanGate::requiredTier(static::$planFeature))])
            : null;
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return static::planLocked() ? 'heroicon-o-lock-closed' : parent::getNavigationIcon();
    }

    public function hydrateGatedByPlan(): void
    {
        abort_if(static::planLocked(), 403);
    }

    public function render(): View
    {
        if (! static::planLocked()) {
            return parent::render();
        }

        $tenant = Filament::getTenant();
        PlanGate::lockedShown($tenant, static::$planFeature); // PS-27: counts the locked page being opened

        return view('filament.pages.plan-locked', [
            'feature' => static::$planFeature,
            'tier' => PlanGate::requiredTier(static::$planFeature),
            'canUpgrade' => PlanGate::canUpgrade(auth()->user(), $tenant),
        ])->layout($this->getLayout(), [
            'livewire' => $this,
            'maxContentWidth' => $this->getMaxContentWidth(),
            ...$this->getLayoutData(),
        ]);
    }
}
