<?php

namespace App\Support\AiWorkspace;

use App\Support\Arabic\Normaliser;

/**
 * FS-06 (R7 part 4): glossary-locked AR ↔ EN translation. The AI gets the term pairs that occur in the text; after the
 * answer, a local check counts which terms were kept and runs the Arabic brand rules (no «و / ب / ل / ك» joined to a Latin
 * word, platform names in Latin). The check is shown on the saved card; nothing is changed silently.
 */
class Glossary
{
    /** @var array{pairs: list<array{0: string, 1: string}>, keep: list<string>}|null */
    protected static ?array $data = null;

    /** @return array{pairs: list<array{0: string, 1: string}>, keep: list<string>} */
    public static function data(): array
    {
        return self::$data ??= json_decode((string) file_get_contents(resource_path('ai/glossary_v1.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    /** 'ar' when the text is mostly Latin (translate into Arabic), else 'en'. */
    public static function target(string $text): string
    {
        $arabic = preg_match_all('/\p{Arabic}/u', $text);
        $latin = preg_match_all('/[A-Za-z]/', $text);

        return $arabic >= $latin ? 'en' : 'ar';
    }

    /** @return array{pairs: list<array{en: string, ar: string}>, keep: list<string>} only the terms found in the source text */
    public static function relevant(string $source, string $to): array
    {
        $pairs = [];
        foreach (static::found($source, $to) as [$en, $ar]) {
            $pairs[] = ['en' => $en, 'ar' => $ar];
        }

        return ['pairs' => $pairs, 'keep' => array_values(array_filter(static::data()['keep'], fn ($k) => static::hasLatin($source, $k)))];
    }

    /** @return array{kept: int, total: int, missing: list<string>, lint: list<string>} */
    public static function check(string $source, string $output, string $to): array
    {
        $missing = [];
        $total = 0;
        foreach (static::found($source, $to) as [$en, $ar]) {
            $total++;
            $ok = $to === 'ar' ? static::hasArabic($output, $ar) : static::hasLatin($output, $en);
            if (! $ok) {
                $missing[] = $to === 'ar' ? "$en → $ar" : "$ar → $en";
            }
        }
        foreach (static::data()['keep'] as $k) {
            if (static::hasLatin($source, $k)) {
                $total++;
                if (! str_contains($output, $k)) {
                    $missing[] = $k;
                }
            }
        }
        $lint = [];
        if ($to === 'ar') {
            if (preg_match_all('/(?<![\p{Arabic}\w])(?:[وف]?[بلك]|[وف])[A-Za-z]+/u', $output, $m)) {
                $lint[] = __('Arabic letter joined to a Latin word: :w', ['w' => implode(' · ', array_unique($m[0]))]);
            }
            if (preg_match_all('/جوجل|غوغل|ميتا|فيسبوك|فيس بوك|إنستغرام|انستغرام|انستقرام|تيك ?توك|سناب ?شات|واتساب|واتس اب/u', $output, $m)) {
                $lint[] = __('Platform name written in Arabic letters: :w', ['w' => implode(' · ', array_unique($m[0]))]);
            }
        }

        return ['kept' => $total - count($missing), 'total' => $total, 'missing' => $missing, 'lint' => $lint];
    }

    /** @return list<array{0: string, 1: string}> pairs whose source-side term occurs in the text (longest terms first, no double count) */
    protected static function found(string $source, string $to): array
    {
        $pairs = static::data()['pairs'];
        usort($pairs, fn ($a, $b) => mb_strlen($b[$to === 'ar' ? 0 : 1]) <=> mb_strlen($a[$to === 'ar' ? 0 : 1]));
        $text = $to === 'ar' ? ' '.mb_strtolower($source).' ' : ' '.Normaliser::key($source).' ';
        $out = [];
        foreach ($pairs as $p) {
            $needle = $to === 'ar' ? mb_strtolower($p[0]) : static::stem($p[1]);
            $pattern = $to === 'ar' ? '/(?<![a-z])'.preg_quote($needle, '/').'(?![a-z])/u' : '/'.preg_quote($needle, '/').'/u';
            if (preg_match($pattern, $text)) {
                $out[] = $p;
                $text = (string) preg_replace($pattern, ' ', $text);
            }
        }

        return $out;
    }

    /** The Arabic term without its leading «ال», normalised, so «بالإنفاق» and «إنفاق» both count. */
    protected static function stem(string $ar): string
    {
        return Normaliser::key((string) preg_replace('/^ال/u', '', $ar));
    }

    protected static function hasArabic(string $text, string $ar): bool
    {
        return str_contains(Normaliser::key($text), static::stem($ar));
    }

    protected static function hasLatin(string $text, string $term): bool
    {
        return (bool) preg_match('/(?<![A-Za-z])'.preg_quote($term, '/').'(?![A-Za-z])/i', $text);
    }
}
