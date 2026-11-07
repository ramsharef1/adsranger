<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\AgencyOperationalAudit;
use App\Models\AgencyWebhook;
use App\Models\AuditLog;
use App\Models\BudgetReallocationLog;
use App\Models\Client;
use App\Models\ClientOnboardingLink;
use App\Models\InfluencerCampaign;
use App\Support\AgencyOperationsHub;
use App\Support\CurrentClient;
use App\Support\InfluencerCampaignTracker;
use App\Support\TeamRoles;
use App\Support\Webhooks;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

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

    // #105 Shareable client onboarding link & #77/#115 Outbound webhooks / Looker & Sheets feed
    public string $prospectName = '';

    public string $prospectWebsite = '';

    public string $webhookName = '';

    public string $webhookTargetUrl = '';

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

    /** #105 Create shareable client onboarding & access request link (/onboard/{token}). */
    public function createOnboardingLink(): void
    {
        $agency = Filament::getTenant();
        if (! $agency) {
            return;
        }
        $client = $this->resolveSelectedClient();
        $name = trim($this->prospectName) !== '' ? trim($this->prospectName) : ($client?->name ?? '');
        if ($name === '') {
            return;
        }

        ClientOnboardingLink::create([
            'agency_id' => $agency->id,
            'client_id' => $client?->id,
            'token' => Str::random(40),
            'prospect_name' => $name,
            'website_url' => trim($this->prospectWebsite) !== '' ? trim($this->prospectWebsite) : null,
            'requested_networks' => ['meta', 'google_ads', 'ga4', 'tiktok', 'snapchat'],
            'status' => 'pending',
        ]);

        $this->prospectName = '';
        $this->prospectWebsite = '';
        Notification::make()->title(__('Client onboarding link created'))->success()->send();
    }

    /**
     * #77 & #115 Create an outbound webhook + read-only JSON feed. Owners and admins only. The target must be a public
     * https address (Webhooks::targetProblem). The feed URL and signing secret are shown once, in the notification.
     */
    public function createWebhook(): void
    {
        $agency = Filament::getTenant();
        abort_unless($agency && TeamRoles::canManageTeam(auth()->user(), $agency), 403);
        $name = trim($this->webhookName);
        $url = trim($this->webhookTargetUrl);
        if ($name === '' || mb_strlen($name) > 140 || $url === '') {
            Notification::make()->title(__('Enter a name and a target address.'))->danger()->send();

            return;
        }
        if ($problem = Webhooks::targetProblem($url)) {
            Notification::make()->title(__($problem))->danger()->send();

            return;
        }
        $client = $this->resolveSelectedClient();

        [$hook, $token, $secret] = AgencyWebhook::issue([
            'agency_id' => $agency->id,
            'client_id' => $client?->id,
            'name' => $name,
            'target_url' => $url,
            'events' => Webhooks::EVENTS,
        ]);
        AuditLog::record('webhook.created', $agency, ['webhook_id' => $hook->id, 'host' => parse_url($url, PHP_URL_HOST)]);

        $this->webhookName = '';
        $this->webhookTargetUrl = '';
        Notification::make()->title(__('Webhook & Looker/Sheets feed created'))
            ->body(__('Copy these now; the signing secret is not shown again.').' '.__('Feed URL').': '.$hook->feedUrl().' · '.__('Signing secret').': '.$secret)
            ->success()->persistent()->send();
    }

    /** Switch a webhook and its feed off (owners and admins). */
    public function deactivateWebhook(int $id): void
    {
        $agency = Filament::getTenant();
        abort_unless($agency && TeamRoles::canManageTeam(auth()->user(), $agency), 403);
        $hook = AgencyWebhook::where('agency_id', $agency->id)->findOrFail($id);
        $hook->update(['is_active' => false]);
        AuditLog::record('webhook.deactivated', $agency, ['webhook_id' => $hook->id]);
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

        $onboardingLinks = ClientOnboardingLink::where('agency_id', $agencyId)
            ->when($filterClientId, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('client_id')->orWhere('client_id', $filterClientId)))
            ->latest()
            ->limit(10)
            ->get();

        $webhooks = AgencyWebhook::where('agency_id', $agencyId)
            ->when($filterClientId, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('client_id')->orWhere('client_id', $filterClientId)))
            ->latest()
            ->limit(10)
            ->get();

        return [
            'agency' => $agency,
            'clients' => $clients,
            'currentClient' => $current,
            'reallocations' => $scope(BudgetReallocationLog::query())->latest()->limit(10)->get(),
            'operationalAudits' => $scope(AgencyOperationalAudit::query())->latest()->limit(10)->get(),
            'influencerCampaigns' => $scope(InfluencerCampaign::query())->latest()->limit(10)->get(),
            'onboardingLinks' => $onboardingLinks,
            'webhooks' => $webhooks,
            'canManageWebhooks' => $agency && TeamRoles::canManageTeam($user, $agency),
        ];
    }
}
