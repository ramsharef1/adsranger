<?php

namespace App\Filament\Pages;

use App\Support\NetworkAccess;
use App\Support\PlanGate;
use App\Support\PlanLimits;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

class PlanUsage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Plan & usage';

    protected static ?string $title = 'Plan & usage';

    protected static ?string $slug = 'plan';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.plan-usage';

    protected function getViewData(): array
    {
        $a = Filament::getTenant();

        return [
            'agency' => $a,
            'plan' => $a->planModel,
            'trialDays' => PlanLimits::trialDaysLeft($a),
            'rows' => [
                ['Team seats (members + pending invites)', PlanLimits::seatsUsed($a), PlanLimits::seatsLimit($a)],
                ['Tracked ad accounts', PlanLimits::adAccountsUsed($a), PlanLimits::adAccountsLimit($a)],
            ],
            'networks' => NetworkAccess::forAgency($a)->pluck('name'),
            // EX-50 point 7: what each tier unlocks, from the gating config; in-app prices allowed (D-19), never public (D-07).
            'tier' => PlanGate::tier($a),
            'byTier' => PlanGate::byTier(),
            'prices' => config('plan_features.prices'),
        ];
    }
}
