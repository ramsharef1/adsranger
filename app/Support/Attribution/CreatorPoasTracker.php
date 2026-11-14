<?php

namespace App\Support\Attribution;

use App\Models\Client;
use App\Models\ClientUnitEconomic;
use App\Models\CreatorPartnership;
use App\Support\TextClean;

class CreatorPoasTracker
{
    /**
     * Records a creator/influencer partnership and calculates true Net POAS
     * factoring flat fee, gifted product COGS, boosted Spark/Partnership spend,
     * and client unit economics (COGS% + COD return impact).
     *
     * @param  array{
     *   creator_name: string,
     *   platform?: string,
     *   flat_fee?: float|int|string,
     *   gifted_cogs?: float|int|string,
     *   boosted_spend?: float|int|string,
     *   promo_code?: ?string,
     *   utm_campaign?: ?string,
     *   attributed_orders?: int|string,
     *   gross_revenue?: float|int|string
     * }  $payload
     */
    public static function recordPartnership(Client $client, array $payload): CreatorPartnership
    {
        $platform = strtolower(trim((string) ($payload['platform'] ?? 'snapchat')));
        if (! in_array($platform, CreatorPartnership::PLATFORMS, true)) {
            $platform = 'snapchat';
        }

        $flatFee = max(0.0, (float) TextClean::western((string) ($payload['flat_fee'] ?? 0)));
        $giftedCogs = max(0.0, (float) TextClean::western((string) ($payload['gifted_cogs'] ?? 0)));
        $boostedSpend = max(0.0, (float) TextClean::western((string) ($payload['boosted_spend'] ?? 0)));
        $orders = max(0, (int) TextClean::western((string) ($payload['attributed_orders'] ?? 0)));
        $grossRevenue = max(0.0, (float) TextClean::western((string) ($payload['gross_revenue'] ?? 0)));

        $totalCreatorCost = $flatFee + $giftedCogs + $boostedSpend;
        $netContribution = self::computeContributionBeforeMedia($client, $grossRevenue, $orders);
        $netPoas = $totalCreatorCost > 0 ? round($netContribution / $totalCreatorCost, 3) : 0.0;

        $promo = trim((string) ($payload['promo_code'] ?? ''));
        $utm = trim((string) ($payload['utm_campaign'] ?? ''));

        return CreatorPartnership::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'creator_name' => trim((string) $payload['creator_name']),
            'platform' => $platform,
            'flat_fee' => round($flatFee, 3),
            'gifted_cogs' => round($giftedCogs, 3),
            'boosted_spend' => round($boostedSpend, 3),
            'promo_code' => $promo !== '' ? strtoupper($promo) : null,
            'utm_campaign' => $utm !== '' ? $utm : null,
            'attributed_orders' => $orders,
            'gross_revenue' => round($grossRevenue, 3),
            'net_poas' => $netPoas,
        ]);
    }

    public static function computeContributionBeforeMedia(Client $client, float $grossRevenue, int $orders): float
    {
        $econ = ClientUnitEconomic::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->first();

        if (! $econ || ! $econ->costs_entered_at) {
            return round($grossRevenue, 3);
        }

        $codShare = min(1.0, max(0.0, (float) $econ->cod_share_pct / 100.0));
        $codReturnRate = min(1.0, max(0.0, (float) $econ->cod_return_rate_pct / 100.0));
        $effectiveReturnShare = $codShare * $codReturnRate;

        $realizedRevenue = $grossRevenue * (1.0 - $effectiveReturnShare);
        $cogs = $realizedRevenue * min(1.0, max(0.0, (float) $econ->cogs_pct / 100.0));
        $returnedOrders = $orders * $effectiveReturnShare;
        $shippingCost = ($orders * (float) $econ->shipping_fee) + ($returnedOrders * (float) $econ->return_shipping_fee);

        return round($realizedRevenue - $cogs - $shippingCost, 3);
    }
}
