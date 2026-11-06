<?php

namespace App\Support;

use App\Models\Client;
use App\Models\CompetitorCreativeRadar;

class CreativeTrendRadar
{
    public static function scanCompetitorAds(
        Client $client,
        string $competitorName,
        string $network,
        array $sampleAds
    ): CompetitorCreativeRadar {
        $formats = [];
        $hooks = [];
        $evergreenCount = 0;

        foreach ($sampleAds as $ad) {
            $fmt = (string) ($ad['format'] ?? 'reels_video');
            $formats[$fmt] = ($formats[$fmt] ?? 0) + 1;

            if (!empty($ad['hook'])) {
                $hooks[] = (string) $ad['hook'];
            }

            if ((int) ($ad['days_active'] ?? 0) >= 14) {
                $evergreenCount++;
            }
        }

        arsort($formats);
        $dominantFormat = (string) (array_key_first($formats) ?? 'reels_video');

        return CompetitorCreativeRadar::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'competitor_name' => trim($competitorName),
            'network' => $network,
            'dominant_format' => $dominantFormat,
            'active_ads_count' => count($sampleAds),
            'top_hooks' => array_slice($hooks, 0, 5),
            'trend_signals' => [
                'evergreen_winners' => $evergreenCount,
                'format_distribution' => $formats,
                'shift_alert' => $evergreenCount >= 2 ? 'high_retention_hook_detected' : 'normal_rotation',
            ],
        ]);
    }
}