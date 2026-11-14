<?php

namespace App\Support\Seo;

use App\Models\Client;
use App\Models\ClientSearchConsoleRow;
use App\Models\SeoRankSnapshot;
use App\Models\SeoTrackedKeyword;
use App\Support\Arabic\Normaliser;
use App\Support\TextClean;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * E2-070: Tracked keyword rankings across GSC / SERP provider / manual snapshots,
 * plus deterministic Keyword Cannibalization Detector when multiple URLs split impressions or swap ranks.
 */
class SeoRankTracker
{
    public static function trackKeyword(
        Client $client,
        string $keyword,
        string $locale = 'ar',
        string $locationCode = 'SA',
        string $device = 'mobile',
        ?string $targetUrl = null,
    ): SeoTrackedKeyword {
        $cleanKw = mb_substr(trim((string) TextClean::invisible($keyword)), 0, 255);
        $cleanLocale = strtolower(mb_substr(trim($locale), 0, 12)) ?: 'ar';
        $cleanLoc = strtoupper(mb_substr(trim($locationCode), 0, 32)) ?: 'SA';
        $cleanDev = strtolower(mb_substr(trim($device), 0, 24)) ?: 'mobile';

        return SeoTrackedKeyword::updateOrCreate(
            [
                'agency_id' => $client->agency_id,
                'client_id' => $client->id,
                'keyword' => $cleanKw,
                'locale' => $cleanLocale,
                'location_code' => $cleanLoc,
                'device' => $cleanDev,
            ],
            [
                'target_url' => $targetUrl ? mb_substr(trim($targetUrl), 0, 500) : null,
                'is_active' => true,
            ]
        );
    }

    /**
     * @param  list<string>  $serpFeatures
     */
    public static function recordSnapshot(
        SeoTrackedKeyword $tracked,
        ?float $position,
        ?string $rankingUrl,
        string $source = 'manual',
        array $serpFeatures = [],
        ?Carbon $checkedAt = null,
    ): SeoRankSnapshot {
        $src = in_array($source, SeoRankSnapshot::SOURCES, true) ? $source : 'manual';

        return SeoRankSnapshot::create([
            'agency_id' => $tracked->agency_id,
            'client_id' => $tracked->client_id,
            'tracked_keyword_id' => $tracked->id,
            'keyword' => $tracked->keyword,
            'locale' => $tracked->locale,
            'location_code' => $tracked->location_code,
            'device' => $tracked->device,
            'position' => $position !== null ? round(max(1.0, $position), 2) : null,
            'ranking_url' => $rankingUrl ? mb_substr(trim($rankingUrl), 0, 500) : null,
            'serp_features' => array_values($serpFeatures),
            'source' => $src,
            'checked_at' => $checkedAt ?? now(),
        ]);
    }

    /**
     * Contract-tested SERP provider check: never fakes success when unconfigured.
     *
     * @return array{ok: bool, status: string, snapshot_id?: int}
     */
    public static function checkViaSerpProvider(
        SeoTrackedKeyword $tracked,
        string $clientDomain,
        ?string $endpointUrl,
        ?string $apiKey,
    ): array {
        $endpoint = trim((string) $endpointUrl);
        $key = trim((string) $apiKey);
        if ($endpoint === '' || $key === '') {
            return ['ok' => false, 'status' => 'unconfigured'];
        }

        try {
            $res = Http::withHeaders(['Authorization' => 'Bearer '.$key])
                ->acceptJson()
                ->timeout(15)
                ->post($endpoint, [
                    'q' => $tracked->keyword,
                    'hl' => $tracked->locale,
                    'gl' => strtolower($tracked->location_code),
                    'device' => $tracked->device,
                ]);
        } catch (Throwable) {
            return ['ok' => false, 'status' => 'failed'];
        }

        if (! $res->successful()) {
            return ['ok' => false, 'status' => 'failed'];
        }

        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'status' => 'failed'];
        }

        $organic = (array) ($data['organic'] ?? []);
        $features = array_values(array_map('strval', (array) ($data['serp_features'] ?? [])));
        $needle = strtolower(preg_replace('#^https?://(?:www\.)?#i', '', trim($clientDomain)) ?? '');
        $needle = rtrim(explode('/', $needle)[0] ?? '', '/');

        $matchedPos = null;
        $matchedUrl = null;
        foreach ($organic as $idx => $item) {
            if (! is_array($item)) {
                continue;
            }
            $link = (string) ($item['link'] ?? $item['url'] ?? '');
            $host = strtolower((string) parse_url($link, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host) ?? $host;
            if ($needle !== '' && ($host === $needle || str_ends_with($host, '.'.$needle))) {
                $matchedPos = (float) ($item['position'] ?? ($idx + 1));
                $matchedUrl = $link;
                break;
            }
        }

        $snap = self::recordSnapshot($tracked, $matchedPos, $matchedUrl, 'serp_provider', $features);

        return ['ok' => true, 'status' => 'checked', 'snapshot_id' => $snap->id];
    }

    /**
     * Pulls latest GSC averages for tracked keywords and records 'gsc' rank snapshots.
     */
    public static function syncSnapshotsFromGsc(Client $client, int $days = 7): int
    {
        $trackedList = SeoTrackedKeyword::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->where('is_active', true)
            ->get();

        if ($trackedList->isEmpty()) {
            return 0;
        }

        $latest = ClientSearchConsoleRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->max('stat_date');

        if (! $latest) {
            return 0;
        }

        $from = Carbon::parse((string) $latest)->subDays(max(1, $days) - 1)->toDateString();
        $gscRows = ClientSearchConsoleRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('stat_date', '>=', $from)
            ->get();

        $byNorm = [];
        foreach ($gscRows as $r) {
            $norm = Normaliser::key($r->query);
            $imp = max(1, (int) $r->impressions);
            $entry = $byNorm[$norm][$r->page_url] ?? ['imp' => 0, 'wpos' => 0.0];
            $entry['imp'] += $imp;
            $entry['wpos'] += ((float) $r->position) * $imp;
            $byNorm[$norm][$r->page_url] = $entry;
        }

        $recorded = 0;
        foreach ($trackedList as $kw) {
            $norm = Normaliser::key($kw->keyword);
            if (empty($byNorm[$norm])) {
                continue;
            }
            $pages = $byNorm[$norm];
            uasort($pages, fn (array $a, array $b): int => $b['imp'] <=> $a['imp']);
            $topUrl = (string) array_key_first($pages);
            $top = $pages[$topUrl];
            $avgPos = round($top['wpos'] / max(1, $top['imp']), 2);
            self::recordSnapshot($kw, $avgPos, $topUrl, 'gsc');
            $recorded++;
        }

        return $recorded;
    }

    /**
     * Deterministic Keyword Cannibalization Detector:
     * 1) GSC impression split: >=2 URLs ranking for the same normalized query where secondary URLs take >=15% impressions.
     * 2) Rank snapshot URL swaps: >=2 distinct ranking_urls observed across snapshots for the same tracked keyword.
     *
     * @return list<array{query: string, reason: string, primary_url: string, competing_urls: list<array{url: string, share_pct: float, avg_position: float}>, url_swaps: int}>
     */
    public static function detectCannibalization(Client $client, int $days = 28): array
    {
        $issues = [];

        $latest = ClientSearchConsoleRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->max('stat_date');

        if ($latest) {
            $from = Carbon::parse((string) $latest)->subDays(max(1, $days) - 1)->toDateString();
            $rows = ClientSearchConsoleRow::where('agency_id', $client->agency_id)
                ->where('client_id', $client->id)
                ->whereDate('stat_date', '>=', $from)
                ->get();

            $byQuery = [];
            foreach ($rows as $row) {
                $norm = Normaliser::key($row->query);
                $byQuery[$norm] ??= ['display' => $row->query, 'pages' => []];
                $p = $byQuery[$norm]['pages'][$row->page_url] ?? ['imp' => 0, 'wpos' => 0.0];
                $imp = max(1, (int) $row->impressions);
                $p['imp'] += $imp;
                $p['wpos'] += ((float) $row->position) * $imp;
                $byQuery[$norm]['pages'][$row->page_url] = $p;
            }

            foreach ($byQuery as $norm => $group) {
                if (count($group['pages']) < 2) {
                    continue;
                }
                $totalImp = array_sum(array_column($group['pages'], 'imp'));
                if ($totalImp < 10) {
                    continue;
                }
                uasort($group['pages'], fn (array $a, array $b): int => $b['imp'] <=> $a['imp']);
                $urls = array_keys($group['pages']);
                $primaryUrl = (string) $urls[0];
                $primaryShare = ($group['pages'][$primaryUrl]['imp'] / $totalImp) * 100.0;
                if ($primaryShare > 85.0) {
                    continue;
                }
                $competing = [];
                foreach ($group['pages'] as $u => $stat) {
                    $competing[] = [
                        'url' => (string) $u,
                        'share_pct' => round(($stat['imp'] / $totalImp) * 100, 1),
                        'avg_position' => round($stat['wpos'] / max(1, $stat['imp']), 2),
                    ];
                }
                $issues[$norm] = [
                    'query' => $group['display'],
                    'reason' => 'impression_split',
                    'primary_url' => $primaryUrl,
                    'competing_urls' => $competing,
                    'url_swaps' => 0,
                ];
            }
        }

        $snapshots = SeoRankSnapshot::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereNotNull('ranking_url')
            ->orderBy('checked_at')
            ->get();

        foreach ($snapshots->groupBy(fn (SeoRankSnapshot $s): string => Normaliser::key($s->keyword).'|'.$s->locale.'|'.$s->device) as $group) {
            $swaps = 0;
            $prevUrl = null;
            $urlCounts = [];
            foreach ($group as $snap) {
                $u = (string) $snap->ranking_url;
                if ($u === '') {
                    continue;
                }
                $urlCounts[$u] = ($urlCounts[$u] ?? 0) + 1;
                if ($prevUrl !== null && $prevUrl !== $u) {
                    $swaps++;
                }
                $prevUrl = $u;
            }
            if ($swaps >= 1 && count($urlCounts) >= 2) {
                arsort($urlCounts);
                $primaryUrl = (string) array_key_first($urlCounts);
                $first = $group->first();
                $norm = Normaliser::key($first->keyword);
                if (isset($issues[$norm])) {
                    $issues[$norm]['url_swaps'] = max($issues[$norm]['url_swaps'], $swaps);
                    $issues[$norm]['reason'] = 'impression_split_and_url_swap';
                } else {
                    $totalObs = array_sum($urlCounts);
                    $competing = [];
                    foreach ($urlCounts as $u => $cnt) {
                        $competing[] = [
                            'url' => (string) $u,
                            'share_pct' => round(($cnt / max(1, $totalObs)) * 100, 1),
                            'avg_position' => round((float) $group->where('ranking_url', $u)->avg('position'), 2),
                        ];
                    }
                    $issues[$norm] = [
                        'query' => $first->keyword,
                        'reason' => 'url_swap',
                        'primary_url' => $primaryUrl,
                        'competing_urls' => $competing,
                        'url_swaps' => $swaps,
                    ];
                }
            }
        }

        return array_values($issues);
    }
}
