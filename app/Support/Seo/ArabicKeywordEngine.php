<?php

namespace App\Support\Seo;

use App\Support\Arabic\Normaliser;

class ArabicKeywordEngine
{
    /**
     * @return array{seed: string, normalised_key: string, variants: list<string>, arabizi: list<string>, dialect_tags: list<string>, questions: list<string>, volumes_included: bool}
     */
    public static function expand(string $seed): array
    {
        $seed = trim($seed);
        $key = Normaliser::key($seed);
        $variants = method_exists(Normaliser::class, "variants") ? array_values(array_unique(array_filter(array_merge([$seed, $key], array_filter((array) Normaliser::variants($seed), 'is_string'))))) : [$seed];
        $arabizi = method_exists(Normaliser::class, "arabicToArabizi")
            ? array_values(array_unique((array) Normaliser::arabicToArabizi($seed)))
            : [];
        $dialects = Normaliser::dialectTags($seed);

        $questions = [];
        if ($seed !== "") {
            foreach (["كيف", "ما هو", "كم سعر", "أفضل", "أين"] as $prefix) {
                $questions[] = "{$prefix} {$seed}؟";
            }
        }

        return [
            "seed" => $seed,
            "normalised_key" => $key,
            "variants" => $variants,
            "arabizi" => $arabizi,
            "dialect_tags" => $dialects,
            "questions" => $questions,
            "volumes_included" => false,
        ];
    }

    public static function toCsv(array $expanded): string
    {
        $lines = ["type,term"];
        foreach ($expanded["variants"] ?? [] as $v) {
            $lines[] = "variant," . str_replace(",", " ", (string) $v);
        }
        foreach ($expanded["arabizi"] ?? [] as $a) {
            $lines[] = "arabizi," . str_replace(",", " ", (string) $a);
        }
        foreach ($expanded["questions"] ?? [] as $q) {
            $lines[] = "question," . str_replace(",", " ", (string) $q);
        }
        return implode("\n", $lines) . "\n";
    }
}
