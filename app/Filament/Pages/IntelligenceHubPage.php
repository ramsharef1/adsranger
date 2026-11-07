<?php

namespace App\Filament\Pages;

use App\Models\BlendedRoasMetric;
use App\Models\Client;
use App\Models\CompetitorCreativeRadar;
use App\Models\IntentKeywordMap;
use App\Models\NetworkAcceleratorEvent;
use App\Models\PsychologicalHookScore;
use App\Models\UxFunnelHeatmap;
use App\Support\BlendedRoasCalculator;
use App\Support\CurrentClient;
use App\Support\KeywordMappingManager;
use App\Support\PsychologicalTriggerAnalyzer;
use App\Filament\Concerns\HeldBackModule;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class IntelligenceHubPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'intelligence_hub';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $slug = 'intelligence-hub';

    protected static ?int $navigationSort = 37;

    protected string $view = 'filament.pages.intelligence-hub';

    public ?int $clientId = null;
    public string $seedKeyword = '';
    public string $keywordLocale = 'ar';
    public string $adCopySample = '';
    public string $hookDialect = 'gulf';

    public function mount(): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $firstClient = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->first() : null;
        $this->clientId = CurrentClient::id($agency) ?: $firstClient?->id;
    }

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

    protected function resolveSelectedClient(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user) {
            return null;
        }
        $q = Client::visibleTo($user, $agency);
        return $this->clientId ? $q->whereKey($this->clientId)->first() : $q->orderBy('name')->first();
    }

    public function generateKeywords(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || trim($this->seedKeyword) === '') {
            return;
        }
        KeywordMappingManager::generateForClient($client, trim($this->seedKeyword), $this->keywordLocale);
        $this->seedKeyword = '';
        Notification::make()->title(__('Keyword intent matrix generated'))->success()->send();
    }

    public function scoreHook(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || trim($this->adCopySample) === '') {
            return;
        }
        PsychologicalTriggerAnalyzer::analyzeAndScore($client, trim($this->adCopySample), $this->hookDialect);
        $this->adCopySample = '';
        Notification::make()->title(__('Psychological hook scored'))->success()->send();
    }

    public function computeBlendedRoas(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client) {
            return;
        }
        BlendedRoasCalculator::computeForClientDate($client, now()->toDateString());
        Notification::make()->title(__('Blended ROAS & MER snapshot updated'))->success()->send();
    }

    public function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $clients = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->get() : collect();
        $current = CurrentClient::resolve($agency);

        $agencyId = $agency?->id ?? 0;
        $visibleIds = $clients->pluck('id')->all();
        $filterClientId = $current?->id ?: $this->clientId;

        $scope = function ($query) use ($agencyId, $visibleIds, $filterClientId) {
            $query->where('agency_id', $agencyId);
            if (! empty($visibleIds)) {
                $query->whereIn('client_id', $visibleIds);
            }
            if ($filterClientId) {
                $query->where('client_id', $filterClientId);
            }
            return $query;
        };

        return [
            'agency' => $agency,
            'clients' => $clients,
            'currentClient' => $current,
            'blendedMetrics' => $scope(BlendedRoasMetric::query())->latest('metric_date')->limit(10)->get(),
            'keywordMaps' => $scope(IntentKeywordMap::query())->latest()->limit(10)->get(),
            'hookScores' => $scope(PsychologicalHookScore::query())->latest()->limit(10)->get(),
            'trendRadars' => $scope(CompetitorCreativeRadar::query())->latest()->limit(10)->get(),
            'uxHeatmaps' => $scope(UxFunnelHeatmap::query())->latest()->limit(10)->get(),
            'accelerators' => $scope(NetworkAcceleratorEvent::query())->latest()->limit(10)->get(),
        ];
    }
}
