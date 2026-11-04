<?php

namespace App\Filament\Widgets;

use App\Support\TeamRoles;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/** Onboarding checklist on the agency dashboard. Hides itself once everything is done. */
class GettingStarted extends Widget
{
    protected string $view = 'filament.widgets.getting-started';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -10;

    public static function canView(): bool
    {
        return Filament::getTenant() !== null;
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $base = '/app/'.$a->slug;
        $steps = [
            ['Create your workspace', 'Name, country, time zone and currency.', true, $base.'/profile'],
            ['Invite your team', 'Add account managers and analysts.', $a->members()->count() > 1 || $a->invitations()->exists(), $base.'/members'],
            ['Connect a network', 'Meta, Google Ads or GA4 to start.', $a->connections()->exists(), $base.'/networks'],
            ['Add your clients', 'Targets, currency and which ad accounts belong to each.', $a->clients()->exists(), $base.'/clients'],
            ['Choose the ad accounts to track', 'Pick which accounts count toward your plan.', $a->adAccounts()->where('is_active', true)->exists(), $base.'/ad-accounts'],
            ['Import data from other networks', 'CSV or Google Sheets, for networks without an API.', $a->dataImports()->exists(), $base.'/imports'],
        ];
        $done = collect($steps)->where(2, true)->count();

        return ['steps' => $steps, 'done' => $done, 'total' => count($steps), 'canManage' => TeamRoles::canManageData(auth()->user(), $a)];
    }
}
