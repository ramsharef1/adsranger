<?php

namespace App\Support\Import;

use App\Support\Arabic\Normaliser;
use App\Support\TextClean;

/**
 * PS-02 (D-296): proposes which client an imported campaign belongs to, from the names alone — Arabic or Latin,
 * «عيادة النور - رمضان» or NOOR_LEADS_KSA. A suggestion only; the user confirms before anything is assigned.
 */
class CampaignMatcher
{
    /** Words too common in campaign names to identify a client. */
    public const STOP = ['campaign', 'ads', 'ad', 'leads', 'lead', 'sales', 'traffic', 'ksa', 'uae', 'jordan', 'kw', 'qa', 'bh', 'om', 'eg',
        'ramadan', 'eid', 'new', 'test', 'copy', 'حملة', 'اعلان', 'اعلانات', 'رمضان', 'عيد', 'عروض', 'عرض', 'جديد'];

    /** @return list<list<string>> one group of comparable forms per word: the Arabic key without «ال», or a Latin word plus its Arabic forms */
    public static function words(string $text): array
    {
        $text = (string) TextClean::invisible($text);
        $out = [];
        foreach (preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) as $w) {
            $w = mb_strtolower($w);
            if (preg_match('/\p{Arabic}/u', $w)) {
                $k = preg_replace('/^ال(?=\p{Arabic}{2,})/u', '', Normaliser::key($w));
                if (mb_strlen($k) >= 2 && ! in_array($k, self::STOP, true)) {
                    $out[$k] = [$k];
                }
            } elseif (mb_strlen($w) >= 3 && ! in_array($w, self::STOP, true) && ! ctype_digit($w)) {
                $forms = [$w];
                foreach (Normaliser::arabiziToArabic($w) as $ar) {
                    $forms[] = preg_replace('/^ال(?=\p{Arabic}{2,})/u', '', Normaliser::key($ar));
                }
                $out[$w] = array_values(array_unique($forms));
            }
        }

        return array_values($out);
    }

    /**
     * Best client for a campaign name, or null when no client is clearly ahead.
     *
     * @param  array<int, string>  $clients  id => name
     */
    public static function suggest(string $campaign, array $clients): ?int
    {
        $c = array_merge(...(self::words($campaign) ?: [[]]));
        if ($c === []) {
            return null;
        }
        $scores = [];
        foreach ($clients as $id => $name) {
            $words = self::words($name);
            if ($words === []) {
                continue;
            }
            // Share of the client's words found in the campaign name, in any script.
            $scores[$id] = count(array_filter($words, fn (array $forms) => array_intersect($forms, $c) !== [])) / count($words);
        }
        arsort($scores);
        $top = array_slice($scores, 0, 2, true);
        $ids = array_keys($top);
        if ($ids === [] || $top[$ids[0]] < 0.5 || (isset($ids[1]) && $top[$ids[1]] === $top[$ids[0]])) {
            return null;
        }

        return (int) $ids[0];
    }
}
