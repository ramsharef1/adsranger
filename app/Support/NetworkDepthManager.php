<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ConversionHealthCheck;
use App\Models\CustomAudienceSync;

class NetworkDepthManager
{
    public static function syncAudienceList(
        Client $client,
        string $name,
        string $sourceType,
        array $targetNetworks,
        int $recordsCount
    ): CustomAudienceSync {
        $estimatedMatchRate = match ($sourceType) {
            'purchasers', 'high_ltv' => 78.50,
            'crm_leads' => 68.00,
            default => 54.00,
        };

        $sync = CustomAudienceSync::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'name' => $name,
            'source_type' => $sourceType,
            'target_networks' => array_values($targetNetworks),
            'records_count' => $recordsCount,
            'match_rate' => $estimatedMatchRate,
            'status' => 'simulated', // D-229: nothing is sent to any network yet
            'last_synced_at' => null,
        ]);

        AuditLog::record('network.audience_simulated', $client, [
            'sync_id' => $sync->id,
            'networks' => $targetNetworks,
            'records_count' => $recordsCount,
        ]);

        return $sync;
    }

    public static function evaluateConversionHealth(
        Client $client,
        string $network,
        string $eventName,
        bool $pixelActive,
        bool $capiActive,
        float $emqScore,
        float $dedupRate
    ): ConversionHealthCheck {
        $status = 'healthy';
        if (! $pixelActive && ! $capiActive) {
            $status = 'critical';
        } elseif (! $capiActive || $emqScore < 5.0 || $dedupRate < 70.0) {
            $status = 'warning';
        }

        return ConversionHealthCheck::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'network' => $network,
            'event_name' => $eventName,
            'pixel_active' => $pixelActive,
            'capi_active' => $capiActive,
            'event_match_quality' => $emqScore,
            'deduplication_rate' => $dedupRate,
            'status' => $status,
            'checked_at' => now(),
        ]);
    }
}
