<?php

namespace App\Filament\Pages;

use App\Models\BlendedRoasMetric;
use App\Models\Client;
use App\Models\CompetitorCreativeRadar;
use App\Models\IntentKeywordMap;
use App\Models\NetworkAcceleratorEvent;
use App\Models\PsychologicalHookScore;
use App\Models\UxFunnelHeatmap;
use App\Support\CurrentClient;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class IntelligenceHubPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $slug = 'intelligence-hub';

    protected static ?int $navigationSort = 37;

    protected string $view = 'filament.pages.intelligence-hub';

    public static function getNavigationLabel(): string
    {
        return __('Intelligence hub');
    }

    public function getTitle(): string
    {
        return __('Growth & intelligence hub');
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
            'blendedMetrics' => BlendedRoasMetric::where('agency_id', $agencyId)->latest('metric_date')->limit(10)->get(),
            'keywordMaps' => IntentKeywordMap::where('agency_id', $agencyId)->latest()->limit(10)->get(),
            'hookScores' => PsychologicalHookScore::where('agency_id', $agencyId)->latest()->limit(10)->get(),
            'trendRadars' => CompetitorCreativeRadar::where('agency_id', $agencyId)->latest()->limit(10)->get(),
            'uxHeatmaps' => UxFunnelHeatmap::where('agency_id', $agencyId)->latest()->limit(10)->get(),
            'accelerators' => NetworkAcceleratorEvent::where('agency_id', $agencyId)->latest()->limit(10)->get(),
        ];
    }
}