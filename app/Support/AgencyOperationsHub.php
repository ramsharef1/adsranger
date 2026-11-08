<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AgencyOperationalAudit;
use App\Models\Client;

class AgencyOperationsHub
{
    public static function calculateBudgetRunway(Client $client, float $remainingBalance, float $dailyBurnRate): AgencyOperationalAudit
    {
        $daysLeft = $dailyBurnRate > 0 ? round($remainingBalance / $dailyBurnRate, 1) : 999.0;
        $status = $daysLeft <= 3.0 ? 'critical_depletion' : ($daysLeft <= 7.0 ? 'warning_low' : 'healthy');

        return AgencyOperationalAudit::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'audit_type' => 'budget_runway',
            'status' => $status,
            'primary_metric' => $daysLeft,
            'details' => [
                'remaining_balance' => $remainingBalance,
                'daily_burn_rate' => $dailyBurnRate,
                'days_remaining' => $daysLeft,
            ],
        ]);
    }

    public static function auditRetainerMargin(Client $client, float $monthlyRetainer, float $adSpendManaged, float $teamHoursSpent, float $hourlyCost = 35.0): AgencyOperationalAudit
    {
        $deliveryCost = round($teamHoursSpent * $hourlyCost, 2);
        $netMargin = $monthlyRetainer > 0 ? round((($monthlyRetainer - $deliveryCost) / $monthlyRetainer) * 100, 2) : 0.0;

        return AgencyOperationalAudit::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'audit_type' => 'retainer_margin',
            'status' => $netMargin >= 40.0 ? 'profitable' : 'margin_squeeze',
            'primary_metric' => $netMargin,
            'details' => [
                'monthly_retainer' => $monthlyRetainer,
                'ad_spend_managed' => $adSpendManaged,
                'delivery_cost' => $deliveryCost,
                'net_margin_percent' => $netMargin,
            ],
        ]);
    }

    public static function checkMenaFxVolatility(Client $client, string $currencyPair, float $baselineRate, float $currentRate): AgencyOperationalAudit
    {
        $driftPercent = $baselineRate > 0 ? round(abs(($currentRate - $baselineRate) / $baselineRate) * 100, 2) : 0.0;

        return AgencyOperationalAudit::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'audit_type' => 'mena_fx_volatility',
            'status' => $driftPercent >= 2.5 ? 'fx_hedge_alert' : 'stable_peg',
            'primary_metric' => $driftPercent,
            'details' => [
                'pair' => $currencyPair,
                'baseline' => $baselineRate,
                'current' => $currentRate,
                'drift_percent' => $driftPercent,
            ],
        ]);
    }

    public static function evaluateMediaBuyerWorkload(Agency $agency, string $buyerName, int $activeClients, int $activeCampaigns): AgencyOperationalAudit
    {
        $loadScore = min(100, ($activeClients * 8) + ($activeCampaigns * 2));

        return AgencyOperationalAudit::create([
            'agency_id' => $agency->id,
            'client_id' => null,
            'audit_type' => 'media_buyer_workload',
            'status' => $loadScore >= 85 ? 'overloaded' : 'balanced',
            'primary_metric' => $loadScore,
            'details' => [
                'media_buyer' => $buyerName,
                'active_clients' => $activeClients,
                'active_campaigns' => $activeCampaigns,
            ],
        ]);
    }
}
