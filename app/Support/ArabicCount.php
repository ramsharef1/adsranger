<?php

namespace App\Support;

/**
 * UX-143 / UX-160 (I-X-2 plural rule): an Arabic count phrase with the right noun form.
 * 1 and 2 use the singular / dual word (no digit), 3–10 the plural, 11–99 the accusative singular, and 100+ follows
 * the last two digits. The digits are wrapped in an LTR isolate so they never flip inside RTL text.
 */
class ArabicCount
{
    /** @var array<string, array{one: string, two: string, few: string, many: string, hundred: string}> */
    private const NOUNS = [
        'days' => ['one' => 'يوم واحد', 'two' => 'يومين', 'few' => 'أيام', 'many' => 'يومًا', 'hundred' => 'يوم'],
        'search_terms' => ['one' => 'عبارة بحث واحدة', 'two' => 'عبارتي بحث', 'few' => 'عبارات بحث', 'many' => 'عبارة بحث', 'hundred' => 'عبارة بحث'],
        'clicks' => ['one' => 'نقرة واحدة', 'two' => 'نقرتين', 'few' => 'نقرات', 'many' => 'نقرة', 'hundred' => 'نقرة'],
        'times' => ['one' => 'مرة واحدة', 'two' => 'مرتين', 'few' => 'مرات', 'many' => 'مرة', 'hundred' => 'مرة'],
    ];

    public static function phrase(int|string $count, string $noun): string
    {
        $forms = self::NOUNS[$noun];
        $n = (int) preg_replace('/\D/', '', (string) $count);
        $shown = "\u{2066}".(is_string($count) ? trim($count) : (string) $count)."\u{2069}";

        if ($n === 1) {
            return $forms['one'];
        }
        if ($n === 2) {
            return $forms['two'];
        }
        $lastTwo = $n % 100;
        $word = match (true) {
            $lastTwo >= 3 && $lastTwo <= 10 => $forms['few'],
            $lastTwo >= 11 => $forms['many'],
            default => $n < 100 ? $forms['few'] : $forms['hundred'],
        };

        return $shown.' '.$word;
    }
}
