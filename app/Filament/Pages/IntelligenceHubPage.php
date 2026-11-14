<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\Alert;
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
use App\Support\ResultResolver;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class IntelligenceHubPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'intelligence_hub';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $slug = 'intelligence-hub';

    protected static ?int $navigationSort = 37;

    protected string $view = 'filament.pages.intelligence-hub';

    public ?int $clientId = null;

    public string $seedKeyword = '';

    public string $keywordLocale = 'ar';

    public string $adCopySample = '';

    public string $hookDialect = 'gulf';

    // Summary of saved numbers and the top open alerts (no model is called here)
    public string $platformQuestion = '';

    public string $platformAnswer = '';

    public array $weeklyActions = [];

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

    protected function canManage(): bool
    {
        $agency = Filament::getTenant();

        return $agency !== null && TeamRoles::canManageData(auth()->user(), $agency);
    }

    /** A starter keyword list built from fixed phrase templates around the typed service. No model and no search volumes. */
    public function generateKeywords(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || ! $this->canManage()) {
            return;
        }
        $data = $this->validate([
            'seedKeyword' => ['required', 'string', 'min:2', 'max:80'],
            'keywordLocale' => ['required', 'in:ar,en'],
        ], [], ['seedKeyword' => __('Core service or product'), 'keywordLocale' => __('Language')]);
        KeywordMappingManager::generateForClient($client, trim($data['seedKeyword']), $data['keywordLocale']);
        $this->seedKeyword = '';
        Notification::make()->title(__('Starter keyword list saved'))->success()->send();
    }

    /** A word check: which persuasion word groups appear in the text. It does not predict results. */
    public function scoreHook(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || ! $this->canManage()) {
            return;
        }
        $data = $this->validate([
            'adCopySample' => ['required', 'string', 'min:3', 'max:300'],
            'hookDialect' => ['required', 'in:gulf,egyptian,levantine,msa'],
        ], [], ['adCopySample' => __('Ad hook or headline'), 'hookDialect' => __('Dialect')]);
        PsychologicalTriggerAnalyzer::analyzeAndScore($client, trim($data['adCopySample']), $data['hookDialect']);
        $this->adCopySample = '';
        Notification::make()->title(__('Word check saved'))->success()->send();
    }

    /** Revenue ÷ spend across every channel for yesterday (the last complete day) in the workspace's time zone. */
    public function computeBlendedRoas(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || ! $this->canManage()) {
            return;
        }
        $tz = Filament::getTenant()?->timezone ?: 'UTC';
        BlendedRoasCalculator::computeForClientDate($client, now($tz)->subDay()->toDateString());
        Notification::make()->title(__('Return snapshot updated for yesterday'))->success()->send();
    }

    /**
     * A plain summary of the client's latest saved snapshot. It reads saved numbers only: no model is called and
     * nothing is filled in when a number is missing. The typed question is not interpreted.
     */
    public function askPlatform(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client) {
            return;
        }
        $m = BlendedRoasMetric::where('agency_id', $client->agency_id)->where('client_id', $client->id)->latest('metric_date')->first();
        if (! $m || (float) $m->total_spend <= 0) {
            $this->platformAnswer = __(':client: no return snapshot with spend is saved yet. Press "Update snapshot" after data for yesterday has arrived.', ['client' => $client->name]);

            return;
        }
        $best = collect((array) $m->channel_breakdown)->filter(fn ($c) => (float) ($c['spend'] ?? 0) > 0)->sortByDesc(fn ($c) => (float) ($c['roas'] ?? 0))->keys()->first();
        $this->platformAnswer = __(':client on :date: spend :spend, revenue :revenue, return :mer× across all channels.', [
            'client' => $client->name,
            'date' => Carbon::parse($m->metric_date)->toDateString(),
            'spend' => ResultResolver::money((float) $m->total_spend, $client->currency),
            'revenue' => ResultResolver::money((float) $m->total_revenue, $client->currency),
            'mer' => number_format((float) $m->marketing_efficiency_ratio, 2),
        ]).($best ? ' '.__('Highest return that day: :network.', ['network' => $best]) : '');
    }

    /** The client's three most serious open alerts, oldest first. Nothing is written when there are none. */
    public function generateWeeklyReview(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client) {
            return;
        }
        $alerts = Alert::where('agency_id', $client->agency_id)->where('client_id', $client->id)->where('status', 'open')
            ->orderByRaw("case when severity in ('critical', 'high') then 0 when severity in ('warning', 'medium') then 1 else 2 end")
            ->orderBy('created_at')->limit(3)->get();
        $this->weeklyActions = $alerts->isEmpty()
            ? [__(':client has no open alerts.', ['client' => $client->name])]
            : $alerts->map(fn (Alert $a) => $client->name.': '.$a->localTitle())->all();
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
            } else {
                $query->whereRaw('1 = 0');
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
            'canManageHub' => $agency ? TeamRoles::canManageData($user, $agency) : false,
        ];
    }
}
