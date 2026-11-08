<?php

namespace App\Support\Arabic;

/**
 * Arabic text layer (SEO slice 0 step 0.1, F3). Pure functions: no database, no network.
 * Rules live only in resources/arabic/normalise.json; resources/js/arabic/normalise.js mirrors every function
 * with the same names and results (parity test: tests/arabic/parity.mjs + ArabicNormaliserTest).
 * key() is a search key only and is never shown to users.
 */
class Normaliser
{
    /** @var array<string, mixed>|null */
    private static ?array $rules = null;

    /** @var array<string, array{0: string, 1: string}>|null key(city or spelling) => [city, country] */
    private static ?array $cityIndex = null;

    public const DIALECTS = ['gulf', 'levant', 'egypt', 'maghreb'];

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return self::$rules ??= json_decode((string) file_get_contents(resource_path('arabic/normalise.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function key(string $text): string
    {
        $r = self::rules();
        $text = mb_strtolower($text, 'UTF-8');
        $text = (string) preg_replace('/['.self::tashkeelClass().']/u', '', $text);
        $text = str_replace($r['strip']['tatweel'], '', $text);
        $text = strtr($text, $r['letter_maps']);
        foreach (['eastern', 'persian'] as $set) {
            $digits = mb_str_split($r['digits'][$set]);
            $text = str_replace($digits, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $text);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Spelling, city and dialect forms of a term. Every form is a candidate until data shows demand (FR-087).
     *
     * @param  array{country?: ?string, dialects?: list<string>}  $options
     * @return list<array{text: string, type: string, status: string}>
     */
    public static function variants(string $term, array $options = []): array
    {
        $r = self::rules();
        $term = trim((string) preg_replace('/\s+/u', ' ', $term));
        $out = [];
        $add = function (string $text, string $type) use (&$out, $term): void {
            if ($text !== $term && ! isset($out[$text])) {
                $out[$text] = ['text' => $text, 'type' => $type, 'status' => 'candidate'];
            }
        };

        $plain = (string) preg_replace('/['.self::tashkeelClass().']/u', '', str_replace($r['strip']['tatweel'], '', $term));
        $add(strtr($plain, $r['letter_maps']), 'spelling');

        foreach ($r['city_spellings'] as $city => $spellings) {
            foreach (array_merge([$city], $spellings) as $form) {
                if (self::hasPhrase($term, $form)) {
                    foreach (array_merge([$city], $spellings) as $other) {
                        $add(self::replacePhrase($term, $form, $other), 'spelling');
                    }
                }
            }
        }

        $dialects = $options['dialects'] ?? self::DIALECTS;
        foreach ($r['msa_question_words'] as $slot => $msa) {
            if (! self::hasPhrase($term, $msa)) {
                continue;
            }
            foreach ($dialects as $dialect) {
                $word = $r['dialect_question_words'][$dialect][$slot] ?? null;
                if ($word !== null) {
                    $add(self::replacePhrase($term, $msa, $word), 'dialect');
                }
            }
        }

        if (preg_match('/[a-z0-9]/i', $term) && ! preg_match('/\p{Arabic}/u', $term)) {
            foreach (self::arabiziToArabic($term) as $candidate) {
                $add($candidate, 'arabizi');
            }
        }

        return array_values($out);
    }

    /** @return list<string> Arabic candidates for an Arabizi text; ambiguous digits fan out (8 → ق / غ). */
    public static function arabiziToArabic(string $text): array
    {
        $r = self::rules();
        $max = (int) $r['arabizi_max_candidates'];
        $results = [''];
        foreach (preg_split('/\s+/u', mb_strtolower(trim($text), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $wordForms = [''];
            $chars = mb_str_split($word);
            $i = 0;
            while ($i < count($chars)) {
                [$options, $len] = self::arabiziToken($chars, $i);
                $next = [];
                foreach ($wordForms as $form) {
                    foreach ($options as $opt) {
                        $next[] = $form.$opt;
                    }
                }
                $wordForms = array_slice(array_values(array_unique($next)), 0, $max);
                $i += $len;
            }
            $next = [];
            foreach ($results as $res) {
                foreach ($wordForms as $form) {
                    $next[] = $res === '' ? $form : $res.' '.$form;
                }
            }
            $results = array_slice(array_values(array_unique($next)), 0, $max);
        }

        return array_values(array_filter($results, fn ($s) => $s !== ''));
    }

    /**
     * @param  list<string>  $chars
     * @return array{0: list<string>, 1: int}
     */
    private static function arabiziToken(array $chars, int $i): array
    {
        $r = self::rules();
        $two = $chars[$i].($chars[$i + 1] ?? '');
        if (isset($r['arabizi_map'][$two])) {
            return [(array) $r['arabizi_map'][$two], 2];
        }
        if (isset($r['arabizi_map'][$chars[$i]])) {
            return [(array) $r['arabizi_map'][$chars[$i]], 1];
        }
        if (isset($chars[$i + 1], $r['arabizi_letters'][$two])) {
            return [$r['arabizi_letters'][$two], 2];
        }
        if ($i === count($chars) - 1 && $i > 0 && isset($r['arabizi_final'][$chars[$i]])) {
            return [$r['arabizi_final'][$chars[$i]], 1];
        }
        if ($i === 0 && in_array($chars[$i], ['a', 'e', 'i', 'o', 'u'], true)) {
            return [['ا'], 1];
        }
        if (isset($r['arabizi_letters'][$chars[$i]])) {
            return [$r['arabizi_letters'][$chars[$i]], 1];
        }

        return [[$chars[$i]], 1];
    }

    /** @return list<string> subset of gulf, levant, egypt, maghreb (marker words only); empty = MSA or unknown */
    public static function dialectTags(string $text): array
    {
        $words = explode(' ', self::key($text));
        $tags = [];
        foreach (self::DIALECTS as $dialect) {
            foreach (self::rules()['dialect_markers'][$dialect] as $marker) {
                if (in_array(self::key($marker), $words, true)) {
                    $tags[] = $dialect;
                    break;
                }
            }
        }

        return $tags;
    }

    /** @return array{city: string, country: string}|null */
    public static function cityOf(string $text, ?string $country = null): ?array
    {
        foreach (self::rules()['ambiguous_places'] as $plain => $amb) {
            if (! self::hasPhrase(self::key($text), self::key($plain))) {
                continue;
            }
            $raw = (string) preg_replace('/\s+/u', ' ', $text);
            if (str_contains($raw, mb_substr($plain, 0, 1).$amb['country_mark'])) {
                return null;
            }
            if (str_contains($raw, $amb['city_mark']) || $country === $amb['city'][1]) {
                return ['city' => $amb['city'][0], 'country' => $amb['city'][1]];
            }

            return null;
        }
        $k = self::key($text);
        foreach (self::cityIndex() as $form => [$city, $code]) {
            if (self::hasPhrase($k, $form)) {
                return ['city' => $city, 'country' => $code];
            }
        }

        return null;
    }

    public static function sameKeyword(string $a, string $b, ?string $country = null): bool
    {
        if (self::key($a) !== self::key($b)) {
            return false;
        }
        $ta = self::placeTags($a, $country);
        $tb = self::placeTags($b, $country);

        return $ta === [] || $tb === [] || $ta === $tb;
    }

    /** Share of Arabic words that carry diacritics, 0–1, rounded to 4 places. */
    public static function tashkeelRatio(string $text): float
    {
        $words = array_filter(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY), fn ($w) => preg_match('/[\x{0621}-\x{064A}]/u', $w));
        if ($words === []) {
            return 0.0;
        }
        $marked = count(array_filter($words, fn ($w) => preg_match('/['.self::tashkeelClass().']/u', $w)));

        return round($marked / count($words), 4);
    }

    /** @return list<string> country tags a text points at; "?<word>" when a place stays ambiguous without context */
    private static function placeTags(string $text, ?string $country): array
    {
        $r = self::rules();
        $tags = [];
        $k = self::key($text);
        foreach ($r['ambiguous_places'] as $plain => $amb) {
            if (! self::hasPhrase($k, self::key($plain))) {
                continue;
            }
            if (str_contains($text, mb_substr($plain, 0, 1).$amb['country_mark'])) {
                $tags[] = $amb['country'];
            } elseif (str_contains($text, $amb['city_mark'])) {
                $tags[] = $amb['city'][1];
            } elseif ($country === $amb['city'][1] || $country === $amb['country']) {
                $tags[] = $country;
            } else {
                $tags[] = '?'.self::key($plain);
            }
            $k = trim(str_replace(self::key($plain), ' ', $k));
        }
        foreach (self::cityIndex() as $form => [, $code]) {
            if (self::hasPhrase($k, $form)) {
                $tags[] = $code;
            }
        }
        foreach ($r['countries'] as $code => $names) {
            foreach ($names as $name) {
                if (self::hasPhrase($k, self::key($name))) {
                    $tags[] = $code;
                }
            }
        }
        $tags = array_values(array_unique($tags));
        sort($tags);

        return $tags;
    }

    /** @return array<string, array{0: string, 1: string}> */
    private static function cityIndex(): array
    {
        if (self::$cityIndex === null) {
            $r = self::rules();
            $index = [];
            foreach ($r['cities'] as $code => $cities) {
                foreach ($cities as $city) {
                    if (isset($r['ambiguous_places'][$city])) {
                        continue;
                    }
                    foreach (array_merge([$city], $r['city_spellings'][$city] ?? []) as $form) {
                        $index[self::key($form)] = [$city, $code];
                    }
                }
            }
            uksort($index, fn ($x, $y) => mb_strlen($y) <=> mb_strlen($x) ?: strcmp($x, $y));
            self::$cityIndex = $index;
        }

        return self::$cityIndex;
    }

    private static function hasPhrase(string $text, string $phrase): bool
    {
        return $phrase !== '' && str_contains(' '.$text.' ', ' '.$phrase.' ');
    }

    private static function replacePhrase(string $text, string $phrase, string $with): string
    {
        return trim(str_replace(' '.$phrase.' ', ' '.$with.' ', ' '.$text.' '));
    }

    private static function tashkeelClass(): string
    {
        return implode('', array_map(fn ($range) => '\x{'.$range[0].'}-\x{'.$range[1].'}', self::rules()['strip']['tashkeel_ranges']));
    }
}
