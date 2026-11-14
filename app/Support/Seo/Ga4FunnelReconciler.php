<?php

namespace App\Support\Seo;

use App\Models\Client;
use App\Models\ClientGa4FunnelRow;
use App\Models\SkuPerformanceSnapshot;
use App\Support\MetricsQuery;
use App\Support\TextClean;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * E2-069: GA4 Data API v1 runReport contract and deterministic 3-way reconciliation
 * comparing Ad Platform Claims vs GA4 Key Events vs Verified Store Orders.
 */
class Ga4FunnelReconciler
{
    public const ENDPOINT_TEMPLATE = 'https://analyticsdata.googleapis.com/v1beta/properties/%s:runReport';

    /**
     * @return array{ok: bool, status: string, rows_saved: int, http_status?: int}
     */
    public static function syncApi(
        Client $client,
        string $propertyId,
        ?string $accessToken,
        string $startDate,
        string $endDate,
    ): array {
        $cleanProp = preg_replace('#^properties/#i', '', trim((string) TextClean::western($propertyId))) ?? '';
        $token = trim((string) $accessToken);

        if ($cleanProp === '' || $token === '') {
            return [
                'ok' => false,
                'status' => 'unconfigured',
                'rows_saved' => 0,
            ];
        }

        $url = sprintf(self::ENDPOINT_TEMPLATE, rawurlencode($cleanProp));

        try {
            $res = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post($url, [
                    'dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]],
                    'dimensions' => [
                        ['name' => 'date'],
                        ['name' => 'sessionDefaultChannelGroup'],
                        ['name' => 'landingPagePlusQueryString'],
                    ],
                    'metrics' => [
                        ['name' => 'sessions'],
                        ['name' => 'engagedSessions'],
                        ['name' => 'addToCarts'],
                        ['name' => 'keyEvents'],
                        ['name' => 'purchaseRevenue'],
                    ],
                    'limit' => 5000,
                ]);
        } catch (Throwable) {
            return [
                'ok' => false,
                'status' => 'failed',
                'rows_saved' => 0,
            ];
        }

        if (! $res->successful()) {
            return [
                'ok' => false,
                'status' => 'failed',
                'rows_saved' => 0,
                'http_status' => $res->status(),
            ];
        }

        $payload = $res->json();
        if (! is_array($payload)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'rows_saved' => 0,
            ];
        }

        $rows = $payload['rows'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return [
                'ok' => true,
                'status' => 'empty',
                'rows_saved' => 0,
            ];
        }

        $saved = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $dims = (array) ($row['dimensionValues'] ?? []);
            $mets = (array) ($row['metricValues'] ?? []);

            $rawDate = trim((string) ($dims[0]['value'] ?? ''));
            $statDate = preg_match('/^(\d{4})(\d{2})(\d{2})$/', $rawDate, $m)
                ? "{$m[1]}-{$m[2]}-{$m[3]}"
                : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) ? $rawDate : null);

            if ($statDate === null) {
                continue;
            }

            $channelGroup = mb_substr(trim(SafeFetcher::toUtf8((string) ($dims[1]['value'] ?? 'Unassigned'))), 0, 120) ?: 'Unassigned';
            $landingPage = mb_substr(trim(SafeFetcher::toUtf8((string) ($dims[2]['value'] ?? '/'))), 0, 500) ?: '/';

            ClientGa4FunnelRow::updateOrCreate(
                [
                    'agency_id' => $client->agency_id,
                    'client_id' => $client->id,
                    'property_id' => mb_substr($cleanProp, 0, 64),
                    'stat_date' => $statDate,
                    'channel_group' => $channelGroup,
                    'landing_page' => $landingPage,
                ],
                [
                    'sessions' => max(0, (int) round((float) ($mets[0]['value'] ?? 0))),
                    'engaged_sessions' => max(0, (int) round((float) ($mets[1]['value'] ?? 0))),
                    'add_to_carts' => max(0, (int) round((float) ($mets[2]['value'] ?? 0))),
                    'key_events' => round(max(0.0, (float) ($mets[3]['value'] ?? 0)), 2),
                    'revenue' => round(max(0.0, (float) ($mets[4]['value'] ?? 0)), 3),
                ]
            );
            $saved++;
        }

        return [
            'ok' => $saved > 0,
            'status' => $saved > 0 ? 'synced' : 'empty',
            'rows_saved' => $saved,
        ];
    }

    /**
     * Deterministic 3-way reconciliation of Ad Claims vs GA4 Key Events vs Store Orders.
     *
     * @return array{
     *   currency: string,
     *   ad_spend: ?float,
     *   ad_conversions: float,
     *   ad_revenue: float,
     *   ga4_sessions: int,
     *   ga4_engaged_sessions: int,
     *   ga4_add_to_carts: int,
     *   ga4_key_events: float,
     *   ga4_revenue: float,
     *   store_orders: int,
     *   store_revenue: float,
     *   ad_vs_ga4_pct: ?float,
     *   ad_vs_store_pct: ?float,
     *   ga4_vs_store_pct: ?float,
     *   status: string
     * }
     */
    public static function reconcile(Client $client, string $startDate, string $endDate): array
    {
        $currency = strtoupper((string) ($client->currency ?: 'USD'));
        $adTotals = MetricsQuery::totals($client, $startDate, $endDate);
        $adSpend = $client->hide_spend ? null : round((float) ($adTotals['spend'] ?? 0.0), 3);
        $adPurchases = (float) ($adTotals['purchases'] ?? 0.0);
        $adConversions = round($adPurchases > 0 ? $adPurchases : (float) ($adTotals['results'] ?? 0.0), 2);
        $adRevenue = round((float) ($adTotals['revenue'] ?? 0.0), 3);

        $ga4Agg = ClientGa4FunnelRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('stat_date', '>=', $startDate)
            ->whereDate('stat_date', '<=', $endDate)
            ->selectRaw('COALESCE(SUM(sessions), 0) as s, COALESCE(SUM(engaged_sessions), 0) as es, COALESCE(SUM(add_to_carts), 0) as atc, COALESCE(SUM(key_events), 0) as ke, COALESCE(SUM(revenue), 0) as rev')
            ->first();

        $ga4Sessions = (int) ($ga4Agg->s ?? 0);
        $ga4Engaged = (int) ($ga4Agg->es ?? 0);
        $ga4AddToCarts = (int) ($ga4Agg->atc ?? 0);
        $ga4KeyEvents = round((float) ($ga4Agg->ke ?? 0.0), 2);
        $ga4Revenue = round((float) ($ga4Agg->rev ?? 0.0), 3);

        $storeAgg = SkuPerformanceSnapshot::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('period_date', '>=', $startDate)
            ->whereDate('period_date', '<=', $endDate)
            ->selectRaw('COALESCE(SUM(orders_count), 0) as ord, COALESCE(SUM(gross_revenue), 0) as rev')
            ->first();

        $storeOrders = (int) ($storeAgg->ord ?? 0);
        $storeRevenue = round((float) ($storeAgg->rev ?? 0.0), 3);

        $adVsGa4 = $ga4KeyEvents > 0 ? round((($adConversions - $ga4KeyEvents) / $ga4KeyEvents) * 100, 1) : null;
        $adVsStore = $storeOrders > 0 ? round((($adConversions - $storeOrders) / $storeOrders) * 100, 1) : null;
        $ga4VsStore = $storeOrders > 0 ? round((($ga4KeyEvents - $storeOrders) / $storeOrders) * 100, 1) : null;

        $status = 'insufficient_data';
        if ($adConversions > 0 || $ga4KeyEvents > 0 || $storeOrders > 0) {
            if (($adVsStore !== null && $adVsStore > 15.0) || ($adVsStore === null && $adVsGa4 !== null && $adVsGa4 > 20.0)) {
                $status = 'over_claimed';
            } elseif ($adVsStore !== null && $adVsStore < -15.0) {
                $status = 'under_tracked';
            } else {
                $status = 'aligned';
            }
        }

        return [
            'currency' => $currency,
            'ad_spend' => $adSpend,
            'ad_conversions' => $adConversions,
            'ad_revenue' => $adRevenue,
            'ga4_sessions' => $ga4Sessions,
            'ga4_engaged_sessions' => $ga4Engaged,
            'ga4_add_to_carts' => $ga4AddToCarts,
            'ga4_key_events' => $ga4KeyEvents,
            'ga4_revenue' => $ga4Revenue,
            'store_orders' => $storeOrders,
            'store_revenue' => $storeRevenue,
            'ad_vs_ga4_pct' => $adVsGa4,
            'ad_vs_store_pct' => $adVsStore,
            'ga4_vs_store_pct' => $ga4VsStore,
            'status' => $status,
        ];
    }
}
