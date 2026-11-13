<?php

namespace App\Support\Attribution;

use App\Models\Agency;
use App\Models\CapiBridgeEvent;
use App\Models\Client;
use App\Models\ClientCustomKpi;
use App\Models\ClientUnitEconomic;
use App\Models\PostPurchaseSurvey;
use App\Models\PostPurchaseSurveyResponse;
use App\Models\SkuPerformanceSnapshot;
use App\Support\Modules;
use App\Support\NetworkDepthManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Wave B.2 (#193–#206): MENA COD-Adjusted POAS, ctwa_clid WhatsApp CAPI Bridge & SKU Profit Engine.
 * Strictly tenant-scoped (agency_id + client_id) and D-229 compliant (simulated / queued_offline states).
 */
class ProfitAndAttributionManager
{
    public static function moduleLiveFor(?Agency $agency): bool
    {
        if (! $agency instanceof Agency) {
            return false;
        }

        return Modules::enabled('intelligence_hub')
            || Modules::hasAgencyOverride('intelligence_hub', $agency)
            || in_array($agency->slug, (array) config('modules.preview_agencies', []), true);
    }

    public static function ensureUnitEconomics(Client $client): ClientUnitEconomic
    {
        $econ = ClientUnitEconomic::firstOrCreate(
            [
                'agency_id' => $client->agency_id,
                'client_id' => $client->id,
            ],
            [
                // UX-255: no invented costs; the hub calculates only after the agency enters its own (costs_entered_at).
                'cogs_pct' => 0,
                'shipping_fee' => 0,
                'return_shipping_fee' => 0,
                'cod_share_pct' => 0,
                'cod_return_rate_pct' => 0,
                'bnpl_share_pct' => 0,
                'bnpl_fee_pct' => 0,
                'gateway_fee_pct' => 0,
                'currency' => $client->currency ?: 'USD',
                'collector_token' => Str::random(40),
            ]
        );

        if (! $econ->collector_token) {
            $econ->update(['collector_token' => Str::random(40)]);
        }

        return $econ;
    }

    public static function ensureSurvey(Client $client): PostPurchaseSurvey
    {
        return PostPurchaseSurvey::firstOrCreate(
            [
                'agency_id' => $client->agency_id,
                'client_id' => $client->id,
            ],
            [
                'survey_token' => Str::random(40),
                'is_active' => true,
                'options_json' => [
                    'tiktok',
                    'meta_instagram',
                    'snapchat',
                    'google_search',
                    'whatsapp',
                    'friend_recommendation',
                ],
            ]
        );
    }

    /**
     * #193 COD-Adjusted POAS & Net Margin Engine.
     *
     * @param  array<string, float|int>|null  $overrides
     * @return array<string, mixed>
     */
    public static function computeCodPoas(
        Client $client,
        float $grossRevenue,
        float $adSpend,
        int $ordersCount,
        ?array $overrides = null
    ): array {
        $econ = static::ensureUnitEconomics($client);

        $cogsPct = min(100.0, max(0.0, (float) ($overrides['cogs_pct'] ?? $econ->cogs_pct)));
        $shippingFee = max(0.0, (float) ($overrides['shipping_fee'] ?? $econ->shipping_fee));
        $returnShippingFee = max(0.0, (float) ($overrides['return_shipping_fee'] ?? $econ->return_shipping_fee));
        $codSharePct = min(100.0, max(0.0, (float) ($overrides['cod_share_pct'] ?? $econ->cod_share_pct)));
        $codReturnRatePct = min(100.0, max(0.0, (float) ($overrides['cod_return_rate_pct'] ?? $econ->cod_return_rate_pct)));
        $bnplSharePct = min(100.0 - $codSharePct, max(0.0, (float) ($overrides['bnpl_share_pct'] ?? $econ->bnpl_share_pct)));
        $bnplFeePct = min(100.0, max(0.0, (float) ($overrides['bnpl_fee_pct'] ?? $econ->bnpl_fee_pct)));
        $gatewayFeePct = min(100.0, max(0.0, (float) ($overrides['gateway_fee_pct'] ?? $econ->gateway_fee_pct)));

        $codShare = $codSharePct / 100.0;
        $codReturnRate = $codReturnRatePct / 100.0;
        $bnplShare = $bnplSharePct / 100.0;
        $onlineCardShare = max(0.0, 1.0 - $codShare - $bnplShare);

        $codRevenueLoss = $grossRevenue * $codShare * $codReturnRate;
        $realizedRevenue = max(0.0, $grossRevenue - $codRevenueLoss);

        $cogsAmount = $realizedRevenue * ($cogsPct / 100.0);
        $outboundShipping = $ordersCount * $shippingFee;
        $returnedOrders = $ordersCount * $codShare * $codReturnRate;
        $returnShipping = $returnedOrders * $returnShippingFee;
        $bnplFees = $grossRevenue * $bnplShare * ($bnplFeePct / 100.0);
        $gatewayFees = $grossRevenue * $onlineCardShare * ($gatewayFeePct / 100.0);

        $operatingDeductions = $cogsAmount + $outboundShipping + $returnShipping + $bnplFees + $gatewayFees;
        $contributionMargin = $realizedRevenue - $operatingDeductions;
        $netProfit = $contributionMargin - $adSpend;

        $poas = $adSpend > 0 ? round($netProfit / $adSpend, 2) : 0.0;
        $contributionPoas = $adSpend > 0 ? round($contributionMargin / $adSpend, 2) : 0.0;
        $effectiveMarginRatio = $grossRevenue > 0 ? ($contributionMargin / $grossRevenue) : 0.0;
        $breakEvenRoas = $effectiveMarginRatio > 0 ? round(1.0 / $effectiveMarginRatio, 2) : null;

        return [
            'currency' => (string) ($client->currency ?: ''),
            'gross_revenue' => round($grossRevenue, 2),
            'cod_revenue_loss' => round($codRevenueLoss, 2),
            'realized_revenue' => round($realizedRevenue, 2),
            'cogs_amount' => round($cogsAmount, 2),
            'outbound_shipping' => round($outboundShipping, 2),
            'return_shipping' => round($returnShipping, 2),
            'bnpl_fees' => round($bnplFees, 2),
            'gateway_fees' => round($gatewayFees, 2),
            'contribution_margin' => round($contributionMargin, 2),
            'ad_spend' => round($adSpend, 2),
            'net_profit' => round($netProfit, 2),
            'poas' => $poas,
            'contribution_poas' => $contributionPoas,
            'net_margin_pct' => $grossRevenue > 0 ? round(($netProfit / $grossRevenue) * 100.0, 2) : 0.0,
            'break_even_roas' => $breakEvenRoas,
        ];
    }

    /**
     * #194 Click-to-WhatsApp (ctwa_clid) Closed-Loop CAPI Bridge.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function captureCtwaEvent(Client $client, array $payload): CapiBridgeEvent
    {
        $econ = static::ensureUnitEconomics($client);
        $occurredAt = isset($payload['occurred_at']) ? Carbon::parse((string) $payload['occurred_at']) : now();
        $ctwaClid = trim((string) ($payload['ctwa_clid'] ?? ''));
        $eventName = trim((string) ($payload['event_name'] ?? 'Lead')) ?: 'Lead';
        $dedupId = trim((string) ($payload['event_dedup_id'] ?? '')) ?: ('ctwa_'.hash('sha256', $client->id.'|'.$ctwaClid.'|'.$eventName));

        $hashedContact = $payload['hashed_contact_sha256']
            ?? static::normalizeAndHashContact($payload['email'] ?? null, $payload['phone'] ?? null);

        $deliveryState = ($payload['queue_mode'] ?? null) === 'offline' ? 'queued_offline' : 'simulated';

        return CapiBridgeEvent::updateOrCreate(
            [
                'agency_id' => $client->agency_id,
                'client_id' => $client->id,
                'event_dedup_id' => $dedupId,
            ],
            [
                'collector_token' => $econ->collector_token,
                'channel' => 'ctwa',
                'event_name' => $eventName,
                'ctwa_clid' => $ctwaClid !== '' ? $ctwaClid : null,
                'click_ids_json' => array_filter([
                    'ctwa_clid' => $ctwaClid ?: null,
                    'fbclid' => $payload['fbclid'] ?? null,
                ]),
                'free_window_expires_at' => $occurredAt->copy()->addHours(72),
                'hashed_contact_sha256' => $hashedContact,
                'value' => (float) ($payload['value'] ?? 0),
                'currency' => (string) ($payload['currency'] ?? $econ->currency ?? 'USD'),
                'delivery_state' => $deliveryState,
                'occurred_at' => $occurredAt,
            ]
        );
    }

    /**
     * #195 First-Party Server-Side Pixel & CAPI Relay (POST /collect/{token}).
     *
     * @param  array<string, mixed>  $payload
     * @return array{event: CapiBridgeEvent, duplicate: bool}
     */
    public static function ingestCollectorRelay(ClientUnitEconomic $econ, array $payload): array
    {
        $clickIds = [];
        foreach (['fbclid', 'gclid', 'ttclid', 'ScCid', 'ctwa_clid'] as $param) {
            if (! empty($payload[$param]) && is_string($payload[$param])) {
                $clickIds[$param] = substr(trim($payload[$param]), 0, 191);
            }
        }

        $eventName = substr(trim((string) ($payload['event_name'] ?? 'PageView')) ?: 'PageView', 0, 64);
        $dedupId = substr(trim((string) ($payload['event_dedup_id'] ?? '')), 0, 128);
        if ($dedupId === '') {
            $dedupId = 'rel_'.substr(hash('sha256', json_encode([$econ->client_id, $eventName, $clickIds, $payload['value'] ?? 0])), 0, 40);
        }

        $existing = CapiBridgeEvent::where('agency_id', $econ->agency_id)
            ->where('client_id', $econ->client_id)
            ->where('event_dedup_id', $dedupId)
            ->first();

        if ($existing) {
            return ['event' => $existing, 'duplicate' => true];
        }

        $occurredAt = isset($payload['occurred_at']) ? Carbon::parse((string) $payload['occurred_at']) : now();
        $ctwaClid = $clickIds['ctwa_clid'] ?? null;
        $hashedContact = ! empty($payload['hashed_contact_sha256']) && preg_match('/^[a-f0-9]{64}$/i', (string) $payload['hashed_contact_sha256'])
            ? strtolower((string) $payload['hashed_contact_sha256'])
            : static::normalizeAndHashContact($payload['email'] ?? null, $payload['phone'] ?? null);

        $deliveryState = ($payload['delivery_mode'] ?? null) === 'queued_offline' ? 'queued_offline' : 'simulated';

        $event = CapiBridgeEvent::create([
            'agency_id' => $econ->agency_id,
            'client_id' => $econ->client_id,
            'collector_token' => $econ->collector_token,
            'channel' => $ctwaClid ? 'ctwa' : 'pixel_relay',
            'event_name' => $eventName,
            'event_dedup_id' => $dedupId,
            'ctwa_clid' => $ctwaClid,
            'click_ids_json' => $clickIds,
            'free_window_expires_at' => $ctwaClid ? $occurredAt->copy()->addHours(72) : null,
            'hashed_contact_sha256' => $hashedContact,
            'value' => round((float) ($payload['value'] ?? 0), 2),
            'currency' => strtoupper(substr(trim((string) ($payload['currency'] ?? $econ->currency ?? 'USD')), 0, 10)),
            'delivery_state' => $deliveryState,
            'occurred_at' => $occurredAt,
        ]);

        return ['event' => $event, 'duplicate' => false];
    }

    /**
     * #196 SKU-Level Hero / Sidekick / Villain / Zombie Matrix.
     */
    public static function classifySkuQuadrant(float $adSpend, float $netProfit, int $ordersCount, float $spendBenchmark = 100.0): string
    {
        $highSpend = $adSpend >= max(1.0, $spendBenchmark);

        if ($highSpend && $netProfit > 0 && $ordersCount > 0) {
            return 'hero';
        }
        if (! $highSpend && $netProfit > 0 && $ordersCount > 0) {
            return 'sidekick';
        }
        if ($highSpend && $netProfit <= 0) {
            return 'villain';
        }

        return 'zombie';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function recordSkuSnapshot(Client $client, array $data): SkuPerformanceSnapshot
    {
        $adSpend = (float) ($data['ad_spend'] ?? 0);
        $grossRevenue = (float) ($data['gross_revenue'] ?? 0);
        $cogsAmount = (float) ($data['cogs_amount'] ?? ($grossRevenue * 0.35));
        $netProfit = array_key_exists('net_profit', $data)
            ? (float) $data['net_profit']
            : ($grossRevenue - $cogsAmount - $adSpend);
        $ordersCount = (int) ($data['orders_count'] ?? 0);
        $benchmark = (float) ($data['spend_benchmark'] ?? 100.0);

        return SkuPerformanceSnapshot::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'sku_code' => trim((string) $data['sku_code']),
            'product_title' => trim((string) ($data['product_title'] ?? $data['sku_code'])),
            'stock_status' => (string) ($data['stock_status'] ?? (((int) ($data['stock_qty'] ?? 10)) <= 0 ? 'out_of_stock' : 'in_stock')),
            'stock_qty' => (int) ($data['stock_qty'] ?? 0),
            'ad_spend' => round($adSpend, 2),
            'gross_revenue' => round($grossRevenue, 2),
            'cogs_amount' => round($cogsAmount, 2),
            'net_profit' => round($netProfit, 2),
            'orders_count' => max(0, $ordersCount),
            'repeat_ltv_90d' => round((float) ($data['repeat_ltv_90d'] ?? 0), 2),
            'quadrant' => static::classifySkuQuadrant($adSpend, $netProfit, $ordersCount, $benchmark),
            'co_purchased_skus_json' => $data['co_purchased_skus_json'] ?? [],
            'promo_codes_json' => $data['promo_codes_json'] ?? [],
            'period_date' => $data['period_date'] ?? now()->toDateString(),
        ]);
    }

    /**
     * @return array{counts: array<string, int>, items: Collection<int, SkuPerformanceSnapshot>}
     */
    public static function skuMatrix(Client $client): array
    {
        $rows = SkuPerformanceSnapshot::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->orderByDesc('period_date')
            ->orderByDesc('net_profit')
            ->get();

        $counts = ['hero' => 0, 'sidekick' => 0, 'villain' => 0, 'zombie' => 0];
        foreach ($rows as $row) {
            if (isset($counts[$row->quadrant])) {
                $counts[$row->quadrant]++;
            }
        }

        return ['counts' => $counts, 'items' => $rows];
    }

    /**
     * #197 Out-of-Stock & Broken-Variant Ad Bleed Guard.
     *
     * @return array{wasted_spend: float, flagged_skus: list<array<string, mixed>>}
     */
    public static function detectOutOfStockBleed(Client $client): array
    {
        $rows = SkuPerformanceSnapshot::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->where('ad_spend', '>', 0)
            ->where(function ($q) {
                $q->where('stock_qty', '<=', 0)
                    ->orWhereIn('stock_status', ['out_of_stock', 'broken_variant']);
            })
            ->orderByDesc('ad_spend')
            ->get();

        $flagged = [];
        $wasted = 0.0;
        foreach ($rows as $r) {
            $wasted += (float) $r->ad_spend;
            $flagged[] = [
                'sku_code' => $r->sku_code,
                'product_title' => $r->product_title,
                'stock_status' => $r->stock_status,
                'stock_qty' => $r->stock_qty,
                'ad_spend' => round((float) $r->ad_spend, 2),
            ];
        }

        return [
            'wasted_spend' => round($wasted, 2),
            'flagged_skus' => $flagged,
        ];
    }

    /**
     * #198 First-Product LTV Gateway Analyzer.
     *
     * @return list<array<string, mixed>>
     */
    public static function rankLtvGateways(Client $client): array
    {
        return SkuPerformanceSnapshot::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->where('orders_count', '>', 0)
            ->orderByDesc('repeat_ltv_90d')
            ->limit(10)
            ->get()
            ->map(function (SkuPerformanceSnapshot $r): array {
                $cac = $r->orders_count > 0 && $r->ad_spend > 0 ? ($r->ad_spend / $r->orders_count) : 0.0;

                return [
                    'sku_code' => $r->sku_code,
                    'product_title' => $r->product_title,
                    'orders_count' => $r->orders_count,
                    'repeat_ltv_90d' => round((float) $r->repeat_ltv_90d, 2),
                    'cac' => round($cac, 2),
                    'ltv_to_cac' => $cac > 0 ? round($r->repeat_ltv_90d / $cac, 2) : null,
                ];
            })
            ->all();
    }

    /**
     * #199 Cart Basket & "Frequently Bought Together" Bundle Affinity Miner.
     *
     * @return list<array{pair: string, primary_sku: string, companion_sku: string, co_purchases: int}>
     */
    public static function mineBundleAffinities(Client $client): array
    {
        $rows = SkuPerformanceSnapshot::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->get();

        $pairs = [];
        foreach ($rows as $row) {
            foreach ((array) ($row->co_purchased_skus_json ?? []) as $item) {
                $companion = is_array($item) ? (string) ($item['sku'] ?? '') : (string) $item;
                $count = is_array($item) ? (int) ($item['count'] ?? 1) : 1;
                if ($companion === '' || $companion === $row->sku_code) {
                    continue;
                }
                $sorted = [$row->sku_code, $companion];
                sort($sorted);
                $key = $sorted[0].' + '.$sorted[1];
                if (! isset($pairs[$key])) {
                    $pairs[$key] = [
                        'pair' => $key,
                        'primary_sku' => $sorted[0],
                        'companion_sku' => $sorted[1],
                        'co_purchases' => 0,
                    ];
                }
                $pairs[$key]['co_purchases'] += max(1, $count);
            }
        }

        usort($pairs, fn (array $a, array $b) => $b['co_purchases'] <=> $a['co_purchases']);

        return array_slice(array_values($pairs), 0, 10);
    }

    /**
     * #200 Promo-Code & Leaked Influencer Coupon Bleed Detector.
     *
     * @return array{total_bleed_amount: float, flagged_codes: list<array<string, mixed>>}
     */
    public static function detectPromoCodeBleed(Client $client): array
    {
        $rows = SkuPerformanceSnapshot::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->get();

        $codes = [];
        foreach ($rows as $row) {
            foreach ((array) ($row->promo_codes_json ?? []) as $entry) {
                if (! is_array($entry) || empty($entry['code'])) {
                    continue;
                }
                $code = strtoupper(trim((string) $entry['code']));
                $codes[$code] ??= [
                    'code' => $code,
                    'discount_pct' => (float) ($entry['discount_pct'] ?? 0),
                    'total_uses' => 0,
                    'unattributed_uses' => 0,
                    'discount_amount' => 0.0,
                ];
                $codes[$code]['discount_pct'] = max($codes[$code]['discount_pct'], (float) ($entry['discount_pct'] ?? 0));
                $codes[$code]['total_uses'] += (int) ($entry['total_uses'] ?? 0);
                $codes[$code]['unattributed_uses'] += (int) ($entry['unattributed_uses'] ?? 0);
                $codes[$code]['discount_amount'] += (float) ($entry['discount_amount'] ?? 0);
            }
        }

        $flagged = [];
        $bleed = 0.0;
        foreach ($codes as $item) {
            $unattributedShare = $item['total_uses'] > 0
                ? (($item['unattributed_uses'] / $item['total_uses']) * 100.0)
                : 0.0;

            if ($item['discount_pct'] >= 20.0 || $unattributedShare >= 50.0) {
                $item['unattributed_share_pct'] = round($unattributedShare, 1);
                $item['discount_amount'] = round($item['discount_amount'], 2);
                $bleed += $item['discount_amount'];
                $flagged[] = $item;
            }
        }

        return [
            'total_bleed_amount' => round($bleed, 2),
            'flagged_codes' => $flagged,
        ];
    }

    /**
     * #201 Automated RFM Buyer Segmentation (ready for #138 Audience Sync with D-229 simulated honesty state).
     *
     * @param  list<array<string, mixed>>  $buyers
     * @param  list<string>  $targetNetworks
     * @return array<string, array{count: int, sync_status: string, sync_id: ?int}>
     */
    public static function buildRfmSegments(
        Client $client,
        array $buyers = [],
        bool $syncToNetworks = false,
        array $targetNetworks = ['meta', 'tiktok']
    ): array {
        $segments = [
            'vip_whales' => 0,
            'at_risk' => 0,
            'high_cod_return_exclusion' => 0,
        ];

        foreach ($buyers as $buyer) {
            $recency = (int) ($buyer['recency_days'] ?? 30);
            $frequency = (int) ($buyer['frequency'] ?? 1);
            $monetary = (float) ($buyer['monetary'] ?? 0);
            $codReturnPct = (float) ($buyer['cod_return_rate_pct'] ?? 0);

            if ($codReturnPct >= 40.0) {
                $segments['high_cod_return_exclusion']++;
            } elseif ($recency <= 30 && $frequency >= 3 && $monetary >= 300.0) {
                $segments['vip_whales']++;
            } elseif ($recency > 45 && $frequency >= 2) {
                $segments['at_risk']++;
            }
        }

        $result = [];
        $sourceTypeMap = [
            'vip_whales' => 'high_ltv',
            'at_risk' => 'crm_leads',
            'high_cod_return_exclusion' => 'cod_exclusions',
        ];

        foreach ($segments as $segmentKey => $count) {
            $syncId = null;
            $status = 'simulated';
            if ($syncToNetworks && $count > 0) {
                $sync = NetworkDepthManager::syncAudienceList(
                    $client,
                    'RFM: '.$segmentKey,
                    $sourceTypeMap[$segmentKey],
                    $targetNetworks,
                    $count
                );
                $syncId = $sync->id;
                $status = $sync->status;
            }
            $result[$segmentKey] = [
                'count' => $count,
                'sync_status' => $status,
                'sync_id' => $syncId,
            ];
        }

        return $result;
    }

    /**
     * #202 Embeddable Post-Purchase HDYHAU Micro-Survey & Blended Share.
     *
     * @param  array<string, mixed>  $data
     */
    public static function recordSurveyResponse(PostPurchaseSurvey $survey, array $data): PostPurchaseSurveyResponse
    {
        return PostPurchaseSurveyResponse::create([
            'agency_id' => $survey->agency_id,
            'client_id' => $survey->client_id,
            'post_purchase_survey_id' => $survey->id,
            'channel_choice' => substr(trim((string) $data['channel_choice']), 0, 80),
            'order_ref' => isset($data['order_ref']) ? substr(trim((string) $data['order_ref']), 0, 120) : null,
            'order_value' => round((float) ($data['order_value'] ?? 0), 2),
            'currency' => strtoupper(substr(trim((string) ($data['currency'] ?? $survey->client?->currency ?? 'USD')), 0, 10)),
        ]);
    }

    /**
     * @param  array<string, float>  $platformSharesPct
     * @return array{total_responses: int, channels: list<array{channel: string, survey_share_pct: float, platform_share_pct: float, blended_share_pct: float}>}
     */
    public static function blendedHdyhauAttribution(Client $client, array $platformSharesPct = []): array
    {
        $responses = PostPurchaseSurveyResponse::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->get();

        $total = $responses->count();
        $counts = [];
        foreach ($responses as $r) {
            $counts[$r->channel_choice] = ($counts[$r->channel_choice] ?? 0) + 1;
        }

        $allChannels = array_values(array_unique(array_merge(array_keys($counts), array_keys($platformSharesPct))));
        sort($allChannels);

        $channels = [];
        foreach ($allChannels as $ch) {
            $surveyPct = $total > 0 ? (($counts[$ch] ?? 0) / $total) * 100.0 : 0.0;
            $platPct = (float) ($platformSharesPct[$ch] ?? 0.0);
            $blended = ($total > 0 && $platformSharesPct !== [])
                ? (($surveyPct * 0.5) + ($platPct * 0.5))
                : ($total > 0 ? $surveyPct : $platPct);

            $channels[] = [
                'channel' => $ch,
                'survey_share_pct' => round($surveyPct, 1),
                'platform_share_pct' => round($platPct, 1),
                'blended_share_pct' => round($blended, 1),
            ];
        }

        usort($channels, fn (array $a, array $b) => $b['blended_share_pct'] <=> $a['blended_share_pct']);

        return [
            'total_responses' => $total,
            'channels' => $channels,
        ];
    }

    /**
     * #203 Lightweight Automated Marketing Mix Model (MMM) — deterministic ridge regression in pure PHP.
     *
     * @param  list<array{spends: array<string, float>, revenue: float}>  $weeklyObservations
     * @return array{baseline: float, channels: array<string, array{coefficient: float, marginal_roi: float, current_spend: float}>, status: string}
     */
    public static function estimateMmm(array $weeklyObservations, float $ridgeLambda = 1.0): array
    {
        if (count($weeklyObservations) < 3) {
            return ['baseline' => 0.0, 'channels' => [], 'status' => 'insufficient_weeks'];
        }

        $channelNames = [];
        foreach ($weeklyObservations as $obs) {
            foreach (array_keys((array) ($obs['spends'] ?? [])) as $ch) {
                if (! in_array($ch, $channelNames, true)) {
                    $channelNames[] = (string) $ch;
                }
            }
        }

        if ($channelNames === []) {
            return ['baseline' => 0.0, 'channels' => [], 'status' => 'no_channels'];
        }

        $p = count($channelNames) + 1; // col 0 = intercept, cols 1..k = log(1 + spend)
        $xtx = array_fill(0, $p, array_fill(0, $p, 0.0));
        $xty = array_fill(0, $p, 0.0);
        $latestSpend = array_fill_keys($channelNames, 0.0);

        foreach ($weeklyObservations as $obs) {
            $row = [1.0];
            foreach ($channelNames as $ch) {
                $s = max(0.0, (float) ($obs['spends'][$ch] ?? 0.0));
                $latestSpend[$ch] = $s;
                $row[] = log(1.0 + $s);
            }
            $y = (float) ($obs['revenue'] ?? 0.0);

            for ($i = 0; $i < $p; $i++) {
                $xty[$i] += $row[$i] * $y;
                for ($j = 0; $j < $p; $j++) {
                    $xtx[$i][$j] += $row[$i] * $row[$j];
                }
            }
        }

        // Ridge penalty on slope coefficients (leave intercept unpenalized)
        for ($i = 1; $i < $p; $i++) {
            $xtx[$i][$i] += max(0.01, $ridgeLambda);
        }

        $beta = static::solveLinearSystem($xtx, $xty);
        $channelsOut = [];

        foreach ($channelNames as $idx => $ch) {
            $coef = max(0.0, $beta[$idx + 1] ?? 0.0);
            $cur = $latestSpend[$ch];
            $marginalRoi = $coef / (1.0 + $cur);

            $channelsOut[$ch] = [
                'coefficient' => round($coef, 4),
                'marginal_roi' => round($marginalRoi, 4),
                'current_spend' => round($cur, 2),
            ];
        }

        return [
            'baseline' => round(max(0.0, $beta[0] ?? 0.0), 2),
            'channels' => $channelsOut,
            'status' => 'deterministic_ridge',
        ];
    }

    /**
     * #204 Multi-Touch Attribution (MTA) Model Switcher.
     *
     * @param  list<array{touchpoints: list<string>, value: float}>  $journeys
     * @return array<string, array<string, float>>
     */
    public static function compareMtaModels(array $journeys): array
    {
        $models = [
            'first_touch' => [],
            'last_touch' => [],
            'linear' => [],
            'time_decay' => [],
            'position_based' => [],
        ];

        foreach ($journeys as $journey) {
            $tps = array_values(array_filter(array_map('strval', (array) ($journey['touchpoints'] ?? []))));
            $value = (float) ($journey['value'] ?? 0.0);
            $n = count($tps);
            if ($n === 0 || $value <= 0) {
                continue;
            }

            // First-touch & Last-touch
            $models['first_touch'][$tps[0]] = ($models['first_touch'][$tps[0]] ?? 0.0) + $value;
            $models['last_touch'][$tps[$n - 1]] = ($models['last_touch'][$tps[$n - 1]] ?? 0.0) + $value;

            // Linear
            $linearShare = $value / $n;
            foreach ($tps as $tp) {
                $models['linear'][$tp] = ($models['linear'][$tp] ?? 0.0) + $linearShare;
            }

            // Time-decay (exponential weight 2^(i - (n - 1)))
            $decayWeights = [];
            $weightSum = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $w = pow(2.0, $i - ($n - 1));
                $decayWeights[$i] = $w;
                $weightSum += $w;
            }
            for ($i = 0; $i < $n; $i++) {
                $tp = $tps[$i];
                $models['time_decay'][$tp] = ($models['time_decay'][$tp] ?? 0.0) + ($value * ($decayWeights[$i] / $weightSum));
            }

            // Position-Based (U-Shaped 40 / 20 / 40)
            if ($n === 1) {
                $models['position_based'][$tps[0]] = ($models['position_based'][$tps[0]] ?? 0.0) + $value;
            } elseif ($n === 2) {
                $models['position_based'][$tps[0]] = ($models['position_based'][$tps[0]] ?? 0.0) + ($value * 0.5);
                $models['position_based'][$tps[1]] = ($models['position_based'][$tps[1]] ?? 0.0) + ($value * 0.5);
            } else {
                $models['position_based'][$tps[0]] = ($models['position_based'][$tps[0]] ?? 0.0) + ($value * 0.4);
                $models['position_based'][$tps[$n - 1]] = ($models['position_based'][$tps[$n - 1]] ?? 0.0) + ($value * 0.4);
                $middleShare = ($value * 0.2) / ($n - 2);
                for ($i = 1; $i < $n - 1; $i++) {
                    $tp = $tps[$i];
                    $models['position_based'][$tp] = ($models['position_based'][$tp] ?? 0.0) + $middleShare;
                }
            }
        }

        foreach ($models as $mKey => $channelMap) {
            foreach ($channelMap as $ch => $val) {
                $models[$mKey][$ch] = round($val, 2);
            }
        }

        return $models;
    }

    /**
     * #205 Custom Calculated KPI Formula Evaluator for saved client KPIs.
     *
     * @param  array<string, float|int>  $metrics
     * @return list<array{id: int, name: string, formula: string, value: float, formatted: string}>
     */
    public static function evaluateClientKpis(Client $client, array $metrics): array
    {
        $kpis = ClientCustomKpi::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->orderBy('name')
            ->get();

        $out = [];
        foreach ($kpis as $kpi) {
            // UX-255: a KPI whose formula needs a figure we don't have shows "—", never a 0 from a missing value.
            $needs = SafeFormulaEvaluator::validate($kpi->formula_expression)['variables'];
            $val = null;
            if (array_diff($needs, array_keys($metrics)) === []) {
                try {
                    $val = SafeFormulaEvaluator::evaluate($kpi->formula_expression, $metrics);
                } catch (\InvalidArgumentException) {
                    $val = null;
                }
            }
            $out[] = [
                'id' => $kpi->id,
                'name' => $kpi->name,
                'formula' => $kpi->formula_expression,
                'value' => $val,
                'formatted' => $val === null ? '—' : SafeFormulaEvaluator::formatValue($val, $kpi->format, (string) ($client->currency ?: '')),
            ];
        }

        return $out;
    }

    /**
     * #206 Privacy-Hashed POS / Offline CSV Matcher (SHA-256).
     */
    public static function normalizeAndHashContact(?string $email, ?string $phone): ?string
    {
        $normEmail = strtolower(trim((string) $email));
        if ($normEmail !== '' && filter_var($normEmail, FILTER_VALIDATE_EMAIL)) {
            return hash('sha256', $normEmail);
        }

        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $digits = ltrim($digits, '0');
        if (strlen($digits) >= 7) {
            return hash('sha256', $digits);
        }

        return null;
    }

    /**
     * @return array{processed_rows: int, matched_rows: int, matched_revenue: float, mode: string}
     */
    public static function matchOfflinePosCsv(Client $client, string $csvContent, bool $hashOnly = true): array
    {
        $econ = static::ensureUnitEconomics($client);
        $lines = preg_split('/\R/', trim($csvContent)) ?: [];
        if (count($lines) < 2) {
            return ['processed_rows' => 0, 'matched_rows' => 0, 'matched_revenue' => 0.0, 'mode' => 'queued_offline'];
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), str_getcsv((string) array_shift($lines)));
        $processed = 0;
        $matched = 0;
        $matchedRevenue = 0.0;

        foreach ($lines as $idx => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $row = [];
            foreach ($header as $i => $colName) {
                $row[$colName] = $cols[$i] ?? '';
            }

            $hash = ! empty($row['contact_sha256']) && preg_match('/^[a-f0-9]{64}$/i', (string) $row['contact_sha256'])
                ? strtolower((string) $row['contact_sha256'])
                : static::normalizeAndHashContact($row['email'] ?? null, $row['phone'] ?? null);

            if (! $hash) {
                continue;
            }

            $processed++;
            $value = round((float) ($row['value'] ?? 0), 2);
            $orderRef = trim((string) ($row['order_ref'] ?? ('pos_'.$idx)));

            $priorTouch = CapiBridgeEvent::where('agency_id', $client->agency_id)
                ->where('client_id', $client->id)
                ->where('hashed_contact_sha256', $hash)
                ->whereIn('channel', ['ctwa', 'pixel_relay'])
                ->latest('occurred_at')
                ->first();

            if ($priorTouch) {
                $matched++;
                $matchedRevenue += $value;
            }

            // Never persist raw email or phone in CapiBridgeEvent (hash-only privacy guarantee)
            CapiBridgeEvent::updateOrCreate(
                [
                    'agency_id' => $client->agency_id,
                    'client_id' => $client->id,
                    'event_dedup_id' => 'pos_'.substr(hash('sha256', $client->id.'|'.$hash.'|'.$orderRef), 0, 48),
                ],
                [
                    'collector_token' => $econ->collector_token,
                    'channel' => 'pos_offline',
                    'event_name' => 'OfflinePurchase',
                    'ctwa_clid' => $priorTouch?->ctwa_clid,
                    'click_ids_json' => $hashOnly ? ($priorTouch?->click_ids_json ?? []) : ($priorTouch?->click_ids_json ?? []),
                    'free_window_expires_at' => $priorTouch?->free_window_expires_at,
                    'hashed_contact_sha256' => $hash,
                    'value' => $value,
                    'currency' => strtoupper(substr(trim((string) ($row['currency'] ?? $econ->currency ?? 'USD')), 0, 10)),
                    'delivery_state' => 'queued_offline',
                    'occurred_at' => ! empty($row['occurred_at']) ? Carbon::parse((string) $row['occurred_at']) : now(),
                ]
            );
        }

        return [
            'processed_rows' => $processed,
            'matched_rows' => $matched,
            'matched_revenue' => round($matchedRevenue, 2),
            'mode' => 'queued_offline',
        ];
    }

    /**
     * Gaussian elimination with partial pivoting.
     *
     * @param  array<int, array<int, float>>  $a
     * @param  array<int, float>  $b
     * @return array<int, float>
     */
    protected static function solveLinearSystem(array $a, array $b): array
    {
        $n = count($b);
        for ($i = 0; $i < $n; $i++) {
            $maxRow = $i;
            for ($k = $i + 1; $k < $n; $k++) {
                if (abs($a[$k][$i]) > abs($a[$maxRow][$i])) {
                    $maxRow = $k;
                }
            }
            [$a[$i], $a[$maxRow]] = [$a[$maxRow], $a[$i]];
            [$b[$i], $b[$maxRow]] = [$b[$maxRow], $b[$i]];

            $pivot = $a[$i][$i];
            if (abs($pivot) < 1e-12) {
                continue;
            }

            for ($k = $i + 1; $k < $n; $k++) {
                $factor = $a[$k][$i] / $pivot;
                $b[$k] -= $factor * $b[$i];
                for ($j = $i; $j < $n; $j++) {
                    $a[$k][$j] -= $factor * $a[$i][$j];
                }
            }
        }

        $x = array_fill(0, $n, 0.0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $sum = $b[$i];
            for ($j = $i + 1; $j < $n; $j++) {
                $sum -= $a[$i][$j] * $x[$j];
            }
            $x[$i] = abs($a[$i][$i]) > 1e-12 ? ($sum / $a[$i][$i]) : 0.0;
        }

        return $x;
    }
}
