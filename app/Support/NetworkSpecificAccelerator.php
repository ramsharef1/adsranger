<?php

namespace App\Support;

use App\Models\Client;
use App\Models\NetworkAcceleratorEvent;

class NetworkSpecificAccelerator
{
    public static function syncMetaLeadToCapi(Client $client, string $leadId, float $dealValue, string $stage = 'qualified'): NetworkAcceleratorEvent
    {
        return NetworkAcceleratorEvent::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'network' => 'meta',
            'feature_key' => 'meta_lead_capi_sync',
            'external_ref' => $leadId,
            'status' => 'capi_dispatched',
            'attributed_value' => round($dealValue, 4),
            'score' => 95,
            'payload' => [
                'event_name' => 'LeadQualifiedOffline',
                'lead_stage' => $stage,
                'action_source' => 'system_generated',
            ],
        ]);
    }

    public static function boostTikTokSparkAd(Client $client, string $organicPostId, float $engagementRate): NetworkAcceleratorEvent
    {
        $sparkCode = 'SPARK-' . strtoupper(substr(md5($organicPostId), 0, 8));

        return NetworkAcceleratorEvent::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'network' => 'tiktok',
            'feature_key' => 'tiktok_spark_booster',
            'external_ref' => $organicPostId,
            'status' => $engagementRate >= 5.0 ? 'syndicated_to_paid' : 'queued',
            'attributed_value' => 0,
            'score' => (int) min(100, round($engagementRate * 10)),
            'payload' => [
                'spark_auth_code' => $sparkCode,
                'engagement_rate' => $engagementRate,
            ],
        ]);
    }

    public static function trackSnapchatArLensRoi(Client $client, string $lensId, int $swipes, float $checkoutRevenue): NetworkAcceleratorEvent
    {
        return NetworkAcceleratorEvent::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'network' => 'snapchat',
            'feature_key' => 'snap_ar_lens_roi',
            'external_ref' => $lensId,
            'status' => 'attributed',
            'attributed_value' => round($checkoutRevenue, 4),
            'score' => min(100, (int) round($swipes / 10)),
            'payload' => [
                'swipe_ups' => $swipes,
                'revenue_per_swipe' => $swipes > 0 ? round($checkoutRevenue / $swipes, 2) : 0.0,
            ],
        ]);
    }

    public static function scoreLinkedInB2bLead(Client $client, string $companyName, int $employeeCount, string $industry): NetworkAcceleratorEvent
    {
        $score = 50;
        if ($employeeCount >= 200) {
            $score += 30;
        }
        if (in_array(strtolower($industry), ['fintech', 'saas', 'enterprise_it', 'healthcare'], true)) {
            $score += 20;
        }

        return NetworkAcceleratorEvent::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'network' => 'linkedin',
            'feature_key' => 'linkedin_abm_scoring',
            'external_ref' => $companyName,
            'status' => $score >= 80 ? 'hot_enterprise_account' : 'nurture',
            'attributed_value' => 0,
            'score' => min(100, $score),
            'payload' => [
                'company' => $companyName,
                'employees' => $employeeCount,
                'industry' => $industry,
            ],
        ]);
    }
}