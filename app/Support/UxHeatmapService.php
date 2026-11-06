<?php

namespace App\Support;

use App\Models\Client;
use App\Models\UxFunnelHeatmap;

class UxHeatmapService
{
    public static function analyzeLandingFunnel(
        Client $client,
        string $url,
        int $sessions,
        float $avgScroll,
        array $stepDropOffs
    ): UxFunnelHeatmap {
        arsort($stepDropOffs);
        $worstStep = (string) (array_key_first($stepDropOffs) ?? 'form_phone_input');
        $maxDrop = (float) ($stepDropOffs[$worstStep] ?? 0.0);

        return UxFunnelHeatmap::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'landing_page_url' => trim($url),
            'sessions_analyzed' => $sessions,
            'avg_scroll_depth' => round($avgScroll, 2),
            'drop_off_rate' => round($maxDrop, 2),
            'primary_leak_step' => $worstStep,
            'friction_Zones' => [
                'step_drop_offs' => $stepDropOffs,
                'recommendation' => $maxDrop >= 40.0 ? 'simplify_checkout_field' : 'healthy_flow',
            ],
        ]);
    }
}