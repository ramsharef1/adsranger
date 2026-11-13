<?php

namespace App\Support\AiWorkspace;

use App\Support\Arabic\Normaliser;

/**
 * FS-15 (R7 part 4): negative keywords in every spelling / dialect form. The text layer decides first (job, free,
 * download and learning cues in Arabic, Arabizi and English); only the terms it can't decide go to the agency's AI.
 * Each negative is then widened with the Arabic text layer's spelling, dialect and Arabizi forms.
 */
class Negatives
{
    /** cue words (normalised) → reason key */
    public const CUES = [
        'jobs' => ['وظايف', 'وظيفه', 'توظيف', 'راتب', 'رواتب', 'شاغر', 'jobs', 'job', 'salary', 'hiring', 'wazayef', 'wazifa'],
        'free' => ['مجاني', 'مجانا', 'ببلاش', 'بلاش', 'free', 'majani', 'balash'],
        'download' => ['تحميل', 'تنزيل', 'download', 'pdf', 'apk', 'tahmil'],
        'learning' => ['كورس', 'دوره', 'تعليم', 'شرح', 'تعلم', 'course', 'tutorial', 'learn', 'sharh'],
    ];

    public const REASONS = ['jobs' => 'Job seekers', 'free' => 'Looking for something free', 'download' => 'Downloads', 'learning' => 'Learning, not buying', 'ai' => 'Proposed by your AI'];

    /** @return list<string> one term per line or comma, trimmed, unique, at most 200 */
    public static function split(string $text): array
    {
        $terms = array_map(fn ($t) => trim((string) preg_replace('/\s+/u', ' ', $t)), preg_split('/[\r\n,،]+/u', $text) ?: []);

        return array_slice(array_values(array_unique(array_filter($terms, fn ($t) => $t !== '' && mb_strlen($t) <= 80))), 0, 200);
    }

    /** @return array{clear: list<array{term: string, reason: string}>, unclear: list<string>} */
    public static function classify(array $terms): array
    {
        $clear = [];
        $unclear = [];
        foreach ($terms as $term) {
            $reason = static::cue($term);
            if ($reason !== null) {
                $clear[] = ['term' => $term, 'reason' => $reason];
            } else {
                $unclear[] = $term;
            }
        }

        return ['clear' => $clear, 'unclear' => $unclear];
    }

    public static function cue(string $term): ?string
    {
        $words = explode(' ', Normaliser::key($term));
        foreach (self::CUES as $reason => $cues) {
            foreach ($cues as $cue) {
                foreach ($words as $w) {
                    // «الوظايف», «بالمجان», «وتحميل»: allow the common Arabic prefixes before the cue.
                    if ($w === $cue || preg_match('/^(?:و|ف|ب|ل|ال|وال|بال|لل)'.preg_quote($cue, '/').'$/u', $w)) {
                        return $reason;
                    }
                }
            }
        }

        return null;
    }

    /** @return list<string> the term's other spelling / dialect / Arabizi forms (at most 6) */
    public static function forms(string $term): array
    {
        return array_slice(array_column(Normaliser::variants($term), 'text'), 0, 6);
    }

    /** @param list<array{term: string, reason: string}> $negatives @return array{negatives: list<array{term: string, reason: string, forms: list<string>}>} */
    public static function output(array $negatives): array
    {
        $seen = [];
        $out = [];
        foreach ($negatives as $n) {
            $k = Normaliser::key($n['term']);
            if ($k === '' || isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[] = ['term' => $n['term'], 'reason' => isset(self::REASONS[$n['reason']]) ? __(self::REASONS[$n['reason']]) : mb_substr(strip_tags($n['reason']), 0, 120), 'forms' => static::forms($n['term'])];
        }

        return ['negatives' => $out];
    }
}
