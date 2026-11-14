<?php

namespace App\Support\Attribution;

use App\Models\Client;
use App\Models\ClientUnitEconomic;
use App\Support\MetricsQuery;

/**
 * E2-071: Deterministic root-cause performance investigator decomposing CPA, ROAS, and POAS shifts
 * across CPC, CTR, CVR, AOV, and COD return rate with zero LLM cost.
 */
class PerformanceInvestigator
{
    /**
     * @param  array{impressions?: float|int, clicks?: float|int, spend?: float, conversions?: float, revenue?: float, cod_return_rate_pct?: float}  $prior
     * @param  array{impressions?: float|int, clicks?: float|int, spend?: float, conversions?: float, revenue?: float, cod_return_rate_pct?: float}  $current
     * @param  array<string, float>  $unitEcon
     * @return array<string, mixed>
     */
    public static function investigate(array $prior, array $current, array $unitEcon = []): array
    {
        $p = self::rates($prior, $unitEcon);
        $c = self::rates($current, $unitEcon);

        $cpaDelta = round($c['cpa'] - $p['cpa'], 3);
        $roasDelta = round($c['roas'] - $p['roas'], 3);
        $poasDelta = round($c['poas'] - $p['poas'], 3);

        // Exact logarithmic decomposition of CPA = CPC / CVR
        [$cpcOnCpa, $cvrOnCpa] = self::decomposeRatio($p['cpa'], $c['cpa'], [
            'cpc' => [$p['cpc'], $c['cpc'], 1.0],
            'cvr' => [$p['cvr'], $c['cvr'], -1.0],
        ]);

        // Exact logarithmic decomposition of ROAS = (CVR * AOV) / CPC
        [$cpcOnRoas, $cvrOnRoas, $aovOnRoas] = self::decomposeRatio($p['roas'], $c['roas'], [
            'cpc' => [$p['cpc'], $c['cpc'], -1.0],
            'cvr' => [$p['cvr'], $c['cvr'], 1.0],
            'aov' => [$p['aov'], $c['aov'], 1.0],
        ]);

        // Isolate COD return rate drag on POAS by holding current ad metrics fixed while swapping COD return rate
        $currentWithPriorCod = self::rates(
            array_merge($current, ['cod_return_rate_pct' => $p['cod_return_rate_pct']]),
            $unitEcon
        );
        $codEffectOnPoas = round($c['poas'] - $currentWithPriorCod['poas'], 3);
        $mediaEffectOnPoas = round($poasDelta - $codEffectOnPoas, 3);

        $ctrDeltaPct = $p['ctr'] > 0 ? round((($c['ctr'] - $p['ctr']) / $p['ctr']) * 100.0, 2) : 0.0;

        $drivers = [
            'cpc_shift' => $cpcOnRoas,
            'cvr_shift' => $cvrOnRoas,
            'aov_shift' => $aovOnRoas,
            'cod_return_shift' => $codEffectOnPoas,
        ];

        $worstKey = 'improved';
        $worstVal = 0.0;
        foreach ($drivers as $k => $v) {
            if ($v < $worstVal) {
                $worstVal = $v;
                $worstKey = $k;
            }
        }
        if ($worstKey === 'cpc_shift' && $ctrDeltaPct <= -15.0) {
            $worstKey = 'ctr_drop_raising_cpc';
        }

        return [
            'prior' => $p,
            'current' => $c,
            'deltas' => [
                'cpa_delta' => $cpaDelta,
                'roas_delta' => $roasDelta,
                'poas_delta' => $poasDelta,
                'ctr_delta_pct' => $ctrDeltaPct,
            ],
            'cpa_waterfall' => [
                'cpc_effect' => $cpcOnCpa,
                'cvr_effect' => $cvrOnCpa,
            ],
            'roas_waterfall' => [
                'cpc_effect' => $cpcOnRoas,
                'cvr_effect' => $cvrOnRoas,
                'aov_effect' => $aovOnRoas,
            ],
            'poas_waterfall' => [
                'media_funnel_effect' => $mediaEffectOnPoas,
                'cod_return_effect' => $codEffectOnPoas,
            ],
            'primary_root_cause' => $worstKey,
        ];
    }

    /**
     * Runs the waterfall directly from stored DailyMetric + ClientUnitEconomic windows for a client.
     *
     * @return array<string, mixed>
     */
    public static function investigateClient(
        Client $client,
        string $priorFrom,
        string $priorTo,
        string $currFrom,
        string $currTo,
    ): array {
        $pTotals = MetricsQuery::totals($client, $priorFrom, $priorTo);
        $cTotals = MetricsQuery::totals($client, $currFrom, $currTo);
        $econ = ClientUnitEconomic::where('agency_id', $client->agency_id)->where('client_id', $client->id)->first();

        $unitEcon = $econ ? [
            'cogs_pct' => (float) $econ->cogs_pct,
            'shipping_fee' => (float) $econ->shipping_fee,
            'return_shipping_fee' => (float) $econ->return_shipping_fee,
            'cod_share_pct' => (float) $econ->cod_share_pct,
            'cod_return_rate_pct' => (float) $econ->cod_return_rate_pct,
        ] : [];

        $mapWindow = fn (array $t): array => [
            'impressions' => (int) ($t['impressions'] ?? 0),
            'clicks' => (int) (($t['link_clicks'] ?? 0) ?: ($t['clicks'] ?? 0)),
            'spend' => (float) ($t['spend'] ?? 0.0),
            'conversions' => (float) (($t['purchases'] ?? 0) > 0 ? $t['purchases'] : ($t['results'] ?? 0.0)),
            'revenue' => (float) ($t['revenue'] ?? 0.0),
            'cod_return_rate_pct' => (float) ($unitEcon['cod_return_rate_pct'] ?? 0.0),
        ];

        return self::investigate($mapWindow($pTotals), $mapWindow($cTotals), $unitEcon);
    }

    /**
     * @param  array<string, float|int>  $m
     * @param  array<string, float>  $unitEcon
     * @return array<string, float>
     */
    private static function rates(array $m, array $unitEcon): array
    {
        $imp = max(0.0, (float) ($m['impressions'] ?? 0));
        $clicks = max(0.0, (float) ($m['clicks'] ?? 0));
        $spend = max(0.0, (float) ($m['spend'] ?? 0));
        $conv = max(0.0, (float) ($m['conversions'] ?? 0));
        $rev = max(0.0, (float) ($m['revenue'] ?? 0));

        $ctr = $imp > 0 ? ($clicks / $imp) : 0.0;
        $cpc = $clicks > 0 ? ($spend / $clicks) : 0.0;
        $cvr = $clicks > 0 ? ($conv / $clicks) : 0.0;
        $cpa = $conv > 0 ? ($spend / $conv) : 0.0;
        $aov = $conv > 0 ? ($rev / $conv) : 0.0;
        $roas = $spend > 0 ? ($rev / $spend) : 0.0;

        $cogsPct = min(100.0, max(0.0, (float) ($unitEcon['cogs_pct'] ?? 35.0))) / 100.0;
        $shipFee = max(0.0, (float) ($unitEcon['shipping_fee'] ?? 0.0));
        $retShipFee = max(0.0, (float) ($unitEcon['return_shipping_fee'] ?? 0.0));
        $codShare = min(100.0, max(0.0, (float) ($unitEcon['cod_share_pct'] ?? 50.0))) / 100.0;
        $codReturnRatePct = min(100.0, max(0.0, (float) ($m['cod_return_rate_pct'] ?? ($unitEcon['cod_return_rate_pct'] ?? 0.0))));
        $codReturn = $codReturnRatePct / 100.0;

        $realizedRev = max(0.0, $rev * (1.0 - $codShare * $codReturn));
        $cogs = $realizedRev * $cogsPct;
        $shipping = ($conv * $shipFee) + ($conv * $codShare * $codReturn * $retShipFee);
        $netProfit = $realizedRev - $cogs - $shipping - $spend;
        $poas = $spend > 0 ? ($netProfit / $spend) : 0.0;

        return [
            'impressions' => round($imp, 2),
            'clicks' => round($clicks, 2),
            'spend' => round($spend, 3),
            'conversions' => round($conv, 2),
            'revenue' => round($rev, 3),
            'ctr' => round($ctr, 6),
            'cpc' => round($cpc, 4),
            'cvr' => round($cvr, 6),
            'cpa' => round($cpa, 3),
            'aov' => round($aov, 3),
            'roas' => round($roas, 3),
            'cod_return_rate_pct' => round($codReturnRatePct, 2),
            'net_profit' => round($netProfit, 3),
            'poas' => round($poas, 3),
        ];
    }

    /**
     * Logarithmic mean decomposition so factor effects sum cleanly to ($y1 - $y0).
     *
     * @param  array<string, array{0: float, 1: float, 2: float}>  $factors
     * @return list<float>
     */
    private static function decomposeRatio(float $y0, float $y1, array $factors): array
    {
        $totalDelta = $y1 - $y0;
        if (abs($totalDelta) < 1e-9 || $y0 <= 0.0 || $y1 <= 0.0) {
            $zeros = array_fill(0, count($factors), 0.0);
            if (abs($totalDelta) >= 1e-9 && count($zeros) > 0) {
                $zeros[0] = round($totalDelta, 3);
            }

            return $zeros;
        }

        $logs = [];
        foreach ($factors as [$f0, $f1, $sign]) {
            $logs[] = ($f0 > 0.0 && $f1 > 0.0) ? ($sign * log($f1 / $f0)) : 0.0;
        }
        $sumLogs = array_sum($logs);
        if (abs($sumLogs) < 1e-9) {
            return array_fill(0, count($factors), 0.0);
        }

        $effects = [];
        $running = 0.0;
        $lastIdx = count($logs) - 1;
        foreach ($logs as $idx => $lg) {
            if ($idx === $lastIdx) {
                $effects[] = round($totalDelta - $running, 3);
            } else {
                $part = round($totalDelta * ($lg / $sumLogs), 3);
                $effects[] = $part;
                $running += $part;
            }
        }

        return $effects;
    }
}
