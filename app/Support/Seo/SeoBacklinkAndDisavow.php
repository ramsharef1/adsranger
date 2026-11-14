<?php

namespace App\Support\Seo;

use App\Models\Client;
use App\Models\SeoBacklinkRecord;
use App\Models\SeoDisavowList;

/**
 * E2-070: Search Console external links CSV importer, multi-competitor referring domain intersection gap finder,
 * and valid UTF-8 Google Search Console Disavow .txt exporter.
 */
class SeoBacklinkAndDisavow
{
    public static function importGscLinksCsv(Client $client, string $csvContent, string $defaultTargetUrl = '/'): int
    {
        $utf8 = SafeFetcher::toUtf8($csvContent);
        $utf8 = preg_replace('/^\xEF\xBB\xBF/', '', $utf8) ?? $utf8;
        $lines = preg_split('/\R/u', trim($utf8)) ?: [];
        if (count($lines) < 2) {
            return 0;
        }

        $header = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            str_getcsv((string) array_shift($lines))
        );

        $col = fn (array $aliases): ?int => collect($aliases)
            ->map(fn (string $a) => array_search($a, $header, true))
            ->first(fn ($idx) => $idx !== false);

        $idxSource = $col(['source_url', 'source url', 'linking page', 'top linking sites', 'domain', 'المصدر']);
        $idxTarget = $col(['target_url', 'target url', 'target page', 'الصفحة الهدف']);
        $idxAnchor = $col(['anchor_text', 'anchor', 'نص الرابط']);
        $idxStatus = $col(['link_status', 'status', 'الحالة']);

        if ($idxSource === null) {
            return 0;
        }

        $saved = 0;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $rawSource = trim((string) ($cells[$idxSource] ?? ''));
            if ($rawSource === '') {
                continue;
            }

            $sourceUrl = preg_match('#^https?://#i', $rawSource) ? $rawSource : 'https://'.$rawSource;
            $domain = self::normaliseDomain($sourceUrl);
            if ($domain === '') {
                continue;
            }

            $targetUrl = $idxTarget !== null && trim((string) ($cells[$idxTarget] ?? '')) !== ''
                ? trim((string) $cells[$idxTarget])
                : $defaultTargetUrl;
            $anchor = $idxAnchor !== null ? mb_substr(trim((string) ($cells[$idxAnchor] ?? '')), 0, 300) : null;
            $rawStatus = $idxStatus !== null ? strtolower(trim((string) ($cells[$idxStatus] ?? 'active'))) : 'active';
            $status = in_array($rawStatus, SeoBacklinkRecord::STATUSES, true) ? $rawStatus : 'active';

            SeoBacklinkRecord::updateOrCreate(
                [
                    'agency_id' => $client->agency_id,
                    'client_id' => $client->id,
                    'source_url' => mb_substr($sourceUrl, 0, 500),
                    'target_url' => mb_substr($targetUrl, 0, 500),
                ],
                [
                    'source_domain' => mb_substr($domain, 0, 190),
                    'anchor_text' => $anchor ?: null,
                    'link_status' => $status,
                ]
            );
            $saved++;
        }

        return $saved;
    }

    /**
     * Multi-competitor referring domain intersection gap finder:
     * Returns domains linking to >= $minCompetitors competitors that do NOT actively link to $client.
     *
     * @param  array<string, list<string>>  $competitorDomainMap  competitor label/domain => list of referring URLs or domains
     * @return list<array{referring_domain: string, competitor_count: int, competitors: list<string>}>
     */
    public static function competitorDomainGap(Client $client, array $competitorDomainMap, int $minCompetitors = 2): array
    {
        $clientDomains = SeoBacklinkRecord::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->where('link_status', 'active')
            ->pluck('source_domain')
            ->map(fn (string $d): string => self::normaliseDomain($d))
            ->flip()
            ->all();

        $matrix = [];
        foreach ($competitorDomainMap as $comp => $refs) {
            $compLabel = trim((string) $comp);
            if ($compLabel === '' || ! is_array($refs)) {
                continue;
            }
            foreach ($refs as $ref) {
                $dom = self::normaliseDomain((string) $ref);
                if ($dom === '' || isset($clientDomains[$dom])) {
                    continue;
                }
                $matrix[$dom][$compLabel] = true;
            }
        }

        $gaps = [];
        foreach ($matrix as $dom => $compSet) {
            $comps = array_keys($compSet);
            if (count($comps) < max(1, $minCompetitors)) {
                continue;
            }
            $gaps[] = [
                'referring_domain' => $dom,
                'competitor_count' => count($comps),
                'competitors' => $comps,
            ];
        }

        usort($gaps, fn (array $a, array $b): int => $b['competitor_count'] <=> $a['competitor_count'] ?: strcmp($a['referring_domain'], $b['referring_domain']));

        return $gaps;
    }

    /**
     * Generates a valid UTF-8 Google Search Console Disavow .txt file from flagged backlink records.
     */
    public static function exportDisavowTxt(
        Client $client,
        string $name = 'Disavow export',
        bool $domainLevel = true,
    ): SeoDisavowList {
        $flagged = SeoBacklinkRecord::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->where('is_flagged_disavow', true)
            ->orderBy('source_domain')
            ->get();

        $entries = [];
        foreach ($flagged as $rec) {
            if ($domainLevel) {
                $dom = self::normaliseDomain($rec->source_domain ?: $rec->source_url);
                if ($dom !== '') {
                    $entries['domain:'.$dom] = true;
                }
            } elseif (preg_match('#^https?://[^\s]+$#i', $rec->source_url)) {
                $entries[$rec->source_url] = true;
            }
        }

        $entryList = array_keys($entries);
        $headerLines = [
            '# Disavow list for '.$client->name,
            '# Generated '.now()->toIso8601String(),
        ];
        $txt = implode("\n", array_merge($headerLines, $entryList))."\n";

        return SeoDisavowList::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'name' => mb_substr(trim($name) ?: 'Disavow export', 0, 190),
            'entries' => $entryList,
            'exported_txt' => $txt,
            'generated_at' => now(),
        ]);
    }

    public static function normaliseDomain(string $urlOrDomain): string
    {
        $raw = trim(strtolower($urlOrDomain));
        if ($raw === '') {
            return '';
        }
        $host = str_contains($raw, '://')
            ? (string) parse_url($raw, PHP_URL_HOST)
            : (string) explode('/', $raw)[0];
        $host = preg_replace('/^www\./', '', trim($host, '.')) ?? '';

        return preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $host) ? $host : '';
    }
}
