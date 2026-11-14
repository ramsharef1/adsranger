<?php

namespace App\Support\Attribution;

use App\Models\Client;
use App\Models\ClientSearchTermRow;
use App\Support\Arabic\Normaliser;
use App\Support\Seo\SafeFetcher;
use App\Support\TextClean;
use Illuminate\Support\Carbon;

/**
 * E2-071: 1/2/3-word n-gram stemmer powered by App\Support\Arabic\Normaliser,
 * exposing zero-conversion wasted spend stems + negative keyword list export.
 */
class SearchTermNgramMiner
{
    public static function importCsv(
        Client $client,
        string $csvContent,
        string $defaultNetwork = 'google_ads',
        ?string $defaultDate = null,
    ): int {
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

        $idxTerm = $col(['search_term', 'search term', 'query', 'عبارة البحث']);
        $idxCampaign = $col(['campaign_name', 'campaign', 'الحملة']);
        $idxNetwork = $col(['network', 'platform', 'المنصة']);
        $idxImp = $col(['impressions', 'مرات الظهور']);
        $idxClicks = $col(['clicks', 'النقرات']);
        $idxSpend = $col(['spend', 'cost', 'الإنفاق', 'التكلفة']);
        $idxConv = $col(['conversions', 'orders', 'purchases', 'التحويلات']);
        $idxRev = $col(['revenue', 'conversion_value', 'الإيرادات']);
        $idxDate = $col(['stat_date', 'date', 'التاريخ']);

        if ($idxTerm === null) {
            return 0;
        }

        $fallbackDate = $defaultDate ?? now()->toDateString();
        $saved = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line);
            $term = mb_substr(trim((string) ($cells[$idxTerm] ?? '')), 0, 300);
            if ($term === '') {
                continue;
            }

            $network = $idxNetwork !== null && trim((string) ($cells[$idxNetwork] ?? '')) !== ''
                ? strtolower(mb_substr(trim((string) $cells[$idxNetwork]), 0, 32))
                : $defaultNetwork;
            $campaign = $idxCampaign !== null ? mb_substr(trim((string) ($cells[$idxCampaign] ?? '')), 0, 255) : '';
            $imp = $idxImp !== null ? self::numInt((string) ($cells[$idxImp] ?? '0')) : 0;
            $clicks = $idxClicks !== null ? self::numInt((string) ($cells[$idxClicks] ?? '0')) : 0;
            $spend = $idxSpend !== null ? self::numFloat((string) ($cells[$idxSpend] ?? '0')) : 0.0;
            $conv = $idxConv !== null ? self::numFloat((string) ($cells[$idxConv] ?? '0')) : 0.0;
            $rev = $idxRev !== null ? self::numFloat((string) ($cells[$idxRev] ?? '0')) : 0.0;
            $dateRaw = $idxDate !== null ? trim((string) TextClean::western((string) ($cells[$idxDate] ?? ''))) : '';
            $statDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw) ? $dateRaw : $fallbackDate;

            ClientSearchTermRow::create([
                'agency_id' => $client->agency_id,
                'client_id' => $client->id,
                'network' => $network,
                'campaign_name' => $campaign,
                'search_term' => $term,
                'impressions' => $imp,
                'clicks' => $clicks,
                'spend' => round(max(0.0, $spend), 3),
                'conversions' => round(max(0.0, $conv), 2),
                'revenue' => round(max(0.0, $rev), 3),
                'stat_date' => $statDate,
            ]);
            $saved++;
        }

        return $saved;
    }

    /**
     * Tokenizes search term into normalized Arabic/Latin word stems using Normaliser::key().
     *
     * @return list<string>
     */
    public static function tokenizeStems(string $searchTerm): array
    {
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $searchTerm) ?? $searchTerm;
        $norm = Normaliser::key($cleaned);
        if ($norm === '') {
            return [];
        }

        $stems = [];
        foreach (preg_split('/\s+/u', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (mb_strlen($word) >= 5 && str_starts_with($word, 'ال') && preg_match('/^\p{Arabic}+$/u', $word)) {
                $word = mb_substr($word, 2);
            }
            if (mb_strlen($word) >= 2) {
                $stems[] = $word;
            }
        }

        return $stems;
    }

    /**
     * Extracts 1-word, 2-word, and 3-word stems with zero conversions and wasted spend >= $minSpend.
     *
     * @return list<array{ngram: string, words: int, term_count: int, impressions: int, clicks: int, spend: float, conversions: float, sample_terms: list<string>}>
     */
    public static function mineBleed(Client $client, int $days = 30, float $minSpend = 5.0): array
    {
        $latest = ClientSearchTermRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->max('stat_date');

        if (! $latest) {
            return [];
        }

        $from = Carbon::parse((string) $latest)->subDays(max(1, $days) - 1)->toDateString();
        $rows = ClientSearchTermRow::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('stat_date', '>=', $from)
            ->get();

        $buckets = [];
        foreach ($rows as $row) {
            $tokens = self::tokenizeStems($row->search_term);
            $len = count($tokens);
            if ($len === 0) {
                continue;
            }

            $seenInRow = [];
            for ($n = 1; $n <= 3; $n++) {
                if ($len < $n) {
                    continue;
                }
                for ($i = 0; $i <= $len - $n; $i++) {
                    $gram = implode(' ', array_slice($tokens, $i, $n));
                    $key = $n.'|'.$gram;
                    if (isset($seenInRow[$key])) {
                        continue;
                    }
                    $seenInRow[$key] = true;

                    $b = $buckets[$key] ?? [
                        'ngram' => $gram,
                        'words' => $n,
                        'term_count' => 0,
                        'impressions' => 0,
                        'clicks' => 0,
                        'spend' => 0.0,
                        'conversions' => 0.0,
                        'samples' => [],
                    ];
                    $b['term_count']++;
                    $b['impressions'] += max(0, (int) $row->impressions);
                    $b['clicks'] += max(0, (int) $row->clicks);
                    $b['spend'] += max(0.0, (float) $row->spend);
                    $b['conversions'] += max(0.0, (float) $row->conversions);
                    if (count($b['samples']) < 4) {
                        $b['samples'][$row->search_term] = true;
                    }
                    $buckets[$key] = $b;
                }
            }
        }

        $bleed = [];
        foreach ($buckets as $b) {
            if ($b['conversions'] > 0.0 || $b['spend'] < $minSpend) {
                continue;
            }
            $bleed[] = [
                'ngram' => $b['ngram'],
                'words' => $b['words'],
                'term_count' => $b['term_count'],
                'impressions' => $b['impressions'],
                'clicks' => $b['clicks'],
                'spend' => round($b['spend'], 3),
                'conversions' => 0.0,
                'sample_terms' => array_keys($b['samples']),
            ];
        }

        usort($bleed, fn (array $a, array $b): int => $b['spend'] <=> $a['spend'] ?: ($b['clicks'] <=> $a['clicks']));

        return array_slice($bleed, 0, 50);
    }

    /**
     * Formats wasted n-gram stems as negative keywords (phrase, exact, or broad).
     *
     * @param  list<array{ngram: string}>  $bleedStems
     */
    public static function exportNegativesTxt(array $bleedStems, string $matchType = 'phrase'): string
    {
        $lines = [];
        foreach ($bleedStems as $row) {
            $gram = trim((string) ($row['ngram'] ?? ''));
            if ($gram === '') {
                continue;
            }
            $lines[] = match ($matchType) {
                'exact' => '['.$gram.']',
                'broad' => $gram,
                default => '"'.$gram.'"',
            };
        }

        return implode("\n", array_values(array_unique($lines))).($lines !== [] ? "\n" : '');
    }

    private static function numInt(string $raw): int
    {
        return max(0, (int) round((float) str_replace([',', ' '], '', (string) TextClean::western($raw))));
    }

    private static function numFloat(string $raw): float
    {
        return (float) str_replace([',', ' '], '', (string) TextClean::western($raw));
    }
}
