<?php

namespace App\Support\Seo;

use App\Support\Arabic\Normaliser;

/**
 * Google Search Console Arabic query & CTR opportunity analyzer (GSC module).
 * Normalises Arabic queries, tags regional dialects, and flags high-impression / low-CTR opportunities.
 * Per D-229, returns an explicit `simulated` source flag when live OAuth rows are not provided.
 */
class SearchConsole
{
    /**
     * @param  list<array{query: string, clicks: int, impressions: int, position: float}>|null  $rows
     * @return array{source: string, totals: array{clicks: int, impressions: int, avg_ctr: float, avg_position: float}, rows: list<array{query: string, canonical: string, dialects: list<string>, clicks: int, impressions: int, ctr: float, position: float, opportunity: bool}>}
     */
    public static function analyze(?array $rows = null): array
    {
        $source = $rows === null ? 'simulated' : 'live';
        $raw = $rows ?? self::sampleRows();

        $out = [];
        $totalClicks = 0;
        $totalImpr = 0;
        $posSum = 0.0;

        foreach ($raw as $r) {
            $q = trim((string) ($r['query'] ?? ''));
            if ($q === '') {
                continue;
            }
            $clicks = max(0, (int) ($r['clicks'] ?? 0));
            $impr = max(0, (int) ($r['impressions'] ?? 0));
            $pos = round(max(1.0, (float) ($r['position'] ?? 1.0)), 1);
            $ctr = $impr > 0 ? round(($clicks / $impr) * 100, 2) : 0.0;

            $totalClicks += $clicks;
            $totalImpr += $impr;
            $posSum += $pos;

            $out[] = [
                'query' => $q,
                'canonical' => Normaliser::key($q),
                'dialects' => Normaliser::dialectTags($q),
                'clicks' => $clicks,
                'impressions' => $impr,
                'ctr' => $ctr,
                'position' => $pos,
                'opportunity' => $impr >= 500 && $pos <= 10.0 && $ctr < 3.5,
            ];
        }

        $count = count($out);

        return [
            'source' => $source,
            'totals' => [
                'clicks' => $totalClicks,
                'impressions' => $totalImpr,
                'avg_ctr' => $totalImpr > 0 ? round(($totalClicks / $totalImpr) * 100, 2) : 0.0,
                'avg_position' => $count > 0 ? round($posSum / $count, 1) : 0.0,
            ],
            'rows' => $out,
        ];
    }

    /**
     * @return list<array{query: string, clicks: int, impressions: int, position: float}>
     */
    public static function sampleRows(): array
    {
        return [
            ['query' => 'حاسبة ميزانية إعلانات رمضان', 'clicks' => 142, 'impressions' => 1850, 'position' => 3.2],
            ['query' => 'افضل شركة اعلانات ممولة في الرياض', 'clicks' => 28, 'impressions' => 1420, 'position' => 5.4],
            ['query' => 'فحص السيو العربي للموقع', 'clicks' => 64, 'impressions' => 910, 'position' => 4.1],
            ['query' => 'ابغى وكالة تسويق رقمي جدة', 'clicks' => 19, 'impressions' => 840, 'position' => 6.8],
            ['query' => 'معاينة نتائج البحث جوجل', 'clicks' => 51, 'impressions' => 620, 'position' => 2.9],
        ];
    }
}
