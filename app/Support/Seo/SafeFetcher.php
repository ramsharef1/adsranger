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

    public const USER_AGENT = 'AdsRangerBot/1.0 (+https://adsranger.com/bot)';

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

            return ['ok' => $res->status() === 200, 'status' => $res->status(), 'url' => $url, 'html' => self::toUtf8($body, $type), 'error' => $res->status() === 200 ? null : 'status'];
        }

        return self::fail($url, 'redirects');
    }

    /**
     * Same strict SSRF guard (public IP only, pinned per hop, <= 3 redirects, 5s timeout, <= 2MB, no cookies)
     * for plain-text / XML resources such as /robots.txt, /llms.txt, and XML sitemaps (#293, #300).
     *
     * @param  list<string>  $allowedTypes
     * @return array{ok: bool, status: ?int, url: string, body: string, content_type: string, x_robots_tag?: string, redirects?: int, error: ?string}
     */
    public static function fetchResource(string $url, array $allowedTypes = ['html', 'plain', 'xml', 'text']): array
    {
        $resolve = self::$resolver ?? fn (string $host) => self::ownHostIp($host) ?? LandingPages::publicIp($host);
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $p = parse_url($url);
            $scheme = strtolower($p['scheme'] ?? '');
            $host = strtolower($p['host'] ?? '');
            $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
            if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! in_array($port, [80, 443], true) || isset($p['user']) || isset($p['pass'])) {
                return ['ok' => false, 'status' => null, 'url' => $url, 'body' => '', 'content_type' => '', 'redirects' => $hop, 'error' => 'blocked'];
            }
            $ip = $resolve($host);
            if (! $ip) {
                return ['ok' => false, 'status' => null, 'url' => $url, 'body' => '', 'content_type' => '', 'redirects' => $hop, 'error' => 'blocked'];
            }
            try {
                $res = Http::withOptions([
                    'allow_redirects' => false,
                    'cookies' => false,
                    'on_headers' => function ($response): void {
                        if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                            throw new \RuntimeException('too_large');
                        }
                    },
                    'progress' => fn ($total, $downloaded) => $downloaded > self::MAX_BYTES ? throw new \RuntimeException('too_large') : null,
                    'curl' => [CURLOPT_RESOLVE => ["$host:$port:".(str_contains($ip, ':') ? "[$ip]" : $ip)]],
                ])->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/plain, application/xml, text/xml, text/html;q=0.9',
                    'Accept-Language' => 'ar,en;q=0.8',
                ])->timeout(self::TIMEOUT)->connectTimeout(self::TIMEOUT)->get($url);
            } catch (Throwable) {
                return ['ok' => false, 'status' => null, 'url' => $url, 'body' => '', 'content_type' => '', 'redirects' => $hop, 'error' => 'unreachable'];
            }
            if (in_array($res->status(), [301, 302, 303, 307, 308], true) && ($loc = $res->header('Location'))) {
                $url = self::absolute($url, $loc);

                continue;
            }
            $type = strtolower((string) $res->header('Content-Type'));
            $body = (string) $res->body();
            if ($type !== '') {
                $matched = false;
                foreach ($allowedTypes as $sub) {
                    if (str_contains($type, $sub)) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    return ['ok' => false, 'status' => $res->status(), 'url' => $url, 'body' => '', 'content_type' => $type, 'redirects' => $hop, 'error' => 'unsupported_type'];
                }
            }
            if (strlen($body) > self::MAX_BYTES) {
                return ['ok' => false, 'status' => $res->status(), 'url' => $url, 'body' => '', 'content_type' => $type, 'redirects' => $hop, 'error' => 'too_large'];
            }

            return [
                'ok' => $res->status() === 200,
                'status' => $res->status(),
                'url' => $url,
                'body' => self::toUtf8($body, $type),
                'content_type' => $type,
                'x_robots_tag' => trim((string) $res->header('X-Robots-Tag')), // AEO crawler check (#305)
                'redirects' => $hop,
                'error' => $res->status() === 200 ? null : 'status',
            ];
        }

        return ['ok' => false, 'status' => null, 'url' => $url, 'body' => '', 'content_type' => '', 'redirects' => self::MAX_REDIRECTS + 1, 'error' => 'redirects'];
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

    /**
     * Pages in the region are still served in windows-1256 / ISO-8859-6. Everything we parse, store (PostgreSQL rejects invalid
     * UTF-8) or return as JSON must be valid UTF-8: convert from the declared charset (header, then <meta>), else drop bad bytes.
     */
    public static function toUtf8(string $body, string $contentType = ''): string
    {
        if ($body === '' || mb_check_encoding($body, 'UTF-8')) {
            return $body;
        }
        $charset = '';
        if (preg_match('/charset=["\']?([A-Za-z0-9_\-]+)/i', $contentType, $m)) {
            $charset = $m[1];
        } elseif (preg_match('/<meta[^>]+charset=["\']?([A-Za-z0-9_\-]+)/i', substr($body, 0, 4096), $m)) {
            $charset = $m[1];
        }
        $charset = strtoupper($charset);
        $map = ['WINDOWS-1256' => 'CP1256', 'CP1256' => 'CP1256', 'ISO-8859-6' => 'ISO-8859-6', 'WINDOWS-1252' => 'CP1252', 'ISO-8859-1' => 'CP1252'];
        if (isset($map[$charset]) && function_exists('iconv')) {
            $converted = @iconv($map[$charset], 'UTF-8//IGNORE', $body);
            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return mb_scrub($body, 'UTF-8');
    }
}
