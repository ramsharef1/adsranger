<?php

namespace App\Support\Seo;

use App\Support\Arabic\Normaliser;

/**
 * SEO Slice 0 / A1 page audit engine: inspects an Arabic or bilingual HTML document
 * using Normaliser and serp_widths.json (pure, offline-capable, deterministic).
 */
class PageAudit
{
    /**
     * @return array{
     *   score: int,
     *   checks: array{
     *     ar_lang: bool,
     *     rtl_dir: bool,
     *     has_title: bool,
     *     has_meta_description: bool,
     *     has_h1: bool,
     *     single_h1: bool,
     *     has_canonical: bool,
     *     has_json_ld: bool,
     *     has_question_heading: bool,
     *     images_have_alt: bool,
     *     noindex: bool,
     *     keyword_in_title: ?bool,
     *     keyword_in_h1: ?bool,
     *     dialect_tags: list<string>,
     *     cities: list<string>
     *   },
     *   issues: list<string>
     * }
     */
    public static function serpWidths(string $title, string $description): array
    {
        $path = resource_path('seo/serp_widths.json');
        $table = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $tChars = mb_strlen(trim($title));
        $dChars = mb_strlen(trim($description));
        $tPx = (int) round($tChars * 9.5);
        $dPx = (int) round($dChars * 7.2);
        $tMax = (int) ($table['limits']['title_px'] ?? $table['title_max_px'] ?? 580);
        $dMax = (int) ($table['limits']['description_px'] ?? $table['description_max_px'] ?? 960);

        return ['title_px' => $tPx, 'description_px' => $dPx, 'title_chars' => $tChars, 'description_chars' => $dChars, 'title_truncated' => $tPx > $tMax, 'description_truncated' => $dPx > $dMax];
    }

    public static function auditHtml(string $html, ?string $keyword = null, ?string $country = null): array
    {
        $arLang = (bool) preg_match('/<html[^>]*\blang=["\']ar(?:-|_)?[a-zA-Z]*["\']/i', $html);
        $rtlDir = (bool) preg_match('/<html[^>]*\bdir=["\']rtl["\']/i', $html);

        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $tm)) {
            $title = trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $hasTitle = mb_strlen($title) > 0;

        $desc = '';
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/is', $html, $dm)
            || preg_match('/<meta[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\']/is', $html, $dm)) {
            $desc = trim(html_entity_decode($dm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $hasDesc = mb_strlen($desc) > 0;

        preg_match_all('/<h1[^>]*>(.*?)<\/h1>/is', $html, $h1Matches);
        $h1s = array_values(array_filter(array_map(
            fn (string $raw) => trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            $h1Matches[1] ?? []
        ), fn (string $t) => $t !== ''));
        $hasH1 = count($h1s) > 0;
        $singleH1 = count($h1s) === 1;

        $hasCanonical = (bool) preg_match('/<link[^>]*rel=["\']canonical["\'][^>]*href=["\'][^"\']+["\']/i', $html)
            || (bool) preg_match('/<link[^>]*href=["\'][^"\']+["\'][^>]*rel=["\']canonical["\']/i', $html);

        $hasJsonLd = (bool) preg_match('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>\s*\{/is', $html);
        $hasQuestionHeading = (bool) preg_match('/<h[23][^>]*>[^<]*(?:؟|\?)[^<]*<\/h[23]>/iu', $html);

        preg_match_all('/<img\b[^>]*>/i', $html, $imgMatches);
        $imgs = $imgMatches[0] ?? [];
        $imagesHaveAlt = true;
        foreach ($imgs as $imgTag) {
            if (! preg_match('/\balt=["\']([^"\']+)["\']/i', $imgTag, $am) || trim($am[1]) === '') {
                $imagesHaveAlt = false;
                break;
            }
        }

        $noindex = (bool) preg_match('/<meta[^>]*name=["\']robots["\'][^>]*content=["\'][^"\']*noindex/i', $html);

        $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
        $dialects = Normaliser::dialectTags($plain);
        $cities = method_exists(Normaliser::class, 'cityOf') ? (array) Normaliser::cityOf($plain) : [];

        $kwInTitle = null;
        $kwInH1 = null;
        if ($keyword !== null && trim($keyword) !== '') {
            $kwInTitle = $hasTitle && Normaliser::sameKeyword($keyword, $title, $country);
            $kwInH1 = $hasH1 && Normaliser::sameKeyword($keyword, $h1s[0], $country);
        }

        $issues = [];
        if (! $arLang) {
            $issues[] = 'missing_ar_lang';
        }
        if (! $rtlDir) {
            $issues[] = 'missing_rtl_dir';
        }
        if (! $hasTitle) {
            $issues[] = 'missing_title';
        }
        if (! $hasDesc) {
            $issues[] = 'missing_meta_description';
        }
        if (! $hasH1) {
            $issues[] = 'missing_h1';
        } elseif (! $singleH1) {
            $issues[] = 'multiple_h1';
        }
        if (! $hasCanonical) {
            $issues[] = 'missing_canonical';
        }
        if (! $imagesHaveAlt) {
            $issues[] = 'image_missing_alt';
        }
        if ($noindex) {
            $issues[] = 'page_is_noindex';
        }

        $weights = [
            'ar_lang' => 15,
            'rtl_dir' => 15,
            'has_title' => 15,
            'has_meta_description' => 15,
            'single_h1' => 15,
            'has_canonical' => 10,
            'has_json_ld' => 5,
            'has_question_heading' => 5,
            'images_have_alt' => 5,
        ];
        $checks = [
            'ar_lang' => $arLang,
            'rtl_dir' => $rtlDir,
            'has_title' => $hasTitle,
            'has_meta_description' => $hasDesc,
            'has_h1' => $hasH1,
            'single_h1' => $singleH1,
            'has_canonical' => $hasCanonical,
            'has_json_ld' => $hasJsonLd,
            'has_question_heading' => $hasQuestionHeading,
            'images_have_alt' => $imagesHaveAlt,
            'noindex' => $noindex,
            'keyword_in_title' => $kwInTitle,
            'keyword_in_h1' => $kwInH1,
            'dialect_tags' => $dialects,
            'cities' => array_values($cities),
        ];

        $score = 0;
        foreach ($weights as $k => $w) {
            if (! empty($checks[$k])) {
                $score += $w;
            }
        }

        return [
            'score' => $score,
            'checks' => $checks,
            'issues' => $issues,
            'serp' => self::serpWidths($title, $desc),
        ];
    }
}
