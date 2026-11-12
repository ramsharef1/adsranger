<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\CapiBridgeEvent;
use App\Models\Client;
use App\Models\ClientCustomKpi;
use App\Models\ClientUnitEconomic;
use App\Support\Attribution\ProfitAndAttributionManager;
use App\Support\Attribution\SafeFormulaEvaluator;
use App\Support\CurrentClient;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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

    // #193 Unit economics & POAS simulator
    public float $cogsPct = 35.0;

    public float $shippingFee = 4.0;

    public float $returnShippingFee = 3.5;

    public float $codSharePct = 55.0;

    public float $codReturnRatePct = 22.0;

    public float $bnplSharePct = 25.0;

    public float $bnplFeePct = 6.0;

    public float $gatewayFeePct = 2.5;

    public float $sampleGrossRevenue = 10000.0;

    public float $sampleAdSpend = 2000.0;

    public int $sampleOrdersCount = 120;

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
            if ($econ) {
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

    /** View-only preview allowed under ReadOnlyGuard. */
    public function previewCodPoas(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client) {
            return;
        }

        $this->poasPreview = ProfitAndAttributionManager::computeCodPoas(
            $client,
            $this->sampleGrossRevenue,
            $this->sampleAdSpend,
            $this->sampleOrdersCount,
            [
                'cogs_pct' => $this->cogsPct,
                'shipping_fee' => $this->shippingFee,
                'return_shipping_fee' => $this->returnShippingFee,
                'cod_share_pct' => $this->codSharePct,
                'cod_return_rate_pct' => $this->codReturnRatePct,
                'bnpl_share_pct' => $this->bnplSharePct,
                'bnpl_fee_pct' => $this->bnplFeePct,
                'gateway_fee_pct' => $this->gatewayFeePct,
            ]
        );
    }

    /** View-only MTA switcher allowed under ReadOnlyGuard. */
    public function setMtaModel(string $model): void
    {
        if (in_array($model, ['first_touch', 'last_touch', 'linear', 'time_decay', 'position_based'], true)) {
            $this->activeMtaModel = $model;
        }
    }

    /** View-only KPI formula check allowed under ReadOnlyGuard. */
    public function previewFormula(): void
    {
        try {
            $this->formulaPreviewValue = SafeFormulaEvaluator::evaluate($this->kpiFormula, [
                'spend' => max(0.0, $this->sampleAdSpend),
                'revenue' => max(0.0, $this->sampleGrossRevenue),
                'net_profit' => (float) ($this->poasPreview['net_profit'] ?? 1500.0),
                'clicks' => 1200,
                'impressions' => 48000,
                'conversions' => max(1, $this->sampleOrdersCount),
                'leads' => 180,
            ]);
            $this->formulaPreviewError = null;
        } catch (InvalidArgumentException $e) {
            $this->formulaPreviewValue = null;
            $this->formulaPreviewError = $e->getMessage();
        }
    }

    public function saveUnitEconomics(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client) {
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
            'currency' => $client->currency ?: 'USD',
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
        Notification::make()->title(__('WhatsApp CAPI event queued in simulated mode'))->success()->send();
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
        Notification::make()->title(__('SKU snapshot recorded'))->success()->send();
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

    public function syncRfmAudiences(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client) {
            return;
        }

        ProfitAndAttributionManager::buildRfmSegments($client, [
            ['recency_days' => 10, 'frequency' => 4, 'monetary' => 650, 'cod_return_rate_pct' => 5],
            ['recency_days' => 60, 'frequency' => 3, 'monetary' => 280, 'cod_return_rate_pct' => 10],
            ['recency_days' => 20, 'frequency' => 2, 'monetary' => 120, 'cod_return_rate_pct' => 60],
        ], true);

        Notification::make()->title(__('Audience segments prepared in simulated mode'))->success()->send();
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
        $poas = $this->poasPreview !== []
            ? $this->poasPreview
            : ProfitAndAttributionManager::computeCodPoas($client, $this->sampleGrossRevenue, $this->sampleAdSpend, $this->sampleOrdersCount);

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
        $customKpis = ProfitAndAttributionManager::evaluateClientKpis($client, [
            'spend' => $this->sampleAdSpend,
            'revenue' => $this->sampleGrossRevenue,
            'net_profit' => (float) ($poas['net_profit'] ?? 0.0),
            'clicks' => 1200,
            'impressions' => 48000,
            'conversions' => max(1, $this->sampleOrdersCount),
            'leads' => 180,
        ]);

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
        ];
    }
}
