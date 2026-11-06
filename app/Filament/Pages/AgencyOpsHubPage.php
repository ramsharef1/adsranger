<?php

namespace App\Filament\Pages;

use App\Models\AgencyOperationalAudit;
use App\Models\BudgetReallocationLog;
use App\Models\Client;
use App\Models\InfluencerCampaign;
use App\Support\CurrentClient;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class AgencyOpsHubPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $slug = 'agency-ops';

    protected static ?int $navigationSort = 38;

    protected string $view = 'filament.pages.agency-ops-hub';

    public static function getNavigationLabel(): string
    {
        return __('Agency operations & CFO');
    }

    public function getTitle(): string
    {
        return __('Agency operations, influencer & CFO hub');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Create & plan');
    }

    public function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $clients = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->get() : collect();
        $current = CurrentClient::resolve();

        $agencyId = $agency?->id ?? 0;

        return [
            'agency' => $agency,
            'clients' => $clients,
            'currentClient' => $current,
            'reallocations' => BudgetReallocationLog::where('agency_id', $agencyId)->latest()->limit(10)->get(),
            'operationalAudits' => AgencyOperationalAudit::where('agency_id', $agencyId)->latest()->limit(10)->get(),
            'influencerCampaigns' => InfluencerCampaign::where('agency_id', $agencyId)->latest()->limit(10)->get(),
        ];
    }
}