<?php

namespace App\Support;

use App\Models\Client;
use App\Models\InfluencerCampaign;

class InfluencerCampaignTracker
{
    public static function recordAndAttribute(
        Client $client,
        string $influencerName,
        string $platform,
        string $promoCode,
        float $fixedFee,
        float $commissionPercent,
        int $ordersCount,
        float $attributedRevenue
    ): InfluencerCampaign {
        $code = strtoupper(trim($promoCode));
        $commissionAmount = round($attributedRevenue * ($commissionPercent / 100), 4);
        $totalPayout = round($fixedFee + $commissionAmount, 4);
        $roas = $totalPayout > 0 ? round($attributedRevenue / $totalPayout, 4) : 0.0;
        $cac = $ordersCount > 0 ? round($totalPayout / $ordersCount, 4) : 0.0;

        return InfluencerCampaign::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'influencer_name' => trim($influencerName),
            'platform' => strtolower(trim($platform)),
            'promo_code' => $code,
            'utm_campaign' => 'inf_' . strtolower($code),
            'fixed_fee' => round($fixedFee, 4),
            'commission_percent' => round($commissionPercent, 2),
            'orders_count' => $ordersCount,
            'attributed_revenue' => round($attributedRevenue, 4),
            'total_payout' => $totalPayout,
            'effective_roas' => $roas,
            'cac' => $cac,
            'status' => $roas >= 2.5 ? 'top_performer' : 'active',
        ]);
    }
}