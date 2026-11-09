<?php

namespace App\Support\Seo;

use App\Support\Arabic\Normaliser;

class SerpReadiness
{
    /**
     * @return array{score: int, widths: array<string, mixed>, keyword_in_title: bool, keyword_in_description: bool, needs_ltr_bdi: bool}
     */
    public static function evaluateSnippet(string $title, string $description, string $keyword, ?string $country = null): array
    {
        $widths = PageAudit::serpWidths($title, $description);
        $kwTitle = trim($keyword) !== "" && Normaliser::sameKeyword($keyword, $title, $country);
        $kwDesc = trim($keyword) !== "" && Normaliser::sameKeyword($keyword, $description, $country);

        $hasArabic = (bool) preg_match("/[\x{0600}-\x{06FF}]/u", $title . " " . $description);
        $hasLatin = (bool) preg_match("/[A-Za-z]{2,}/", $title . " " . $description);
        $needsBdi = $hasArabic && $hasLatin;

        $score = 0;
        if ($widths["title_chars"] > 0 && !$widths["title_truncated"]) {
            $score += 35;
        }
        if ($widths["description_chars"] > 0 && !$widths["description_truncated"]) {
            $score += 35;
        }
        if ($kwTitle) {
            $score += 20;
        }
        if ($kwDesc) {
            $score += 10;
        }

        return [
            "score" => $score,
            "widths" => $widths,
            "keyword_in_title" => $kwTitle,
            "keyword_in_description" => $kwDesc,
            "needs_ltr_bdi" => $needsBdi,
        ];
    }
}
