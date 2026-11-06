<?php

namespace App\Support;

use App\Models\BlendedRoasMetric;
use App\Models\Client;
use App\Models\DailyMetric;

class BlendedRoasCalculator
{
    public static function computeForClientDate(Client $client, string $date): BlendedRoasMetric
    {
        $rows = DailyMetric::query()
            ->where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('date', $date)
            ->get();

        $totalSpend = (float) $rows->sum('spend');
        $totalRevenue = (float) $rows->sum('revenue');
        $totalConversions = (float) $rows->sum(fn ($r) => (float) ($r->conversions ?: $r->results ?? 0));

        $breakdown = [];
        foreach ($rows->groupBy('network') as $network => $netRows) {
            $netSpend = (float) $netRows->sum('spend');
            $netRev = (float) $netRows->sum('revenue');
            $breakdown[$network] = [
                'spend' => round($netSpend, 4),
                'revenue' => round($netRev, 4),
                'roas' => $netSpend > 0 ? round($netRev / $netSpend, 4) : 0.0,
            ];
        }

        $mer = $totalSpend > 0 ? round($totalRevenue / $totalSpend, 4) : 0.0;

        return BlendedRoasMetric::updateOrCreate(
            [
                'agency_id' => $client->agency_id,
                'client_id' => $client->id,
                'metric_date' => $date,
            ],
            [
                'total_spend' => round($totalSpend, 4),
                'total_revenue' => round($totalRevenue, 4),
                'total_conversions' => round($totalConversions, 4),
                'blended_roas' => $mer,
                'marketing_efficiency_ratio' => $mer,
                'channel_breakdown' => $breakdown,
            ]
        );
    }
}