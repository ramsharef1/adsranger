<?php

namespace App\Support\Seo\Audit;

use App\Support\Arabic\Normaliser;

/**
 * A1 rules (910 BUILD START step 3; SEO_Slice0_Spec 0.3 order: Hreflang, AR-RTL, AR-LANG, AR-FONT, URL, AR-TITLE, AR-ONPAGE, then the
 * standard set). Each issue is [code, detail]. Page rules see one parsed page; site rules see every crawled page.
 *
 * @phpstan-import-type Parsed from PageParser
 */
class Rules
{
    private const TITLE_MAX_PX = 580;

    /**
     * @param  Parsed  $p
     * @return list<array{0: string, 1: string}>
     */
    public static function page(array $p, string $url, ?int $status, ?string $keyword = null): array
    {
        $issues = [];
        if ($status !== null && $status >= 400) {
            return [['STATUS-01', 'HTTP '.$status]];
        }
        $isArabic = $p['arabic_ratio'] >= 0.5 || str_starts_with(strtolower($p['lang']), 'ar');
        $declaredArabic = str_starts_with(strtolower($p['lang']), 'ar');

        // Hreflang codes on this page (pairing and return links are site rules).
        foreach ($p['hreflang'] as $alt) {
            $code = $alt['lang'];
            if (strtolower($code) !== 'x-default' && ! preg_match('/^[a-z]{2,3}(-[A-Z]{2}|-[0-9]{3})?$/', $code)) {
                $issues[] = ['HREF-05', $code];
            }
        }
        if ($p['canonical'] !== '' && $p['hreflang'] !== []) {
            $self = PageParser::normalise($url);
            $ownLangs = array_values(array_filter($p['hreflang'], fn (array $a) => PageParser::normalise($a['href']) === $self && strtolower($a['lang']) !== 'x-default'));
            $canonLangs = array_values(array_filter($p['hreflang'], fn (array $a) => PageParser::normalise($a['href']) === PageParser::normalise($p['canonical']) && strtolower($a['lang']) !== 'x-default'));
            if ($ownLangs !== [] && $canonLangs !== [] && substr(strtolower($ownLangs[0]['lang']), 0, 2) !== substr(strtolower($canonLangs[0]['lang']), 0, 2)) {
                $issues[] = ['HREF-04', $p['canonical']];
            }
        }

        // Arabic and direction.
        if ($isArabic && $p['dir'] !== 'rtl') {
            $issues[] = ['AR-RTL-01', $p['dir'] === '' ? 'no dir' : 'dir="'.$p['dir'].'"'];
        }
        if ($isArabic && ! $declaredArabic) {
            $issues[] = ['AR-LANG-01', $p['lang'] === '' ? 'no lang' : 'lang="'.$p['lang'].'"'];
        }
        if ($isArabic && $p['fonts'] !== [] && ! $p['arabic_font']) {
            $issues[] = ['AR-FONT-01', implode(', ', array_slice($p['fonts'], 0, 3))];
        }
        if ($p['fonts'] !== [] && ! $p['font_display']) {
            $issues[] = ['AR-FONT-02', ''];
        }

        // URL.
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('/%D[89AB]%[0-9A-F]{2}/i', $path)) {
            $issues[] = ['URL-01', $path];
        }
        $decoded = rawurldecode($path);
        foreach (explode('/', $decoded) as $seg) {
            if (preg_match('/\p{Arabic}/u', $seg) && preg_match('/[A-Za-z]/', $seg)) {
                $issues[] = ['URL-02', $seg];
                break;
            }
        }
        if (mb_strlen($url) > 150) {
            $issues[] = ['URL-03', mb_strlen($url).' chars'];
        }

        // Titles and descriptions.
        if ($p['title'] === '') {
            $issues[] = ['META-01', ''];
        } else {
            if ($p['title_px'] > self::TITLE_MAX_PX) {
                $issues[] = ['AR-TITLE-01', $p['title_px'].' px'];
            }
            if ($isArabic && preg_match('/^[A-Za-z]/u', $p['title'])) {
                $issues[] = ['AR-TITLE-02', mb_substr($p['title'], 0, 60)];
            }
            if (Normaliser::tashkeelRatio($p['title']) > 0.34) {
                $issues[] = ['AR-TITLE-03', mb_substr($p['title'], 0, 60)];
            }
        }
        if ($p['description'] === '') {
            $issues[] = ['META-02', ''];
        }
        if ($p['h1s'] === []) {
            $issues[] = ['H1-01', ''];
        } elseif (count($p['h1s']) > 1) {
            $issues[] = ['H1-02', count($p['h1s']).' × h1'];
        }
        if ($keyword !== null && trim($keyword) !== '') {
            $key = Normaliser::key($keyword);
            $inTitle = $p['title'] !== '' && self::containsKey($p['title'], $key);
            $inH1 = $p['h1s'] !== [] && self::containsKey(implode(' ', $p['h1s']), $key);
            if ($p['title'] !== '' && ! $inTitle) {
                $issues[] = ['AR-ONPAGE-01', $keyword];
            }
            if ($p['h1s'] !== [] && ! $inH1) {
                $issues[] = ['AR-ONPAGE-02', $keyword];
            }
        }

        // Indexing.
        if ($p['noindex']) {
            $issues[] = ['IDX-01', ''];
        }
        if ($p['canonical'] === '') {
            $issues[] = ['IDX-02', ''];
        } elseif (PageParser::normalise($p['canonical']) !== PageParser::normalise($url) && ! self::sameButSlash($p['canonical'], $url)
            && ! in_array('HREF-04', array_column($issues, 0), true)) { // UX-248: one cause, one issue
            $issues[] = ['IDX-03', $p['canonical']];
        }

        // Images and schema.
        if ($p['images_no_alt'] > 0) {
            $issues[] = ['IMG-01', mb_substr($p['images_no_alt'].' img'.(($p['images_no_alt_src'] ?? []) ? ': '.implode(' · ', $p['images_no_alt_src']) : ''), 0, 480)];
        }
        if ($p['json_ld_invalid'] > 0) {
            $issues[] = ['SCHEMA-02', $p['json_ld_invalid'].' block(s)'];
        }
        if ($p['json_ld'] === [] && $p['json_ld_invalid'] === 0) {
            $issues[] = ['SCHEMA-01', ''];
        }
        foreach ($p['json_ld'] as $block) {
            if (preg_match('/"(datePublished|dateModified|startDate|endDate|validFrom|validThrough)"\s*:\s*"([^"]*)"/', $block, $dm)
                && ! preg_match('/^\d{4}-\d{2}(-\d{2})?(T[\d:+\-.Z]*)?$/', $dm[2])) {
                $issues[] = ['SCHEMA-03', $dm[1].': '.mb_substr($dm[2], 0, 40)];
                break;
            }
        }

        // A2 speed (E2-062): static asset checks from the HTML only; never a timing claim.
        $speed = $p['speed'] ?? null;
        if (is_array($speed)) {
            $cfg = config('seo_audit.speed', []);
            if ($speed['blocking_scripts'] > (int) ($cfg['blocking_scripts_max'] ?? 0)) {
                $issues[] = ['SPEED-01', mb_substr($speed['blocking_scripts'].' script: '.implode(' · ', $speed['blocking_scripts_src']), 0, 480)];
            }
            if ($speed['blocking_css'] > (int) ($cfg['blocking_css_max'] ?? 2)) {
                $issues[] = ['SPEED-02', $speed['blocking_css'].' CSS'];
            }
            if ($speed['hero_img_no_dims'] !== null) {
                $issues[] = ['SPEED-03', $speed['hero_img_no_dims']];
            }
            if ($speed['inline_bytes'] > (int) ($cfg['inline_bytes_max'] ?? 100_000)) {
                $issues[] = ['SPEED-04', number_format($speed['inline_bytes'] / 1024, 0).' KB'];
            }
        }

        return $issues;
    }

    /**
     * Site-wide rules: hreflang pairing and return links, duplicate titles / descriptions, broken internal links.
     *
     * @param  array<string, array{parsed: Parsed, status: ?int}>  $pages  url => page
     * @param  array<string, ?int>  $linkStatus  url => status of internal links fetched only for the check (HEAD / GET), or null when unknown
     * @return array<string, list<array{0: string, 1: string}>> url => issues
     */
    public static function site(array $pages, array $linkStatus = []): array
    {
        $out = array_fill_keys(array_keys($pages), []);
        $langsSeen = [];
        foreach ($pages as $url => $page) {
            $lang = substr(strtolower($page['parsed']['lang']), 0, 2);
            if ($lang !== '') {
                $langsSeen[$lang] = true;
            }
        }
        $bilingual = count($langsSeen) >= 2;

        $titles = [];
        $descs = [];
        foreach ($pages as $url => $page) {
            $p = $page['parsed'];
            if (($page['status'] ?? 200) >= 400) {
                continue;
            }
            if ($p['title'] !== '') {
                $titles[$p['title']][] = $url;
            }
            if ($p['description'] !== '') {
                $descs[$p['description']][] = $url;
            }

            $self = PageParser::normalise($url);
            if ($bilingual && $p['hreflang'] === []) {
                $out[$url][] = ['HREF-01', ''];
            }
            if ($p['hreflang'] !== []) {
                $hasDefault = false;
                foreach ($p['hreflang'] as $alt) {
                    if (strtolower($alt['lang']) === 'x-default') {
                        $hasDefault = true;

                        continue;
                    }
                    $target = PageParser::normalise($alt['href']);
                    if ($target === $self) {
                        continue;
                    }
                    if (isset($pages[$target])) {
                        $back = array_filter($pages[$target]['parsed']['hreflang'], fn (array $a) => PageParser::normalise($a['href']) === $self);
                        if ($back === []) {
                            $out[$url][] = ['HREF-02', $target];
                        }
                    } elseif (($linkStatus[$target] ?? null) !== null && $linkStatus[$target] >= 400) {
                        $out[$url][] = ['HREF-06', $target];
                    }
                }
                if (! $hasDefault) {
                    $out[$url][] = ['HREF-03', ''];
                }
            }

            foreach ($p['links'] as $link) {
                $target = PageParser::normalise($link);
                $status = $pages[$target]['status'] ?? $linkStatus[$target] ?? null;
                if ($status !== null && $status >= 400) {
                    $out[$url][] = ['LINK-01', $target];
                } elseif ($status !== null && $status >= 300 && $status < 400) {
                    $out[$url][] = ['LINK-02', $target];
                }
            }
        }
        foreach ($titles as $title => $urls) {
            if (count($urls) > 1) {
                foreach ($urls as $url) {
                    $out[$url][] = ['META-03', count($urls).' pages'];
                }
            }
        }
        foreach ($descs as $desc => $urls) {
            if (count($urls) > 1) {
                foreach ($urls as $url) {
                    $out[$url][] = ['META-04', count($urls).' pages'];
                }
            }
        }

        return $out;
    }

    private static function containsKey(string $haystack, string $key): bool
    {
        if ($key === '') {
            return true;
        }
        $h = Normaliser::key($haystack);

        return str_contains($h, $key);
    }

    private static function sameButSlash(string $a, string $b): bool
    {
        return rtrim(PageParser::normalise($a), '/') === rtrim(PageParser::normalise($b), '/');
    }
}
