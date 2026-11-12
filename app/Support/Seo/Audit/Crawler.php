<?php

namespace App\Support\Seo\Audit;

use App\Support\Seo\SafeFetcher;
use Closure;

/**
 * Polite same-host crawl for A1 (910 BUILD START step 4): robots.txt (longest match, our user agent or *), one request per
 * interval, SafeFetcher (public IPs only, 2 MB, 5 s), max N pages, breadth-first from the site URL. Links to other hosts are not fetched.
 *
 * @phpstan-import-type Parsed from PageParser
 */
class Crawler
{
    /** @var list<string> */
    public array $disallow = [];

    /** @var list<string> */
    public array $allow = [];

    public bool $robotsRead = false;

    public function __construct(
        public readonly string $siteUrl,
        public readonly int $maxPages = 200,
        public readonly int $intervalMs = 1000,
        public ?Closure $sleeper = null,
    ) {}

    /** Reads robots.txt for AdsRangerBot (falls back to *). Returns false when the site root is disallowed. */
    public function readRobots(): bool
    {
        $p = parse_url($this->siteUrl);
        $robotsUrl = ($p['scheme'] ?? 'https').'://'.($p['host'] ?? '').(isset($p['port']) ? ':'.$p['port'] : '').'/robots.txt';
        $res = SafeFetcher::fetchResource($robotsUrl, ['plain', 'text', 'html']);
        $this->robotsRead = true;
        if (($res['status'] ?? null) !== 200 || trim($res['body']) === '') {
            return true;
        }
        $groups = [];
        $current = null;
        foreach (preg_split('/\R/', $res['body']) ?: [] as $raw) {
            $line = trim((string) preg_replace('/#.*$/', '', $raw));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);
            if ($field === 'user-agent') {
                $current = strtolower($value);
                $groups[$current] ??= ['allow' => [], 'disallow' => []];
            } elseif ($current !== null && in_array($field, ['allow', 'disallow'], true) && $value !== '') {
                $groups[$current][$field][] = $value;
            }
        }
        $ours = null;
        foreach ($groups as $agent => $rules) {
            if (str_contains($agent, 'adsrangerbot')) {
                $ours = $rules;
            }
        }
        $ours ??= $groups['*'] ?? ['allow' => [], 'disallow' => []];
        $this->allow = $ours['allow'];
        $this->disallow = $ours['disallow'];

        return $this->allowed($this->siteUrl);
    }

    public function allowed(string $url): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
        $longest = fn (array $rules): int => max(array_map(fn (string $r): int => str_starts_with($path, rtrim($r, '*')) ? strlen($r) : -1, $rules ?: ['']));
        $d = $this->disallow === [] ? -1 : $longest($this->disallow);
        $a = $this->allow === [] ? -1 : $longest($this->allow);

        return $d < 0 || $a >= $d;
    }

    /**
     * Crawls and calls $onPage(url, status, html, response_ms, finalUrl) for every fetched page, in order.
     * Returns url => status for every URL requested (crawled pages and link checks).
     *
     * @return array<string, ?int>
     */
    public function run(Closure $onPage, ?Closure $shouldStop = null): array
    {
        $start = PageParser::normalise($this->siteUrl);
        $queue = [$start];
        $seen = [$start => true];
        $status = [];
        $fetched = 0;
        while ($queue !== [] && $fetched < $this->maxPages) {
            if ($shouldStop && $shouldStop()) {
                break;
            }
            $url = array_shift($queue);
            if (! $this->allowed($url)) {
                continue;
            }
            $t0 = microtime(true);
            $res = SafeFetcher::fetch($url);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $fetched++;
            $finalUrl = PageParser::normalise((string) ($res['url'] ?? $url));
            $code = $res['status'] ?? null;
            if ($finalUrl !== $url) {
                // SafeFetcher followed a redirect: the requested URL counts as a redirect for the link check; the final page is audited once.
                $status[$url] = 301;
                if (isset($status[$finalUrl]) || ! PageParser::sameHost($finalUrl, $start)) {
                    $this->pause();

                    continue;
                }
                $seen[$finalUrl] = true;
            }
            $status[$finalUrl] = $code;
            $onPage($finalUrl, $code, (string) ($res['html'] ?? ''), $ms, $res['error'] ?? null);
            if ($code === 200 && ($res['html'] ?? '') !== '') {
                $parsed = PageParser::parse((string) $res['html'], $finalUrl);
                foreach ($parsed['links'] as $link) {
                    $n = PageParser::normalise($link);
                    if (isset($seen[$n]) || ! PageParser::sameHost($n, $start) || preg_match('/\.(jpe?g|png|gif|webp|svg|pdf|zip|mp4|mp3|css|js|ico|woff2?)(\?|$)/i', $n)) {
                        continue;
                    }
                    $seen[$n] = true;
                    $queue[] = $n;
                }
            }
            $this->pause();
        }

        return $status;
    }

    private function pause(): void
    {
        if ($this->sleeper) {
            ($this->sleeper)($this->intervalMs);
        } elseif ($this->intervalMs > 0) {
            usleep($this->intervalMs * 1000);
        }
    }
}
