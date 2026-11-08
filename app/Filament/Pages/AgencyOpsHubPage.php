<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\AgencyOperationalAudit;
use App\Models\BudgetReallocationLog;
use App\Models\Client;
use App\Models\InfluencerCampaign;
use App\Support\AgencyOperationsHub;
use App\Support\CurrentClient;
use App\Support\InfluencerCampaignTracker;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AgencyOpsHubPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'agency_ops_hub';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $slug = 'agency-ops';

    protected static ?int $navigationSort = 38;

    protected string $view = 'filament.pages.agency-ops-hub';

    public ?int $clientId = null;

    public float $remainingBalance = 1500.0;

    public float $dailyBurnRate = 250.0;

    public string $influencerName = '';

    public string $influencerPlatform = 'snapchat';

    public string $promoCode = '';

    public float $fixedFee = 1000.0;

    public float $commissionPercent = 10.0;

    public int $ordersCount = 25;

    public float $attributedRevenue = 8500.0;

    public function mount(): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $firstClient = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->first() : null;
        $this->clientId = CurrentClient::id($agency) ?: $firstClient?->id;
    }

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

    protected function resolveSelectedClient(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user) {
            return null;
        }$q = Client::visibleTo($user, $agency);

        return $this->clientId ? $q->whereKey($this->clientId)->first() : $q->orderBy('name')->first();
    }

    public function runRunwayAudit(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client) {
            return;
        }
        AgencyOperationsHub::calculateBudgetRunway($client, (float) $this->remainingBalance, (float) $this->dailyBurnRate);
        Notification::make()->title(__('Budget runway audit recorded'))->success()->send();
    }

    public function recordInfluencer(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || trim($this->influencerName) === '' || trim($this->promoCode) === '') {
            return;
        }
        InfluencerCampaignTracker::recordAndAttribute(
            $client,
            trim($this->influencerName), $this->influencerPlatform,
            strtoupper(trim($this->promoCode)),
            (float) $this->fixedFee,
            (float) $this->commissionPercent,
            (int) $this->ordersCount,
            (float) $this->attributedRevenue
        );
        $this->influencerName = '';
        $this->promoCode = '';
        Notification::make()->title(__('Influencer campaign attributed'))->success()->send();
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
            'reallocations' => $scope(BudgetReallocationLog::query())->latest()->limit(10)->get(),
            'operationalAudits' => $scope(AgencyOperationalAudit::query())->latest()->limit(10)->get(),
            'influencerCampaigns' => $scope(InfluencerCampaign::query())->latest()->limit(10)->get(),
        ];
    }
}
