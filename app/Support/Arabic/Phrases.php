<?php

namespace App\Support\Arabic;

use App\Models\Client;

/**
 * R5 (D-307): XS-16 dialect per client, XS-18 the CTA + offer phrase library (an E1 draft set until Growth approves it),
 * and XS-17 rule-based variants of an ad line (dialect word swaps, a clean spelling form, CTA swaps). No AI.
 */
class Phrases
{
    public const DIALECTS = ['gulf', 'levantine', 'egyptian', 'maghrebi', 'msa'];

    public const SEASONS = ['any', 'ramadan', 'eid', 'white_friday', 'national_day', 'back_to_school'];

    protected const BY_MARKET = [
        'SA' => 'gulf', 'AE' => 'gulf', 'KW' => 'gulf', 'QA' => 'gulf', 'BH' => 'gulf', 'OM' => 'gulf',
        'JO' => 'levantine', 'LB' => 'levantine', 'PS' => 'levantine', 'SY' => 'levantine',
        'EG' => 'egyptian', 'MA' => 'maghrebi', 'TN' => 'maghrebi', 'DZ' => 'maghrebi', 'LY' => 'maghrebi',
    ];

    private static ?array $data = null;

    /** @return array<string, mixed> */
    public static function data(): array
    {
        return static::$data ??= json_decode((string) file_get_contents(resource_path('arabic/phrases_v1.json')), true) ?: ['phrases' => [], 'swaps' => [], 'status' => 'draft'];
    }

    public static function isDraft(): bool
    {
        return (static::data()['status'] ?? 'draft') === 'draft';
    }

    /** @return array<string, string> */
    public static function dialectLabels(): array
    {
        return ['gulf' => __('Gulf'), 'levantine' => __('Levantine'), 'egyptian' => __('Egyptian'), 'maghrebi' => __('Maghrebi'), 'msa' => __('Modern Standard Arabic')];
    }

    /** @return array<string, string> */
    public static function seasonLabels(): array
    {
        return ['any' => __('Any time'), 'ramadan' => __('Ramadan'), 'eid' => __('Eid'), 'white_friday' => __('White Friday'), 'national_day' => __('National day'), 'back_to_school' => __('Back to school')];
    }

    /** The client's dialect: set on the client, else from its market (Gulf, Levant, Egypt, Maghreb), else MSA. */
    public static function dialectOf(?Client $c): string
    {
        if ($c && in_array($c->dialect, self::DIALECTS, true)) {
            return $c->dialect;
        }

        return self::BY_MARKET[strtoupper((string) ($c?->market ?: $c?->agency?->country))] ?? 'msa';
    }

    /**
     * @return list<array{id: int, kind: string, sector: string, season: string, dialect: string, text: string}>
     */
    public static function find(?string $dialect = null, ?string $sector = null, ?string $season = null, ?string $kind = null): array
    {
        return array_values(array_filter(static::data()['phrases'] ?? [], fn ($p) => (! $dialect || $p['dialect'] === $dialect)
            && (! $sector || in_array($p['sector'], [$sector, 'general'], true))
            && (! $season || $season === 'any' || in_array($p['season'], [$season, 'any'], true))
            && (! $kind || $p['kind'] === $kind)));
    }

    /** @return list<array{type: string, text: string}> */
    public static function variants(string $line, string $dialect, ?string $sector = null): array
    {
        $line = trim((string) preg_replace('/\s+/u', ' ', $line));
        if ($line === '' || ! in_array($dialect, self::DIALECTS, true)) {
            return [];
        }
        $out = [];
        $add = function (string $type, string $text) use (&$out, $line): void {
            $text = trim($text);
            if ($text !== '' && $text !== $line && ! in_array($text, array_column($out, 'text'), true)) {
                $out[] = ['type' => $type, 'text' => $text];
            }
        };

        // 1. Dialect word swaps: MSA → the dialect, or any dialect → MSA.
        $swaps = static::data()['swaps'] ?? [];
        $map = [];
        if ($dialect === 'msa') {
            foreach ($swaps as $pairs) {
                foreach ($pairs as $msa => $dial) {
                    $map[$dial] = $msa;
                }
            }
        } else {
            $map = $swaps[$dialect] ?? [];
            foreach ($swaps as $d => $pairs) {
                foreach ($d === $dialect ? [] : $pairs as $msa => $dial) {
                    $map[$dial] ??= $swaps[$dialect][$msa] ?? $msa;
                }
            }
        }
        $add('dialect', static::replaceWords($line, $map));

        // 2. A clean spelling form: no diacritics or tatweel (they break some fonts and character counts).
        $add('spelling', trim((string) preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $line)));

        // 3. CTA swaps: a library CTA in the line is replaced with this dialect's CTAs.
        $ctas = array_column(static::find(null, null, null, 'cta'), 'text');
        usort($ctas, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($ctas as $cta) {
            if (str_contains($line, $cta)) {
                foreach (array_slice(array_column(static::find($dialect, $sector, null, 'cta'), 'text'), 0, 4) as $new) {
                    $add('cta', str_replace($cta, $new, $line));
                }
                break;
            }
        }

        return $out;
    }

    /** @param  array<string, string>  $map */
    protected static function replaceWords(string $line, array $map): string
    {
        foreach ($map as $from => $to) {
            $line = (string) preg_replace('/(?<![\p{L}])'.preg_quote($from, '/').'(?![\p{L}])/u', $to, $line);
        }

        return $line;
    }
}
