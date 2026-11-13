<?php

namespace App\Support;

use App\Models\AdAccount;
use App\Models\LandingPage;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Landing page health (#49). URLs come from Google (landing_page_view), Meta ad creatives, or are added by hand.
 * The checker only fetches public http(s) hosts: every hop's IP is resolved, private / reserved ranges are refused,
 * and the connection is pinned to the checked IP (no DNS rebinding).
 */
class LandingPages
{
    public const TRACKING = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'ttclid', 'msclkid', 'sccid'];

    /** Strip tracking parameters and fragments so the same page is stored once. Null when not a valid http(s) URL. */
    public static function normalize(string $url): ?string
    {
        $url = trim($url);
        if (str_contains($url, '{')) { // ValueTrack / dynamic placeholders
            $url = preg_replace('/\{[^}]*\}/', '', $url);
        }
        $p = parse_url($url);
        if (! $p || ! in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }
        parse_str($p['query'] ?? '', $q);
        $q = array_diff_key($q, array_flip(self::TRACKING));
        ksort($q);
        $out = strtolower($p['scheme']).'://'.strtolower($p['host']).(isset($p['port']) ? ':'.$p['port'] : '').($p['path'] ?? '/');
        if ($q) {
            $out .= '?'.http_build_query($q);
        }

        return mb_substr($out, 0, 1000);
    }

    /** @param array<string,int>|array<int,string> $urls url => clicks, or a list of urls */
    public static function register(AdAccount $a, array $urls, string $source): int
    {
        $n = 0;
        foreach ($urls as $k => $v) {
            [$raw, $clicks] = is_int($k) ? [$v, 0] : [$k, (int) $v];
            $url = static::normalize((string) $raw);
            if (! $url) {
                continue;
            }
            $lp = LandingPage::firstOrNew(['agency_id' => $a->agency_id, 'url_hash' => sha1($url)]);
            $lp->fill(['url' => $url, 'client_id' => $lp->client_id ?: $a->client_id, 'source' => $lp->exists ? $lp->source : $source, 'last_seen_at' => now()]);
            if ($clicks || ! $lp->exists) {
                $lp->clicks_30d = max($clicks, (int) $lp->clicks_30d);
            }
            $lp->save();
            $n++;
        }

        return $n;
    }

    /** Public IPv4 / IPv6 only. */
    public static function publicIp(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = array_merge(gethostbynamel($host) ?: [], array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));
        }
        if (! $ips) {
            return null;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null; // any private answer = refuse the host
            }
        }

        return $ips[0];
    }

    public static function check(LandingPage $lp): LandingPage
    {
        $v = static::probe($lp->url);

        return static::save($lp, $v + ($v['status_code'] === null ? [] : static::pagespeed($lp)));
    }

    /**
     * Fetch a public http(s) URL the safe way (see the class note) and grade it. Nothing is stored.
     * Used by the nightly checks (via check()) and by the UTM builder's link check (#86).
     *
     * @return array{state: string, status_code: ?int, error: ?string, ms?: int, bytes?: int, redirects?: int, final_url?: string}
     */
    public static function probe(string $url): array
    {
        $redirects = 0;
        $start = microtime(true);
        $res = null;
        $error = null;
        try {
            for ($hop = 0; $hop <= 5; $hop++) {
                $p = parse_url($url);
                $scheme = strtolower($p['scheme'] ?? '');
                $host = $p['host'] ?? '';
                $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
                if (! in_array($scheme, ['http', 'https'], true) || ! in_array($port, [80, 443], true)) {
                    throw new \RuntimeException('Only http and https on standard ports are checked.');
                }
                $ip = static::publicIp($host);
                if (! $ip) {
                    return ['state' => 'blocked', 'error' => 'The host does not resolve to a public address.', 'status_code' => null];
                }
                $res = Http::withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => ["$host:$port:".(str_contains($ip, ':') ? "[$ip]" : $ip)]]])
                    ->withHeaders(['User-Agent' => 'AdsRangerBot/1.0 (+https://adsranger.com/bot)', 'Accept-Language' => 'ar,en;q=0.8'])
                    ->timeout(15)->connectTimeout(8)->get($url);
                if (in_array($res->status(), [301, 302, 303, 307, 308], true) && ($loc = $res->header('Location'))) {
                    $url = str_starts_with($loc, 'http') ? $loc : static::absolute($url, $loc);
                    $redirects++;

                    continue;
                }
                break;
            }
        } catch (Throwable $e) {
            $error = mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 290);
        }
        $ms = (int) round((microtime(true) - $start) * 1000);
        if (! $res) {
            return ['state' => 'broken', 'error' => $error ?: 'No response.', 'ms' => $ms, 'status_code' => null, 'redirects' => $redirects];
        }
        $code = $res->status();
        $final = $url;
        $state = match (true) {
            $code >= 400 || $code === 0 => 'broken',
            $redirects > 5 || in_array($code, [301, 302, 303, 307, 308], true) => 'redirects',
            ! str_starts_with($final, 'https://') => 'insecure',
            $ms > (int) config('insights.landing.slow_ms', 3000) => 'slow',
            $redirects > 2 => 'redirects',
            default => 'ok',
        };

        return ['state' => $state, 'status_code' => $code, 'ms' => $ms, 'bytes' => strlen((string) $res->body()), 'redirects' => $redirects,
            'final_url' => mb_substr($final, 0, 1000), 'error' => $state === 'broken' ? 'HTTP '.$code : null];
    }

    protected static function absolute(string $base, string $loc): string
    {
        $p = parse_url($base);

        return $p['scheme'].'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '').(str_starts_with($loc, '/') ? $loc : '/'.$loc);
    }

    /** Optional Google PageSpeed Insights mobile score, at most once a week per page, when a key is configured. */
    protected static function pagespeed(LandingPage $lp): array
    {
        $key = config('insights.landing.pagespeed_key');
        if (! $key || ($lp->psi_score !== null && $lp->checked_at && $lp->checked_at->gt(now()->subDays(7)))) {
            return [];
        }
        try {
            $r = Http::timeout(60)->get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed', ['url' => $lp->url, 'strategy' => 'mobile', 'category' => 'performance', 'key' => $key]);
            $s = $r->json('lighthouseResult.categories.performance.score');

            return is_numeric($s) ? ['psi_score' => (int) round($s * 100)] : [];
        } catch (Throwable) {
            return [];
        }
    }

    protected static function save(LandingPage $lp, array $v): LandingPage
    {
        // DB-01: keep the time of the first failed check, so the alert can say how long ads have pointed at a dead page.
        $down = static::isDown($v['state'] ?? $lp->state, $lp->url, $v['final_url'] ?? null);
        $v['down_since'] = $down ? ($lp->down_since ?? now()) : null;
        $lp->forceFill($v + ['checked_at' => now()])->save();

        return $lp;
    }

    /** Broken, or a redirect that lands on another site (DB-01). */
    public static function isDown(string $state, string $url, ?string $finalUrl): bool
    {
        if ($state === 'broken') {
            return true;
        }
        if ($finalUrl === null || $finalUrl === '') {
            return false;
        }
        $host = fn (string $u) => preg_replace('/^www\./', '', strtolower((string) parse_url($u, PHP_URL_HOST)));

        return $host($finalUrl) !== '' && $host($finalUrl) !== $host($url);
    }

    public static function stateLabel(string $s): array
    {
        return ['ok' => ['OK', 'success'], 'slow' => ['Slow', 'warning'], 'redirects' => ['Too many redirects', 'warning'], 'insecure' => ['Not HTTPS', 'warning'],
            'broken' => ['Broken', 'danger'], 'blocked' => ['Not checkable', 'gray'], 'unchecked' => ['Not checked yet', 'gray']][$s] ?? [$s, 'gray'];
    }
}
