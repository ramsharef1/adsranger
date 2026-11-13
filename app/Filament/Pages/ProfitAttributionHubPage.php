<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\CapiBridgeEvent;
use App\Models\Client;
use App\Models\ClientCustomKpi;
use App\Models\ClientStoreConnection;
use App\Models\ClientUnitEconomic;
use App\Support\Attribution\ProfitAndAttributionManager;
use App\Support\Attribution\SafeFormulaEvaluator;
use App\Support\CurrentClient;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProfitAttributionHubPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'intelligence_hub';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $slug = 'profit-attribution-hub';

    protected static ?int $navigationSort = 38;

    protected string $view = 'filament.pages.profit-attribution-hub';

    public ?int $clientId = null;

    /**
     * #193 Unit economics + POAS estimate (UX-255): every input starts empty. Nothing is calculated until the agency has
     * entered all its own costs and one period's revenue, spend and orders; no number on this page is invented.
     */
    public ?float $cogsPct = null;

    public ?float $shippingFee = null;

    public ?float $returnShippingFee = null;

    public ?float $codSharePct = null;

    public ?float $codReturnRatePct = null;

    public ?float $bnplSharePct = null;

    public ?float $bnplFeePct = null;

    public ?float $gatewayFeePct = null;

    public ?float $periodRevenue = null;

    public ?float $periodSpend = null;

    public ?int $periodOrders = null;

    public bool $poasMissingInputs = true;

    public array $poasPreview = [];

    // #194 Click-to-WhatsApp CAPI capture
    public string $ctwaClid = '';

    public string $ctwaEventName = 'Purchase';

    public float $ctwaValue = 0.0;

    public string $ctwaPhone = '';

    // #196 SKU snapshot form
    public string $skuCode = '';

    public string $skuTitle = '';

    public int $skuStockQty = 25;

    public float $skuSpend = 0.0;

    public float $skuRevenue = 0.0;

    public int $skuOrders = 0;

    public float $skuRepeatLtv = 0.0;

    // #204 MTA active model switcher (view-only)
    public string $activeMtaModel = 'position_based';

    // #205 Custom KPI builder
    public string $kpiName = '';

    public string $kpiFormula = '(net_profit / spend)';

    public string $kpiFormat = 'decimal';

    public ?float $formulaPreviewValue = null;

    public ?string $formulaPreviewError = null;

    // #206 Offline POS CSV matcher
    public string $posCsvInput = '';

    public ?array $posMatchSummary = null;

    // E2-063 store webhooks (Salla / Zid / Shopify)
    public string $storePlatform = 'salla';

    public string $storeIdentifier = '';

    public string $storeSecret = '';

    public function mount(): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $current = CurrentClient::resolve($agency);
        $firstClient = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->first() : null;
        $this->clientId = $current?->id ?: $firstClient?->id;

        $client = $this->resolveSelectedClient();
        if ($client) {
            $econ = ClientUnitEconomic::where('agency_id', $client->agency_id)
                ->where('client_id', $client->id)
                ->first();
            if ($econ && $econ->costs_entered_at) {
                $this->cogsPct = (float) $econ->cogs_pct;
                $this->shippingFee = (float) $econ->shipping_fee;
                $this->returnShippingFee = (float) $econ->return_shipping_fee;
                $this->codSharePct = (float) $econ->cod_share_pct;
                $this->codReturnRatePct = (float) $econ->cod_return_rate_pct;
                $this->bnplSharePct = (float) $econ->bnpl_share_pct;
                $this->bnplFeePct = (float) $econ->bnpl_fee_pct;
                $this->gatewayFeePct = (float) $econ->gateway_fee_pct;
            }
            $this->previewCodPoas();
        }
    }

    public static function getNavigationLabel(): string
    {
        return __('Profit & attribution hub');
    }

    public function getTitle(): string
    {
        return __('Profit & attribution hub');
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
        $current = CurrentClient::resolve($agency);
        if ($current) {
            return $current;
        }
        $q = Client::visibleTo($user, $agency);

        return $this->clientId ? $q->whereKey($this->clientId)->first() : $q->orderBy('name')->first();
    }

    /** @return array<string, float> the cost inputs, or [] while any of them is empty */
    protected function costInputs(): array
    {
        $costs = [
            'cogs_pct' => $this->cogsPct,
            'shipping_fee' => $this->shippingFee,
            'return_shipping_fee' => $this->returnShippingFee,
            'cod_share_pct' => $this->codSharePct,
            'cod_return_rate_pct' => $this->codReturnRatePct,
            'bnpl_share_pct' => $this->bnplSharePct,
            'bnpl_fee_pct' => $this->bnplFeePct,
            'gateway_fee_pct' => $this->gatewayFeePct,
        ];
        foreach ($costs as $v) {
            if ($v === null || $v === '') {
                return [];
            }
        }

        return array_map(fn ($v) => (float) $v, $costs);
    }

    protected function hasPeriod(): bool
    {
        return $this->periodRevenue !== null && $this->periodSpend !== null && $this->periodOrders !== null
            && (float) $this->periodSpend > 0;
    }

    /** View-only preview allowed under ReadOnlyGuard. Calculates only from what the agency entered (UX-255). */
    public function previewCodPoas(): void
    {
        $client = $this->resolveSelectedClient();
        $costs = $this->costInputs();
        if (! $client || $costs === [] || ! $this->hasPeriod()) {
            $this->poasPreview = [];
            $this->poasMissingInputs = true;

            return;
        }

        $this->poasMissingInputs = false;
        $this->poasPreview = ProfitAndAttributionManager::computeCodPoas(
            $client,
            (float) $this->periodRevenue,
            (float) $this->periodSpend,
            (int) $this->periodOrders,
            $costs
        );
    }

    /** @return array<string, float> only the figures the agency entered or that follow from them */
    protected function knownMetrics(): array
    {
        if (! $this->hasPeriod()) {
            return [];
        }
        $m = [
            'spend' => (float) $this->periodSpend,
            'revenue' => (float) $this->periodRevenue,
            'conversions' => (float) $this->periodOrders,
        ];
        if (isset($this->poasPreview['net_profit'])) {
            $m['net_profit'] = (float) $this->poasPreview['net_profit'];
        }

        return $m;
    }

    /** View-only MTA switcher allowed under ReadOnlyGuard. */
    public function setMtaModel(string $model): void
    {
        if (in_array($model, ['first_touch', 'last_touch', 'linear', 'time_decay', 'position_based'], true)) {
            $this->activeMtaModel = $model;
        }
    }

    /** View-only KPI formula check allowed under ReadOnlyGuard. Uses only figures the agency entered (UX-255). */
    public function previewFormula(): void
    {
        $this->formulaPreviewValue = null;
        $check = SafeFormulaEvaluator::validate($this->kpiFormula);
        if (! $check['valid']) {
            $this->formulaPreviewError = $check['error'];

            return;
        }
        $known = $this->knownMetrics();
        $missing = array_values(array_diff($check['variables'], array_keys($known)));
        if ($missing !== []) {
            $this->formulaPreviewError = __('This formula needs :vars, which this page does not have yet. Enter the period figures above, or wait for the ad accounts to connect.', ['vars' => implode(', ', $missing)]);

            return;
        }
        try {
            $this->formulaPreviewValue = SafeFormulaEvaluator::evaluate($this->kpiFormula, $known);
            $this->formulaPreviewError = null;
        } catch (InvalidArgumentException $e) {
            $this->formulaPreviewError = $e->getMessage();
        }
    }

    public function saveUnitEconomics(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client) {
            return;
        }

        $costs = $this->costInputs();
        if ($costs === []) {
            Notification::make()->title(__('Enter all the costs before saving.'))->warning()->send();

            return;
        }

        $econ = ProfitAndAttributionManager::ensureUnitEconomics($client);
        $econ->update([
            'cogs_pct' => min(100.0, max(0.0, $this->cogsPct)),
            'shipping_fee' => max(0.0, $this->shippingFee),
            'return_shipping_fee' => max(0.0, $this->returnShippingFee),
            'cod_share_pct' => min(100.0, max(0.0, $this->codSharePct)),
            'cod_return_rate_pct' => min(100.0, max(0.0, $this->codReturnRatePct)),
            'bnpl_share_pct' => min(100.0, max(0.0, $this->bnplSharePct)),
            'bnpl_fee_pct' => min(100.0, max(0.0, $this->bnplFeePct)),
            'gateway_fee_pct' => min(100.0, max(0.0, $this->gatewayFeePct)),
            'currency' => $client->currency ?: $econ->currency,
            'costs_entered_at' => now(),
        ]);

        $this->previewCodPoas();
        Notification::make()->title(__('Unit economics saved'))->success()->send();
    }

    public function recordCtwaEvent(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || trim($this->ctwaClid) === '') {
            return;
        }

        ProfitAndAttributionManager::captureCtwaEvent($client, [
            'ctwa_clid' => trim($this->ctwaClid),
            'event_name' => $this->ctwaEventName,
            'value' => $this->ctwaValue,
            'phone' => $this->ctwaPhone,
            'currency' => $client->currency ?: 'USD',
        ]);

        $this->ctwaClid = '';
        $this->ctwaPhone = '';
        Notification::make()->title(__('Event saved here. Nothing was sent to Meta.'))->success()->send();
    }

    public function addSkuSnapshot(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || trim($this->skuCode) === '') {
            return;
        }

        ProfitAndAttributionManager::recordSkuSnapshot($client, [
            'sku_code' => $this->skuCode,
            'product_title' => $this->skuTitle !== '' ? $this->skuTitle : $this->skuCode,
            'stock_qty' => $this->skuStockQty,
            'ad_spend' => $this->skuSpend,
            'gross_revenue' => $this->skuRevenue,
            'orders_count' => $this->skuOrders,
            'repeat_ltv_90d' => $this->skuRepeatLtv,
        ]);

        $this->skuCode = '';
        $this->skuTitle = '';
        Notification::make()->title(__('Product performance saved'))->success()->send();
    }

    public function saveCustomKpi(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || trim($this->kpiName) === '') {
            return;
        }

        $check = SafeFormulaEvaluator::validate($this->kpiFormula);
        if (! $check['valid']) {
            $this->formulaPreviewError = $check['error'];

            return;
        }

        ClientCustomKpi::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'name' => trim($this->kpiName),
            'formula_expression' => trim($this->kpiFormula),
            'format' => in_array($this->kpiFormat, ['currency', 'percent', 'decimal'], true) ? $this->kpiFormat : 'decimal',
            'show_on_client_report' => false,
        ]);

        $this->kpiName = '';
        $this->formulaPreviewError = null;
        Notification::make()->title(__('Custom KPI saved'))->success()->send();
    }

    public function importPosOfflineCsv(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || trim($this->posCsvInput) === '') {
            return;
        }

        $this->posMatchSummary = ProfitAndAttributionManager::matchOfflinePosCsv($client, $this->posCsvInput, true);
        $this->posCsvInput = '';
        Notification::make()->title(__('Offline CSV matched in hash-only mode'))->success()->send();
    }

    protected function canManageStores(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    /** E2-063: adds a store whose signed webhooks will feed this client. It stays "waiting" until a verified event arrives. */
    public function addStoreConnection(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || ! $this->canManageStores()) {
            return;
        }
        $this->validate([
            'storePlatform' => ['required', 'in:'.implode(',', ClientStoreConnection::PLATFORMS)],
            'storeIdentifier' => ['required', 'string', 'max:191'],
            'storeSecret' => ['required', 'string', 'min:16', 'max:255'],
        ]);
        ClientStoreConnection::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'platform' => $this->storePlatform,
            'store_identifier' => trim($this->storeIdentifier),
            'webhook_secret' => trim($this->storeSecret),
            'webhook_token' => Str::random(48),
            'status' => 'webhook_ready',
        ]);
        $this->reset(['storeIdentifier', 'storeSecret']);
        Notification::make()->title(__('Store added. Paste the receiver address in the store\'s webhook settings.'))->success()->send();
    }

    public function toggleStoreConnection(int $id): void
    {
        $connection = $this->storeConnection($id);
        if (! $connection) {
            return;
        }
        $connection->update(['status' => $connection->status === 'paused' ? ($connection->last_event_at ? 'active' : 'webhook_ready') : 'paused']);
    }

    public function deleteStoreConnection(int $id): void
    {
        $this->storeConnection($id)?->delete();
    }

    /** Copies the COD rates observed from the store into the agency's unit economics (the agency can still edit them). */
    public function useStoreRates(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || ! $this->canManageStores()) {
            return;
        }
        $econ = ClientUnitEconomic::where('agency_id', $client->agency_id)->where('client_id', $client->id)->first();
        $rates = $econ?->storeRates();
        if (! $rates || $rates['orders'] < (int) config('store_sync.min_orders_for_rates', 20)) {
            return;
        }
        $econ->update(['cod_share_pct' => $rates['cod_share_pct'] ?? 0, 'cod_return_rate_pct' => $rates['cod_return_rate_pct'] ?? 0]);
        $this->codSharePct = (float) ($rates['cod_share_pct'] ?? 0);
        $this->codReturnRatePct = (float) ($rates['cod_return_rate_pct'] ?? 0);
        Notification::make()->title(__('Store rates copied into the unit economics.'))->success()->send();
    }

    protected function storeConnection(int $id): ?ClientStoreConnection
    {
        $client = $this->resolveSelectedClient();
        if (! $client || ! $this->canManageStores()) {
            return null;
        }

        return ClientStoreConnection::where('agency_id', $client->agency_id)->where('client_id', $client->id)->find($id);
    }

    public function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $clients = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->get() : collect();
        $client = $this->resolveSelectedClient();

        if (! $client) {
            return [
                'client' => null,
                'clients' => $clients,
            ];
        }

        $econ = ClientUnitEconomic::where('agency_id', $client->agency_id)->where('client_id', $client->id)->first();
        $poas = $this->poasPreview;

        $skuMatrix = ProfitAndAttributionManager::skuMatrix($client);
        $oosBleed = ProfitAndAttributionManager::detectOutOfStockBleed($client);
        $ltvGateways = ProfitAndAttributionManager::rankLtvGateways($client);
        $bundles = ProfitAndAttributionManager::mineBundleAffinities($client);
        $promoBleed = ProfitAndAttributionManager::detectPromoCodeBleed($client);
        $hdyhau = ProfitAndAttributionManager::blendedHdyhauAttribution($client);

        $capiEvents = CapiBridgeEvent::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->latest('occurred_at')
            ->limit(10)
            ->get();

        $journeys = $capiEvents->isNotEmpty()
            ? $capiEvents->map(fn (CapiBridgeEvent $e) => [
                'touchpoints' => array_values(array_filter(array_merge(array_keys((array) ($e->click_ids_json ?? [])), [$e->channel]))),
                'value' => max(1.0, (float) $e->value),
            ])->all()
            : [];

        $mtaModels = ProfitAndAttributionManager::compareMtaModels($journeys);
        $customKpis = ProfitAndAttributionManager::evaluateClientKpis($client, $this->knownMetrics());

        return [
            'client' => $client,
            'clients' => $clients,
            'econ' => $econ,
            'poas' => $poas,
            'skuMatrix' => $skuMatrix,
            'oosBleed' => $oosBleed,
            'ltvGateways' => $ltvGateways,
            'bundles' => $bundles,
            'promoBleed' => $promoBleed,
            'hdyhau' => $hdyhau,
            'capiEvents' => $capiEvents,
            'mtaModels' => $mtaModels,
            'activeMtaCredits' => $mtaModels[$this->activeMtaModel] ?? [],
            'customKpis' => $customKpis,
            'currency' => (string) ($client->currency ?: ''),
            'storeConnections' => ClientStoreConnection::where('agency_id', $client->agency_id)->where('client_id', $client->id)->withCount('events')->orderBy('id')->get(),
            'storeRates' => $econ?->storeRates() ?? ['orders' => 0, 'cod_share_pct' => null, 'cod_return_rate_pct' => null],
            'storeMinOrders' => (int) config('store_sync.min_orders_for_rates', 20),
            'canManageStores' => $this->canManageStores(),
        ];
    }
}
