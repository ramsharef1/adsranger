<?php

namespace App\Support\Reach;

use App\Models\ReachCouponCode;
use App\Models\ReachOffer;
use App\Support\Arabic\Normaliser;
use App\Support\TextClean;

/**
 * FR-REACH R-1: bulk CSV import of offers and coupons. Headers may be English or Arabic (config reach.csv_headers);
 * Eastern digits and Arabic separators become Western (TextClean::western). A row with a known (network, external_id)
 * updates that offer. Bad rows are skipped and reported by line; nothing is half-written.
 */
class OfferImporter
{
    /** @return array{created: int, updated: int, codes: int, errors: array<int, string>} */
    public static function importCsv(string $csv): array
    {
        $csv = (string) preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = preg_split('/\R/u', trim($csv)) ?: [];
        $result = ['created' => 0, 'updated' => 0, 'codes' => 0, 'errors' => []];
        if (count($lines) < 2) {
            $result['errors'][1] = 'no_rows';

            return $result;
        }
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $map = static::headerMap(str_getcsv($lines[0], $delimiter, '"', ''));
        if (! isset($map['title'], $map['payout_amount'])) {
            $result['errors'][1] = 'missing_headers';

            return $result;
        }
        foreach (array_slice($lines, 1) as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '');
            $row = [];
            foreach ($map as $field => $idx) {
                $row[$field] = $cells[$idx] ?? null;
            }
            $saved = static::save($row);
            if (is_string($saved)) {
                $result['errors'][$i + 2] = $saved;

                continue;
            }
            $result[$saved['created'] ? 'created' : 'updated']++;
            $result['codes'] += $saved['codes'];
        }

        return $result;
    }

    /** @param  list<string|null>  $headers  @return array<string, int> field => column index */
    public static function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $idx => $h) {
            $h = mb_strtolower(trim((string) TextClean::invisible((string) $h)));
            foreach (config('reach.csv_headers') as $field => $aliases) {
                if (! isset($map[$field]) && in_array($h, array_map('mb_strtolower', $aliases), true)) {
                    $map[$field] = $idx;
                }
            }
        }

        return $map;
    }

    /**
     * Manual entry and CSV rows share this path.
     *
     * @param  array<string, mixed>  $row
     * @return array{created: bool, codes: int, offer: ReachOffer}|string offer or an error key
     */
    public static function save(array $row): array|string
    {
        $clean = fn ($v) => $v === null ? null : trim((string) TextClean::western(TextClean::invisible((string) $v)));
        $row = array_map($clean, $row);

        $title = $row['title'] ?? '';
        if ($title === '' || mb_strlen($title) > 190) {
            return 'bad_title';
        }
        $payout = static::number($row['payout_amount'] ?? null);
        if ($payout === null || $payout <= 0) {
            return 'bad_payout';
        }
        $publisherPayout = static::number($row['publisher_payout_amount'] ?? null) ?? Reach::amount($payout * (float) config('reach.publisher_share'));
        if ($publisherPayout < 0 || $publisherPayout > $payout) {
            return 'bad_publisher_payout';
        }
        $network = strtolower($row['network'] ?? '') ?: 'direct_client';
        if (! in_array($network, config('reach.networks'), true)) {
            return 'bad_network';
        }
        $type = strtolower($row['offer_type'] ?? '') ?: 'cps';
        if (! in_array($type, config('reach.offer_types'), true)) {
            return 'bad_type';
        }
        $url = $row['landing_url'] ?? null;
        if ($url !== null && $url !== '' && ! preg_match('#^https://[^\s]+$#i', $url)) {
            return 'bad_url';
        }
        $currency = strtoupper($row['currency'] ?? '') ?: 'USD';
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            return 'bad_currency';
        }

        $codes = array_values(array_filter(array_map('trim', preg_split('/[|\s]+/', (string) ($row['coupon_code'] ?? '')) ?: [])));
        $attrs = [
            'title' => $title,
            'payout_amount' => Reach::amount($payout),
            'publisher_payout_amount' => Reach::amount($publisherPayout),
            'currency' => $currency,
            'offer_type' => $type,
            'allow_incent' => in_array(mb_strtolower((string) ($row['allow_incent'] ?? '')), ['1', 'yes', 'true', 'y', 'نعم'], true),
            'coupon_code' => $codes[0] ?? null,
            'landing_url' => $url ?: null,
            'category' => ($row['category'] ?? null) ? mb_strtolower(mb_substr($row['category'], 0, 32)) : null,
            'countries' => static::countries($row['countries'] ?? null),
            'keywords' => static::keywords($row['keywords'] ?? null),
        ];
        $externalId = ($row['external_id'] ?? '') !== '' ? mb_substr($row['external_id'], 0, 64) : null;

        $offer = $externalId !== null
            ? ReachOffer::updateOrCreate(['network' => $network, 'external_id' => $externalId], $attrs)
            : ReachOffer::create($attrs + ['network' => $network]);

        $added = 0;
        if (count($codes) > 1) {
            // Several codes in one cell = a rotating pool (tier 3), capped at reach.attribution.pool_max.
            foreach (array_slice($codes, 0, (int) config('reach.attribution.pool_max')) as $code) {
                $added += ReachCouponCode::firstOrCreate(['offer_id' => $offer->id, 'code' => mb_substr($code, 0, 40)], ['tier' => 'pool'])->wasRecentlyCreated ? 1 : 0;
            }
        }

        return ['created' => $offer->wasRecentlyCreated, 'codes' => $added, 'offer' => $offer];
    }

    public static function number(mixed $v): ?float
    {
        $v = trim((string) TextClean::western((string) $v));
        $v = preg_replace('/[^\d.,\-]/', '', $v);
        if ($v === '' || $v === null) {
            return null;
        }
        // "1,234.5" → 1234.5 ; "12,5" (comma as decimal) → 12.5
        $v = str_contains($v, '.') ? str_replace(',', '', $v) : str_replace(',', '.', $v);

        return is_numeric($v) ? (float) $v : null;
    }

    /** @return list<string>|null */
    private static function countries(?string $v): ?array
    {
        $list = array_values(array_filter(array_map(fn ($c) => strtoupper(trim($c)), preg_split('/[|,\s]+/', (string) $v) ?: []), fn ($c) => preg_match('/^[A-Z]{2}$/', $c)));

        return $list ?: null;
    }

    /** @return list<string>|null normalised keys (Arabic letter variants folded) */
    private static function keywords(?string $v): ?array
    {
        $list = array_values(array_unique(array_filter(array_map(fn ($k) => Normaliser::key(trim($k)), preg_split('/[|,،]+/u', (string) $v) ?: []))));

        return $list ?: null;
    }
}
