<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Models\ModuleEarlyAccessRequest;
use App\Models\PlanUpgradeRequest;
use App\Support\Modules;
use App\Support\NetworkAccess;
use App\Support\PlanGate;
use App\Support\PlanLimits;
use App\Support\Trial;
use App\Support\ValueCard;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use UnitEnum;

class PlanUsage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Plan & usage';

    protected static ?string $title = 'Plan & usage';

    protected static ?string $slug = 'plan';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.plan-usage';

    public bool $showUpgradeModal = false;

    public string $targetPlan = 'pro';

    public string $upgradeNote = '';

    public function openUpgradeModal(?string $presetTier = null): void
    {
        $agency = Filament::getTenant();
        abort_unless(PlanGate::canUpgrade(auth()->user(), $agency), 403);
        $this->targetPlan = $presetTier && array_key_exists($presetTier, config('plan_features.tiers', [])) ? $presetTier : 'pro';
        $this->showUpgradeModal = true;
    }

    public function closeUpgradeModal(): void
    {
        $this->showUpgradeModal = false;
    }

    public function submitUpgradeRequest(): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        abort_unless($agency && PlanGate::canUpgrade($user, $agency), 403);

        $tiers = PlanGate::offeredTiers();
        $this->validate([
            'targetPlan' => ['required', Rule::in($tiers)],
            'upgradeNote' => ['nullable', 'string', 'max:500'],
        ]);

        $req = PlanUpgradeRequest::query()->updateOrCreate(
            ['agency_id' => $agency->id, 'status' => 'pending'],
            [
                'requested_by' => $user->id,
                'current_plan' => $agency->plan,
                'target_plan' => $this->targetPlan,
                'note' => trim($this->upgradeNote) ?: null,
            ]
        );

        AuditLog::record('agency.upgrade_requested', $agency, [
            'request_id' => $req->id,
            'from' => $agency->plan,
            'to' => $this->targetPlan,
        ]);

        $this->showUpgradeModal = false;
        $this->upgradeNote = '';
        Notification::make()->title(__('Upgrade request submitted'))->success()->send();
    }

    public function requestModuleEarlyAccess(string $moduleKey): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        abort_unless($agency && PlanGate::canUpgrade($user, $agency), 403);

        $catalog = Modules::catalog();
        abort_unless(isset($catalog[$moduleKey]) && $catalog[$moduleKey]['beta_requestable'], 404);

        $req = ModuleEarlyAccessRequest::query()->firstOrCreate(
            ['agency_id' => $agency->id, 'module_key' => $moduleKey, 'status' => 'pending'],
            ['requested_by' => $user->id]
        );

        AuditLog::record('agency.module_early_access_requested', $agency, [
            'request_id' => $req->id,
            'module' => $moduleKey,
        ]);

        Notification::make()->title(__('Early access requested'))->success()->send();
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $user = auth()->user();
        $valueCard = ValueCard::month($a); // PS-29 (D-306)
        $betaModules = collect(Modules::catalog())
            ->filter(fn (array $meta, string $key): bool => $meta['beta_requestable'] && ! Modules::on($key, $user, $a))
            ->all();

        $requestedModules = ModuleEarlyAccessRequest::query()
            ->where('agency_id', $a->id)
            ->where('status', 'pending')
            ->pluck('module_key')
            ->all();

        return ['valueCard' => $valueCard] + [
            'agency' => $a,
            'plan' => $a->planModel,
            'trialDays' => PlanLimits::trialDaysLeft($a),
            'trialEnded' => Trial::hasEnded($a),
            'canUpgrade' => PlanGate::canUpgrade($user, $a),
            'pendingUpgrade' => PlanUpgradeRequest::query()->where('agency_id', $a->id)->where('status', 'pending')->latest()->first(),
            'betaModules' => $betaModules,
            'requestedModules' => $requestedModules,
            'rows' => [
                [__('Team seats (members + pending invites)'), PlanLimits::seatsUsed($a), PlanLimits::seatsLimit($a)],
                [__('Tracked ad accounts'), PlanLimits::adAccountsUsed($a), PlanLimits::adAccountsLimit($a)],
                [__('Clients (archived ones do not count)'), PlanLimits::clientsUsed($a), PlanLimits::clientsLimit($a)],
            ],
            'networks' => NetworkAccess::forAgency($a)->pluck('name'),
            'tier' => PlanGate::tier($a),
            'byTier' => PlanGate::byTier(),
            'prices' => config('plan_features.prices'),
        ];
    }
}
