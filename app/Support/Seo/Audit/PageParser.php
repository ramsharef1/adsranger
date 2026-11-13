<?php

namespace App\Support\Seo\Audit;

use App\Support\Seo\PageAudit;
use App\Support\Seo\SafeFetcher;

/**
 * Reads the parts of one HTML page the A1 rules need (regex over the raw HTML, no DOM: pages in the wild are often not well-formed).
 *
 * @phpstan-type Parsed array{lang: string, dir: string, title: string, description: string, h1s: list<string>, canonical: string,
 *   hreflang: list<array{lang: string, href: string}>, links: list<string>, images_no_alt: int, images_no_alt_src: list<string>, noindex: bool, fonts: list<string>,
 *   font_display: bool, arabic_font: bool, json_ld: list<string>, json_ld_invalid: int, text: string, word_count: int, arabic_ratio: float,
 *   title_px: int, speed: array{blocking_scripts: int, blocking_scripts_src: list<string>, blocking_css: int, hero_img_no_dims: ?string, inline_bytes: int}}
 */
class PageParser
{
    private const ARABIC_FONT_HINT = '/(Arabic|Tajawal|Cairo|Almarai|Changa|Amiri|Noto\s*(Naskh|Kufi|Sans)\s*Arabic|Rubik|Vazirmatn|Readex|El\s*Messiri|Harmattan|Lateef|Scheherazade|Markazi|Baloo\s*Bhaijaan|Alexandria|Zain|Kufi|Naskh|Dubai|Janna|Jazeera|Hacen|Droid\s*Arabic)/i';

    /** @return Parsed */
    public static function parse(string $html, string $pageUrl): array
    {
        $html = mb_check_encoding($html, 'UTF-8') ? $html : SafeFetcher::toUtf8($html);
        $head = preg_match('/<head\b.*?<\/head>/is', $html, $hm) ? $hm[0] : substr($html, 0, 20000);
        $htmlTag = preg_match('/<html\b[^>]*>/i', $html, $tm) ? $tm[0] : '';
        $lang = preg_match('/\blang=["\']?([A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?)["\']?/i', $htmlTag, $m) ? $m[1] : '';
        $dir = preg_match('/\bdir=["\']?(rtl|ltr|auto)["\']?/i', $htmlTag, $m) ? strtolower($m[1]) : '';

        $title = preg_match('/<title[^>]*>(.*?)<\/title>/is', $head, $m) ? self::text($m[1]) : '';
        $description = '';
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\']/is', $head, $m)
            || preg_match('/<meta[^>]*content=["\']([^"\']*)["\'][^>]*name=["\']description["\']/is', $head, $m)) {
            $description = self::text($m[1]);
        }
        preg_match_all('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $hm1);
        $h1s = array_values(array_filter(array_map(fn (string $r) => self::text($r), $hm1[1] ?? []), fn (string $t) => $t !== ''));

        $canonical = preg_match('/<link[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $head, $m)
            || preg_match('/<link[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']canonical["\']/i', $head, $m) ? self::absolute($pageUrl, html_entity_decode($m[1])) : '';

        $hreflang = [];
        preg_match_all('/<link\b[^>]*>/i', $head, $links);
        foreach ($links[0] as $tag) {
            if (! preg_match('/rel=["\']alternate["\']/i', $tag) || ! preg_match('/hreflang=["\']([^"\']+)["\']/i', $tag, $hl) || ! preg_match('/href=["\']([^"\']+)["\']/i', $tag, $hr)) {
                continue;
            }
            $hreflang[] = ['lang' => trim($hl[1]), 'href' => self::absolute($pageUrl, html_entity_decode($hr[1]))];
        }

        $noindex = (bool) preg_match('/<meta[^>]*name=["\']robots["\'][^>]*content=["\'][^"\']*noindex/i', $head)
            || (bool) preg_match('/<meta[^>]*content=["\'][^"\']*noindex[^"\']*["\'][^>]*name=["\']robots["\']/i', $head);

        preg_match_all('/<a\b[^>]*href=["\']([^"\'#]+)["\']/i', $html, $am);
        $pageLinks = [];
        foreach ($am[1] as $href) {
            $href = trim(html_entity_decode($href));
            if ($href === '' || preg_match('/^(mailto:|tel:|javascript:|data:|whatsapp:|sms:)/i', $href)) {
                continue;
            }
            $pageLinks[] = self::absolute($pageUrl, $href);
        }

        preg_match_all('/<img\b[^>]*>/i', $html, $im);
        $noAlt = array_values(array_filter($im[0], fn (string $tag) => ! preg_match('/\balt\s*=/i', $tag)));
        $imagesNoAlt = count($noAlt);
        // FS-13: keep the first 5 addresses so the AI box can draft alt text for them.
        $imagesNoAltSrc = [];
        foreach (array_slice($noAlt, 0, 5) as $tag) {
            if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $sm) && ! str_starts_with(trim($sm[1]), 'data:')) {
                $imagesNoAltSrc[] = mb_substr(self::absolute($pageUrl, trim(html_entity_decode($sm[1]))), 0, 90);
            }
        }

        $fonts = [];
        $fontDisplay = true;
        if (preg_match_all('/<link[^>]*href=["\']([^"\']*fonts\.googleapis\.com\/css[^"\']*)["\']/i', $head, $fm)) {
            foreach ($fm[1] as $href) {
                $href = html_entity_decode($href);
                $fonts[] = $href;
                if (! preg_match('/display=/i', $href)) {
                    $fontDisplay = false;
                }
            }
        }
        if (preg_match_all('/@font-face\s*\{([^}]*)\}/is', $html, $ff)) {
            foreach ($ff[1] as $block) {
                $fonts[] = preg_match('/font-family\s*:\s*["\']?([^;"\'}]+)/i', $block, $fn) ? trim($fn[1]) : '@font-face';
                if (! preg_match('/font-display\s*:/i', $block)) {
                    $fontDisplay = false;
                }
            }
        }
        $arabicFont = (bool) preg_match(self::ARABIC_FONT_HINT, implode(' ', $fonts));

        $jsonLd = [];
        $invalid = 0;
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $sm)) {
            foreach ($sm[1] as $raw) {
                $decoded = json_decode(trim($raw), true);
                if ($decoded === null) {
                    $invalid++;
                } else {
                    $jsonLd[] = trim($raw);
                }
            }
        }

        $body = preg_match('/<body\b.*?<\/body>/is', $html, $bm) ? $bm[0] : $html;
        $body = preg_replace('/<(script|style|noscript|svg)\b.*?<\/\1>/is', ' ', $body) ?? $body;
        $text = self::text($body);
        $wordCount = count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $letters = preg_replace('/[^\p{L}]/u', '', $text) ?? '';
        $arabicLetters = preg_replace('/[^\p{Arabic}]/u', '', $letters) ?? '';
        $ratio = mb_strlen($letters) > 0 ? mb_strlen($arabicLetters) / mb_strlen($letters) : 0.0;

        return [
            'lang' => $lang, 'dir' => $dir, 'title' => $title, 'description' => $description, 'h1s' => $h1s, 'canonical' => $canonical,
            'hreflang' => $hreflang, 'links' => array_values(array_unique($pageLinks)), 'images_no_alt' => $imagesNoAlt, 'images_no_alt_src' => $imagesNoAltSrc, 'noindex' => $noindex,
            'fonts' => $fonts, 'font_display' => $fontDisplay, 'arabic_font' => $arabicFont, 'json_ld' => $jsonLd, 'json_ld_invalid' => $invalid,
            'text' => mb_substr($text, 0, 4000), 'word_count' => $wordCount, 'arabic_ratio' => round($ratio, 3),
            'title_px' => (int) PageAudit::serpWidths($title, $description)['title_px'],
            'speed' => self::speed($html, $head, $pageUrl),
        ];
    }

    /**
     * A2 static asset facts (E2-062): read from the HTML only, no timing. A render-blocking script is a <script src> in <head>
     * without defer, async or type="module"; a render-blocking stylesheet is a <link rel="stylesheet"> in <head> whose media
     * applies on load (none, all, screen). The hero image is the first <img> in <body>; inline bytes count inline scripts,
     * styles and data: URIs.
     *
     * @return array{blocking_scripts: int, blocking_scripts_src: list<string>, blocking_css: int, hero_img_no_dims: ?string, inline_bytes: int}
     */
    public static function speed(string $html, string $head, string $pageUrl): array
    {
        $blockingScripts = [];
        preg_match_all('/<script\b([^>]*)>/i', $head, $sm);
        foreach ($sm[1] as $attrs) {
            if (! preg_match('/\bsrc\s*=\s*["\']?([^"\'\s>]+)/i', $attrs, $src)) {
                continue;
            }
            if (preg_match('/\b(defer|async)\b/i', $attrs) || preg_match('/\btype\s*=\s*["\']?module/i', $attrs)) {
                continue;
            }
            $blockingScripts[] = mb_substr(self::absolute($pageUrl, html_entity_decode($src[1])), 0, 120);
        }

        $blockingCss = 0;
        preg_match_all('/<link\b[^>]*>/i', $head, $lm);
        foreach ($lm[0] as $tag) {
            if (! preg_match('/\brel\s*=\s*["\']?stylesheet/i', $tag)) {
                continue;
            }
            $media = preg_match('/\bmedia\s*=\s*["\']([^"\']*)["\']/i', $tag, $mm) ? strtolower(trim($mm[1])) : '';
            if ($media === '' || $media === 'all' || $media === 'screen') {
                $blockingCss++;
            }
        }

        $body = preg_match('/<body\b.*?<\/body>/is', $html, $bm) ? $bm[0] : '';
        $hero = null;
        if (preg_match('/<img\b[^>]*>/i', $body, $im)) {
            $tag = $im[0];
            $sized = preg_match('/\bwidth\s*=/i', $tag) && preg_match('/\bheight\s*=/i', $tag);
            $styled = preg_match('/\bstyle\s*=\s*["\'][^"\']*(aspect-ratio|height\s*:)/i', $tag);
            if (! $sized && ! $styled) {
                $hero = preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $hs) && ! str_starts_with(trim($hs[1]), 'data:')
                    ? mb_substr(self::absolute($pageUrl, trim(html_entity_decode($hs[1]))), 0, 120) : 'img';
            }
        }

        $inline = 0;
        preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is', $html, $is);
        foreach ($is[1] as $i => $attrs) {
            if (! preg_match('/\bsrc\s*=/i', $attrs) && ! preg_match('/application\/(ld\+)?json/i', $attrs)) {
                $inline += strlen($is[2][$i]);
            }
        }
        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $html, $st);
        foreach ($st[1] as $css) {
            $inline += strlen($css);
        }
        preg_match_all('/data:[a-z]+\/[a-z0-9.+-]+;base64,[A-Za-z0-9+\/=]+/i', $html, $du);
        foreach ($du[0] as $uri) {
            $inline += strlen($uri);
        }

        return ['blocking_scripts' => count($blockingScripts), 'blocking_scripts_src' => array_slice($blockingScripts, 0, 5), 'blocking_css' => $blockingCss, 'hero_img_no_dims' => $hero, 'inline_bytes' => $inline];
    }

    public static function text(string $fragment): string
    {
        $t = html_entity_decode(strip_tags($fragment), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    }

    public static function absolute(string $base, string $href): string
    {
        if (preg_match('/^https?:\/\//i', $href)) {
            return self::normalise($href);
        }
        $b = parse_url($base);
        $scheme = $b['scheme'] ?? 'https';
        $host = $b['host'] ?? '';
        $port = isset($b['port']) ? ':'.$b['port'] : '';
        if (str_starts_with($href, '//')) {
            return self::normalise($scheme.':'.$href);
        }
        if (str_starts_with($href, '/')) {
            return self::normalise("$scheme://$host$port$href");
        }
        if (str_starts_with($href, '?')) {
            return self::normalise("$scheme://$host$port".($b['path'] ?? '/').$href);
        }
        $path = $b['path'] ?? '/';
        $dir = substr($path, 0, (int) strrpos($path, '/') + 1);

        return self::normalise("$scheme://$host$port$dir$href");
    }

    /** Drops the fragment, resolves ./ and ../, lower-cases the host, keeps the rest as is. */
    public static function normalise(string $url): string
    {
        $url = preg_replace('/#.*$/', '', $url) ?? $url;
        $p = parse_url($url);
        if (! isset($p['host'])) {
            return $url;
        }
        $segments = [];
        foreach (explode('/', $p['path'] ?? '/') as $seg) {
            if ($seg === '..') {
                array_pop($segments);
            } elseif ($seg !== '.') {
                $segments[] = $seg;
            }
        }
        $path = implode('/', $segments);
        if ($path === '') {
            $path = '/';
        }

        return ($p['scheme'] ?? 'https').'://'.strtolower($p['host']).(isset($p['port']) ? ':'.$p['port'] : '').$path.(isset($p['query']) ? '?'.$p['query'] : '');
    }

    public static function sameHost(string $a, string $b): bool
    {
        $ha = strtolower((string) parse_url($a, PHP_URL_HOST));
        $hb = strtolower((string) parse_url($b, PHP_URL_HOST));

        return $ha !== '' && (preg_replace('/^www\./', '', $ha) === preg_replace('/^www\./', '', $hb));
    }
}
