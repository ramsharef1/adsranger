<?php

namespace App\Support\Seo;

use App\Models\Client;
use App\Models\ClientSearchConsoleRow;
use App\Support\TextClean;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * E2-069: Google Search Console searchAnalytics/query contract, CSV importer,
 * #4-#15 striking-distance finder, and window-over-window query decay detector.
 */
class GscSearchAnalyticsSync
{
    public const ENDPOINT_TEMPLATE = 'https://searchconsole.googleapis.com/webmasters/v3/sites/%s/searchAnalytics/query';

    /**
     * @return array{ok: bool, status: string, rows_saved: int, http_status?: int}
     */
    public static function syncApi(
        Client $client,
        string $siteUrl,
        ?string $accessToken,
        string $startDate,
        string $endDate,
    ): array {
        $siteUrl = trim($siteUrl);
        $token = trim((string) $accessToken);

        if ($siteUrl === '' || $token === '') {
            return [
                'ok' => false,
                'status' => 'unconfigured',
                'rows_saved' => 0,
            ];
        }

        $url = sprintf(self::ENDPOINT_TEMPLATE, rawurlencode($siteUrl));

        try {
            $res = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post($url, [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'dimensions' => ['date', 'query', 'page', 'country', 'device'],
                    'rowLimit' => 5000,
                ]);
        } catch (Throwable) {
            return [
                'ok' => false,
                'status' => 'failed',
                'rows_saved' => 0,
            ];
        }

        if (! $res->successful()) {
            return [
                'ok' => false,
                'status' => 'failed',
                'rows_saved' => 0,
                'http_status' => $res->status(),
            ];
        }

        $payload = $res->json();
        if (! is_array($payload)) {
            return [
                'ok' => false,
                'status' => 'failed',
                'rows_saved' => 0,
            ];
        }

        $rows = $payload['rows'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return [
                'ok' => true,
                'status' => 'empty',
                'rows_saved' => 0,
            ];
        }

        $saved = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $keys = array_values((array) ($row['keys'] ?? []));
            $statDate = self::cleanDate((string) ($keys[0] ?? $endDate));
            $query = mb_substr(trim(SafeFetcher::toUtf8((string) ($keys[1] ?? ''))), 0, 300);
            $pageUrl = mb_substr(trim(SafeFetcher::toUtf8((string) ($keys[2] ?? $siteUrl))), 0, 500);
            $country = strtoupper(mb_substr(trim((string) ($keys[3] ?? '')), 0, 12));
            $device = strtoupper(mb_substr(trim((string) ($keys[4] ?? 'ALL')), 0, 24)) ?: 'ALL';

            if ($statDate === null || $query === '') {
                continue;
            }

            ClientSearchConsoleRow::updateOrCreate(
                [
                    'agency_id' => $client->agency_id,
                    'client_id' => $client->id,
                    'site_url' => mb_substr($siteUrl, 0, 255),
                    'stat_date' => $statDate,
                    'query' => $query,
                    'page_url' => $pageUrl !== '' ? $pageUrl : $siteUrl,
                    'country' => $country,
                    'device' => $device,
                ],
                [
                    'clicks' => max(0, (int) round((float) ($row['clicks'] ?? 0))),
                    'impressions' => max(0, (int) round((float) ($row['impressions'] ?? 0))),
                    'ctr' => round(max(0.0, min(1.0, (float) ($row['ctr'] ?? 0))), 4),
                    'position' => round(max(0.0, (float) ($row['position'] ?? 0)), 2),
                ]
            );
            $saved++;
        }

        return [
            'ok' => $saved > 0,
            'status' => $saved > 0 ? 'synced' : 'empty',
            'rows_saved' => $saved,
        ];
    }

    public static function importCsv(
        Client $client,
        string $siteUrl,
        string $csvContent,
        ?string $defaultDate = null,
    ): int {
        $siteUrl = trim($siteUrl) !== '' ? trim($siteUrl) : 'https://example.com';
        $utf8 = SafeFetcher::toUtf8($csvContent);
        $utf8 = preg_replace('/^\xEF\xBB\xBF/', '', $utf8) ?? $utf8;
        $lines = preg_split('/\R/u', trim($utf8)) ?: [];
        if (count($lines) < 2) {
            return 0;
        }

        $header = array_map(
            fn ($h) => strtolower(trim((string) TextClean::invisible((string) $h))),
            str_getcsv((string) array_shift($lines))
        );

        $col = fn (array $aliases): ?int => collect($aliases)
            ->map(fn (string $a) => array_search($a, $header, true))
            ->first(fn ($idx) => $idx !== false);

        $idxQuery = $col(['query', 'top queries', 'queries', 'الاستعلام', 'عبارة البحث']);
        $idxPage = $col(['page', 'top pages', 'url', 'page_url', 'الصفحة']);
        $idxClicks = $col(['clicks', 'النقرات']);
        $idxImpressions = $col(['impressions', 'مرات الظهور']);
        $idxCtr = $col(['ctr', 'نسبة النقر']);
        $idxPosition = $col(['position', 'avg position', 'الترتيب', 'متوسط الترتيب']);
        $idxDate = $col(['date', 'stat_date', 'التاريخ']);
        $idxCountry = $col(['country', 'الدولة']);
        $idxDevice = $col(['device', 'الجهاز']);

        if ($idxQuery === null) {
            return 0;
        }

        $fallbackDate = self::cleanDate($defaultDate ?? now()->toDateString()) ?? now()->toDateString();
        $saved = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $query = mb_substr(trim((string) ($cells[$idxQuery] ?? '')), 0, 300);
            if ($query === '') {
                continue;
            }

            $pageUrl = $idxPage !== null ? mb_substr(trim((string) ($cells[$idxPage] ?? '')), 0, 500) : '';
            $clicks = $idxClicks !== null ? self::parseInt((string) ($cells[$idxClicks] ?? '0')) : 0;
            $impressions = $idxImpressions !== null ? self::parseInt((string) ($cells[$idxImpressions] ?? '0')) : 0;
            $ctr = $idxCtr !== null ? self::parseCtr((string) ($cells[$idxCtr] ?? '0')) : ($impressions > 0 ? round($clicks / $impressions, 4) : 0.0);
            $position = $idxPosition !== null ? self::parseFloat((string) ($cells[$idxPosition] ?? '0')) : 0.0;
            $statDate = $idxDate !== null ? (self::cleanDate((string) ($cells[$idxDate] ?? '')) ?? $fallbackDate) : $fallbackDate;
            $country = $idxCountry !== null ? strtoupper(mb_substr(trim((string) ($cells[$idxCountry] ?? '')), 0, 12)) : '';
            $device = $idxDevice !== null ? (strtoupper(mb_substr(trim((string) ($cells[$idxDevice] ?? 'ALL')), 0, 24)) ?: 'ALL') : 'ALL';

            ClientSearchConsoleRow::updateOrCreate(
                [
                    'agency_id' => $client->agency_id,
                    'client_id' => $client->id,
                    'site_url' => mb_substr($siteUrl, 0, 255),
                    'stat_date' => $statDate,
                    'query' => $query,
                    'page_url' => $pageUrl !== '' ? $pageUrl : $siteUrl,
                    'country' => $country,
                    'device' => $device,
                ],
                [
                    'clicks' => $clicks,
                    'impressions' => $impressions,
                    'ctr' => $ctr,
                    'position' => round(max(0.0, $position), 2),
                ]
            );
            $saved++;
        }

        return $saved;
    }

    /**
     * Queries ranked #4.0 to #15.0 where small on-page improvements can lift CTR into top-3 territory.
     *
     * @return list<array{query: string, page_url: string, clicks: int, impressions: int, ctr_pct: float, position: float, estimated_extra_clicks: int}>
     */
    public static function strikingDistance(Client $client, int $days = 28, int $minImpressions = 10): array
    {
        $latest = ClientSearchConsoleRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->max('stat_date');

        if (! $latest) {
            return [];
        }

        $from = Carbon::parse((string) $latest)->subDays(max(1, $days) - 1)->toDateString();

        $rows = ClientSearchConsoleRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('stat_date', '>=', $from)
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->query.'||'.$row->page_url;
            $g = $grouped[$key] ?? [
                'query' => $row->query,
                'page_url' => $row->page_url,
                'clicks' => 0,
                'impressions' => 0,
                'weighted_pos' => 0.0,
            ];
            $imp = max(0, (int) $row->impressions);
            $g['clicks'] += max(0, (int) $row->clicks);
            $g['impressions'] += $imp;
            $g['weighted_pos'] += ((float) $row->position) * ($imp > 0 ? $imp : 1);
            $grouped[$key] = $g;
        }

        $candidates = [];
        foreach ($grouped as $g) {
            if ($g['impressions'] < $minImpressions) {
                continue;
            }
            $pos = round($g['weighted_pos'] / max(1, $g['impressions']), 2);
            if ($pos < 4.0 || $pos > 15.0) {
                continue;
            }
            $ctrPct = round(($g['clicks'] / max(1, $g['impressions'])) * 100, 2);
            $candidates[] = [
                'query' => $g['query'],
                'page_url' => $g['page_url'],
                'clicks' => $g['clicks'],
                'impressions' => $g['impressions'],
                'ctr_pct' => $ctrPct,
                'position' => $pos,
                'estimated_extra_clicks' => max(0, (int) round($g['impressions'] * 0.08 - $g['clicks'])),
            ];
        }

        usort($candidates, fn (array $a, array $b): int => $b['impressions'] <=> $a['impressions']);

        return array_slice($candidates, 0, 50);
    }

    /**
     * Detects queries losing >=25% clicks or dropping >=2.0 average positions vs the preceding equal window.
     *
     * @return list<array{query: string, page_url: string, prior_clicks: int, recent_clicks: int, clicks_lost: int, click_delta_pct: float, prior_position: float, recent_position: float, position_delta: float}>
     */
    public static function queryDecay(Client $client, int $windowDays = 14, int $minPriorClicks = 5): array
    {
        $latest = ClientSearchConsoleRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->max('stat_date');

        if (! $latest) {
            return [];
        }

        $w = max(1, $windowDays);
        $endRecent = Carbon::parse((string) $latest)->startOfDay();
        $startRecent = $endRecent->copy()->subDays($w - 1);
        $endPrior = $startRecent->copy()->subDay();
        $startPrior = $endPrior->copy()->subDays($w - 1);

        $rows = ClientSearchConsoleRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('stat_date', '>=', $startPrior->toDateString())
            ->whereDate('stat_date', '<=', $endRecent->toDateString())
            ->get();

        $buckets = [];
        foreach ($rows as $row) {
            $d = $row->stat_date instanceof Carbon ? $row->stat_date->toDateString() : (string) $row->stat_date;
            $period = $d >= $startRecent->toDateString() ? 'recent' : 'prior';
            $key = $row->query.'||'.$row->page_url;
            $buckets[$key] ??= [
                'query' => $row->query,
                'page_url' => $row->page_url,
                'prior_clicks' => 0,
                'prior_imp' => 0,
                'prior_wpos' => 0.0,
                'recent_clicks' => 0,
                'recent_imp' => 0,
                'recent_wpos' => 0.0,
            ];
            $imp = max(1, (int) $row->impressions);
            $buckets[$key]["{$period}_clicks"] += max(0, (int) $row->clicks);
            $buckets[$key]["{$period}_imp"] += $imp;
            $buckets[$key]["{$period}_wpos"] += ((float) $row->position) * $imp;
        }

        $decaying = [];
        foreach ($buckets as $b) {
            if ($b['prior_clicks'] < $minPriorClicks) {
                continue;
            }
            $priorPos = $b['prior_imp'] > 0 ? round($b['prior_wpos'] / $b['prior_imp'], 2) : 0.0;
            $recentPos = $b['recent_imp'] > 0 ? round($b['recent_wpos'] / $b['recent_imp'], 2) : ($priorPos + 10.0);
            $clickDeltaPct = round((($b['recent_clicks'] - $b['prior_clicks']) / $b['prior_clicks']) * 100, 1);
            $posDelta = round($recentPos - $priorPos, 2);

            if ($clickDeltaPct <= -25.0 || $posDelta >= 2.0) {
                $decaying[] = [
                    'query' => $b['query'],
                    'page_url' => $b['page_url'],
                    'prior_clicks' => $b['prior_clicks'],
                    'recent_clicks' => $b['recent_clicks'],
                    'clicks_lost' => max(0, $b['prior_clicks'] - $b['recent_clicks']),
                    'click_delta_pct' => $clickDeltaPct,
                    'prior_position' => $priorPos,
                    'recent_position' => $recentPos,
                    'position_delta' => $posDelta,
                ];
            }
        }

        usort($decaying, fn (array $a, array $b): int => $b['clicks_lost'] <=> $a['clicks_lost'] ?: ($b['position_delta'] <=> $a['position_delta']));

        return array_slice($decaying, 0, 50);
    }

    private static function cleanDate(string $raw): ?string
    {
        $clean = trim((string) TextClean::western($raw));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $clean)) {
            return $clean;
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $clean, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }

    private static function parseInt(string $raw): int
    {
        $clean = str_replace([',', ' '], '', (string) TextClean::western($raw));

        return max(0, (int) round((float) $clean));
    }

    private static function parseFloat(string $raw): float
    {
        $clean = str_replace([',', ' '], '', (string) TextClean::western($raw));

        return (float) $clean;
    }

    private static function parseCtr(string $raw): float
    {
        $clean = trim((string) TextClean::western($raw));
        $isPercent = str_contains($clean, '%');
        $num = (float) str_replace(['%', ',', ' '], '', $clean);
        if ($isPercent || $num > 1.0) {
            $num /= 100.0;
        }

        return round(max(0.0, min(1.0, $num)), 4);
    }
}
