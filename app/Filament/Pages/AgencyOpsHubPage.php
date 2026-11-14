<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\AgencyOperationalAudit;
use App\Models\BudgetReallocationLog;
use App\Models\Client;
use App\Models\InfluencerCampaign;
use App\Models\ProspectPitchDeck;
use App\Support\AgencyOperationsHub;
use App\Support\AgencyOps\PitchDeckBuilder;
use App\Support\CurrentClient;
use App\Support\InfluencerCampaignTracker;
use App\Support\Modules;
use App\Support\TeamRoles;
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

    /** Typed by the person; no sample numbers are pre-filled. */
    public $remainingBalance = null;

    public $dailyBurnRate = null;

    public string $influencerName = '';

    public string $influencerPlatform = 'snapchat';

    public string $promoCode = '';

    public $fixedFee = null;

    public $commissionPercent = null;

    public $ordersCount = null;

    public $attributedRevenue = null;

    public string $prospectName = '';

    public string $prospectUrl = '';

    public string $prospectCurrency = 'SAR';

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

    protected function canManage(): bool
    {
        $agency = Filament::getTenant();

        return $agency !== null && TeamRoles::canManageData(auth()->user(), $agency);
    }

    public function runRunwayAudit(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || ! $this->canManage()) {
            return;
        }
        $data = $this->validate([
            'remainingBalance' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'dailyBurnRate' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ], [], ['remainingBalance' => __('Remaining balance'), 'dailyBurnRate' => __('Daily burn rate')]);
        AgencyOperationsHub::calculateBudgetRunway($client, (float) $data['remainingBalance'], (float) $data['dailyBurnRate']);
        $this->reset(['remainingBalance', 'dailyBurnRate']);
        Notification::make()->title(__('Budget runway audit recorded'))->success()->send();
    }

    public function recordInfluencer(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || ! $this->canManage()) {
            return;
        }
        $data = $this->validate([
            'influencerName' => ['required', 'string', 'max:120'],
            'influencerPlatform' => ['required', 'in:'.implode(',', array_keys(self::platforms()))],
            'promoCode' => ['required', 'string', 'max:48', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'fixedFee' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'commissionPercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'ordersCount' => ['required', 'integer', 'min:0', 'max:9999999'],
            'attributedRevenue' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ], [], [
            'influencerName' => __('Creator name'), 'influencerPlatform' => __('Platform'), 'promoCode' => __('Promo code'), 'fixedFee' => __('Fixed fee'),
            'commissionPercent' => __('Commission %'), 'ordersCount' => __('Orders'), 'attributedRevenue' => __('Revenue'),
        ]);
        InfluencerCampaignTracker::recordAndAttribute(
            $client,
            trim($data['influencerName']), $data['influencerPlatform'],
            strtoupper(trim($data['promoCode'])),
            (float) $data['fixedFee'],
            (float) $data['commissionPercent'],
            (int) $data['ordersCount'],
            (float) $data['attributedRevenue']
        );
        $this->reset(['influencerName', 'promoCode', 'fixedFee', 'commissionPercent', 'ordersCount', 'attributedRevenue']);
        Notification::make()->title(__('Influencer campaign attributed'))->success()->send();
    }

    /** @return array<string, string> */
    public static function platforms(): array
    {
        return ['snapchat' => 'Snapchat', 'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X', 'other' => __('Other')];
    }

    /** @return array{0: string, 1: string} label and tone (ok · warn · bad) for a stored audit status */
    public static function statusLabel(string $status): array
    {
        return [
            'healthy' => [__('Enough budget'), 'ok'], 'warning_low' => [__('Runs out within a week'), 'warn'], 'critical_depletion' => [__('Runs out within 3 days'), 'bad'],
            'profitable' => [__('Profitable'), 'ok'], 'margin_squeeze' => [__('Low margin'), 'warn'],
            'stable_peg' => [__('Stable'), 'ok'], 'fx_hedge_alert' => [__('Exchange rate moved'), 'warn'],
            'balanced' => [__('Balanced'), 'ok'], 'overloaded' => [__('Overloaded'), 'bad'],
        ][$status] ?? [$status, 'warn'];
    }

    public static function auditLabel(string $type): string
    {
        return [
            'budget_runway' => __('Budget runway'), 'retainer_margin' => __('Retainer margin'),
            'mena_fx_volatility' => __('Exchange rate check'), 'media_buyer_workload' => __('Team workload'),
        ][$type] ?? $type;
    }

    public function createPitchDeck(): void
    {
        $agency = Filament::getTenant();
        if (! $agency || ! TeamRoles::canManageData(auth()->user(), $agency) || trim($this->prospectName) === '' || trim($this->prospectUrl) === '') {
            return;
        }
        PitchDeckBuilder::generate($agency, $this->prospectName, $this->prospectUrl, $this->prospectCurrency);
        $this->reset(['prospectName', 'prospectUrl']);
        Notification::make()->title(__('Prospect pitch deck generated.'))->success()->send();
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
            'pitchDecks' => ProspectPitchDeck::where('agency_id', $agencyId)->latest('id')->limit(10)->get(),
            'canManageOps' => $agency ? TeamRoles::canManageData($user, $agency) : false,
            'showDomain' => Modules::on('custom_domains', null, $agency),
            'platforms' => self::platforms(),
        ];
    }
}
