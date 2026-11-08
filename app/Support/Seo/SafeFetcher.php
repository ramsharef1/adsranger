<?php

namespace App\Support\Seo;

use App\Support\LandingPages;
use Closure;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches one public web page for the SEO checks (free hreflang checker now, the A1 site audit later), the safe way
 * (PR-015 §2.4, D-239): http(s) on standard ports only; the host's every IP must be public (no private, loopback,
 * link-local or metadata ranges) and the request is pinned to that IP; each redirect hop is checked again, at most 3;
 * 5 s timeout, HTML only, at most 2 MB, no cookies, no JavaScript. Nothing is stored.
 */
class SafeFetcher
{
    public const MAX_REDIRECTS = 3;

    public const MAX_BYTES = 2_000_000;

    public const TIMEOUT = 5;

    public const USER_AGENT = 'AdsRangerHreflangChecker/1.0 (+https://adsranger.com/tools/hreflang-checker)';

    /** @var (Closure(string): ?string)|null resolves a host to one public IP or null; tests swap it. */
    public static ?Closure $resolver = null;

    /** @return array{ok: bool, status: ?int, url: string, html: string, error: ?string} */
    public static function fetch(string $url): array
    {
        $resolve = self::$resolver ?? fn (string $host) => self::ownHostIp($host) ?? LandingPages::publicIp($host);
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $p = parse_url($url);
            $scheme = strtolower($p['scheme'] ?? '');
            $host = strtolower($p['host'] ?? '');
            $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
            if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! in_array($port, [80, 443], true) || isset($p['user']) || isset($p['pass'])) {
                return self::fail($url, 'blocked');
            }
            $ip = $resolve($host);
            if (! $ip) {
                return self::fail($url, 'blocked');
            }
            try {
                $res = Http::withOptions([
                    'allow_redirects' => false,
                    'cookies' => false,
                    // Stop early on a declared or actual body over the limit, before it is all downloaded.
                    'on_headers' => function ($response) {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            throw new \RuntimeException('too_large');
                        }
                    },
                    'progress' => fn ($total, $downloaded) => $downloaded > self::MAX_BYTES ? throw new \RuntimeException('too_large') : null,
                    'curl' => [CURLOPT_RESOLVE => ["$host:$port:".(str_contains($ip, ':') ? "[$ip]" : $ip)]],
                ])->withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'text/html', 'Accept-Language' => 'ar,en;q=0.8'])
                    ->timeout(self::TIMEOUT)->connectTimeout(self::TIMEOUT)->get($url);
            } catch (Throwable) {
                return self::fail($url, 'unreachable');
            }
            if (in_array($res->status(), [301, 302, 303, 307, 308], true) && ($loc = $res->header('Location'))) {
                $url = self::absolute($url, $loc);

                continue;
            }
            $type = strtolower($res->header('Content-Type'));
            $body = (string) $res->body();
            if ($type !== '' && ! str_contains($type, 'html')) {
                return self::fail($url, 'not_html', $res->status());
            }
            if (strlen($body) > self::MAX_BYTES) {
                return self::fail($url, 'too_large', $res->status());
            }

            return ['ok' => $res->status() === 200, 'status' => $res->status(), 'url' => $url, 'html' => $body, 'error' => $res->status() === 200 ? null : 'status'];
        }

        return self::fail($url, 'redirects');
    }

    /** Our own hosts (config seo_tools.own_hosts) map to a fixed IP, used only if that IP is itself public. */
    public static function ownHostIp(string $host): ?string
    {
        $ip = config('seo_tools.own_hosts')[strtolower($host)] ?? null;

        return $ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? $ip : null;
    }

    /** @return array{ok: bool, status: ?int, url: string, html: string, error: ?string} */
    private static function fail(string $url, string $error, ?int $status = null): array
    {
        return ['ok' => false, 'status' => $status, 'url' => $url, 'html' => '', 'error' => $error];
    }

    public static function absolute(string $base, string $loc): string
    {
        if (preg_match('#^https?://#i', $loc)) {
            return $loc;
        }
        $p = parse_url($base);
        $root = $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
        if (str_starts_with($loc, '//')) {
            return $p['scheme'].':'.$loc;
        }
        if (str_starts_with($loc, '/')) {
            return $root.$loc;
        }
        $dir = preg_replace('#/[^/]*$#', '/', $p['path'] ?? '/');

        return $root.$dir.$loc;
    }
}
