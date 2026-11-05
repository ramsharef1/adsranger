<?php

namespace App\Support;

/**
 * #143: Automated policy pre-check for ad copy and creatives against Meta and Google rules.
 */
class PolicyPreCheck
{
    public const BANNED_TERMS = [
        'cure', 'heal', 'miracle', 'guaranteed results', '100% success',
        'get rich quick', 'make money fast', 'lose weight instantly',
        'علاج نهائي', 'ضمان 100%', 'ربح مضمون', 'تخسيس فوري بدون عناء',
    ];

    /** @return array{passed:bool,issues:array<string>} */
    public static function analyze(string $text): array
    {
        $issues = [];
        $lower = mb_strtolower($text);
        foreach (self::BANNED_TERMS as $term) {
            if (mb_stripos($lower, mb_strtolower($term)) !== false) {
                $issues[] = "Prohibited claim or restricted phrase detected: '{$term}'.";
            }
        }
        if (preg_match('/[!]{3,}/u', $text)) {
            $issues[] = "Excessive punctuation (multiple exclamation marks) violates ad platform styling guidelines.";
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
        ];
    }
}
