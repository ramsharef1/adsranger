<?php

namespace App\Support;

use App\Models\BudgetReallocationLog;
use App\Models\Client;

class BudgetReallocationEngine
{
    public static function evaluateAndReallocate(
        Client $client,
        array $campaigns,
        float $targetCpa,
        float $monthlyCap,
        float $currentMonthSpend
    ): ?BudgetReallocationLog {
        $winners = [];
        $losers = [];

        foreach ($campaigns as $c) {
            $cpa = (float) ($c['cpa'] ?? 0);
            if ($cpa > 0 && $cpa <= ($targetCpa * 0.8)) {
                $winners[] = $c;
            } elseif ($cpa >= ($targetCpa * 1.2)) {
                $losers[] = $c;
            }
        }

        if (empty($winners) || empty($losers)) {
            return null;
        }

        usort($winners, fn ($a, $b) => $a['cpa'] <=> $b['cpa']);
        usort($losers, fn ($a, $b) => $b['cpa'] <=> $a['cpa']);

        $donor = $losers[0];
        $recipient = $winners[0];

        $rawShift = round(((float) $donor['daily_budget']) * 0.20, 2);
        $remainingCap = max(0.0, $monthlyCap - $currentMonthSpend);
        $safeShift = min($rawShift, $remainingCap);

        $status = $safeShift > 0 ? 'applied' : 'capped_hold';

        return BudgetReallocationLog::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'source_campaign' => (string) $donor['name'],
            'target_campaign' => (string) $recipient['name'],
            'shifted_amount' => $safeShift,
            'monthly_cap' => $monthlyCap,
            'projected_spend' => round($currentMonthSpend + $safeShift, 2),
            'status' => $status,
            'decision_meta' => [
                'target_cpa' => $targetCpa,
                'donor_cpa' => $donor['cpa'],
                'recipient_cpa' => $recipient['cpa'],
            ],
        ]);
    }
}