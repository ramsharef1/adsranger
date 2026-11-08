<?php

namespace App\Support\Seo;

use DOMDocument;
use DOMXPath;

/**
 * Hreflang rule module (PR-015 §2.4 + §5.2-2: the free checker now, the A1 audit's first rule module later).
 * Checks one page and every language version it lists (at most 10 fetches): 200 status, valid codes, self-reference,
 * return links, x-default, canonical to itself, duplicate codes, and lang="ar" + dir="rtl" on Arabic pages.
 * Returns issue codes with parameters; the page builds the words (AR / EN).
 */
class Hreflang
{
    public const MAX_FETCHES = 10;

    /**
     * @return array{pages: list<array{url: string, status: ?int, error: ?string, links: array<string, string>, lang: string, dir: string, canonical: string}>, issues: list<array{code: string, level: string, url: string, value?: string}>}
     */
    public static function check(string $url): array
    {
        $first = SafeFetcher::fetch($url);
        $pages = [self::page($first)];
        $issues = [];
        if (! $first['ok']) {
            return ['pages' => $pages, 'issues' => [['code' => $first['error'] === 'blocked' ? 'blocked' : 'fetch_failed', 'level' => 'critical', 'url' => $url]]];
        }

        $seen = [self::norm($first['url']) => 0];
        foreach ($pages[0]['links'] as $code => $alt) {
            if (count($pages) >= self::MAX_FETCHES) {
                break;
            }
            if (! isset($seen[self::norm($alt)])) {
                $seen[self::norm($alt)] = count($pages);
                $pages[] = self::page(SafeFetcher::fetch($alt));
            }
        }

        foreach ($pages as $i => $page) {
            if ($page['status'] !== 200) {
                $issues[] = ['code' => $page['error'] === 'blocked' ? 'blocked' : 'not_200', 'level' => 'critical', 'url' => $page['url']];

                continue;
            }
            foreach ($page['dupes'] as $code) {
                $issues[] = ['code' => 'duplicate_code', 'level' => 'critical', 'url' => $page['url'], 'value' => $code];
            }
            foreach (array_keys($page['links']) as $code) {
                if (! self::validCode($code)) {
                    $issues[] = ['code' => 'bad_code', 'level' => 'critical', 'url' => $page['url'], 'value' => $code];
                }
            }
            if ($page['links'] === []) {
                $issues[] = ['code' => 'no_hreflang', 'level' => 'critical', 'url' => $page['url']];

                continue;
            }
            if (! in_array(self::norm($page['url']), array_map(self::norm(...), $page['links']), true)) {
                $issues[] = ['code' => 'no_self', 'level' => 'warning', 'url' => $page['url']];
            }
            if (! isset($page['links']['x-default'])) {
                $issues[] = ['code' => 'no_x_default', 'level' => 'warning', 'url' => $page['url']];
            }
            if ($page['canonical'] !== '' && self::norm($page['canonical']) !== self::norm($page['url'])) {
                $issues[] = ['code' => 'canonical_elsewhere', 'level' => 'critical', 'url' => $page['url'], 'value' => $page['canonical']];
            }
            $myCode = array_search(self::norm($page['url']), array_map(self::norm(...), $page['links']), true);
            if (is_string($myCode) && str_starts_with(strtolower($myCode), 'ar')) {
                if (! str_starts_with(strtolower($page['lang']), 'ar')) {
                    $issues[] = ['code' => 'ar_no_lang', 'level' => 'critical', 'url' => $page['url']];
                }
                if (strtolower($page['dir']) !== 'rtl') {
                    $issues[] = ['code' => 'ar_no_rtl', 'level' => 'critical', 'url' => $page['url']];
                }
            }
        }

        // Return links: every page the first page lists must list the first page back.
        $firstUrl = self::norm($pages[0]['url']);
        foreach ($pages as $i => $page) {
            if ($i === 0 || $page['status'] !== 200 || $page['links'] === []) {
                continue;
            }
            if (! in_array($firstUrl, array_map(self::norm(...), $page['links']), true)) {
                $issues[] = ['code' => 'no_return', 'level' => 'critical', 'url' => $page['url'], 'value' => $pages[0]['url']];
            }
        }

        return ['pages' => array_map(fn ($p) => array_diff_key($p, ['dupes' => 1]), $pages), 'issues' => $issues];
    }

    /** ISO 639-1 language + optional ISO 3166-1 alpha-2 region with a hyphen, or x-default. "UK" is not a region code (GB). */
    public static function validCode(string $code): bool
    {
        if ($code === 'x-default') {
            return true;
        }
        if (! preg_match('/^([a-z]{2})(-([A-Za-z]{2}))?$/', $code, $m)) {
            return false;
        }

        return ! isset($m[3]) || strtoupper($m[3]) !== 'UK';
    }

    /** @param  array{ok: bool, status: ?int, url: string, html: string, error: ?string}  $fetch */
    private static function page(array $fetch): array
    {
        $page = ['url' => $fetch['url'], 'status' => $fetch['status'], 'error' => $fetch['error'], 'links' => [], 'dupes' => [], 'lang' => '', 'dir' => '', 'canonical' => ''];
        if ($fetch['html'] === '') {
            return $page;
        }
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$fetch['html'], LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $x = new DOMXPath($dom);
        $html = $x->query('//html')->item(0);
        $page['lang'] = $html?->getAttribute('lang') ?? '';
        $page['dir'] = $html?->getAttribute('dir') ?? '';
        foreach ($x->query('//head/link[@rel]') as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            $href = trim($link->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $href = SafeFetcher::absolute($fetch['url'], $href);
            if ($rel === 'canonical' && $page['canonical'] === '') {
                $page['canonical'] = $href;
            } elseif ($rel === 'alternate' && ($code = trim($link->getAttribute('hreflang'))) !== '') {
                if (isset($page['links'][$code])) {
                    $page['dupes'][] = $code;
                }
                $page['links'][$code] = $href;
            }
        }

        return $page;
    }

    private static function norm(string $url): string
    {
        return rtrim(strtolower(preg_replace('#^https?://#i', '', $url)), '/');
    }
}
