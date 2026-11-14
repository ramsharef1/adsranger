<?php

namespace App\Support\Attribution;

use App\Models\Client;
use App\Models\WatchedLandingUrl;
use App\Models\WatchedUrlCheck;
use App\Support\Seo\SafeFetcher;

class LandingUrlWatchdog
{
    /** @var array<string, list<string>> */
    public const PIXEL_SIGNATURES = [
        'meta_pixel' => ['fbq(', 'connect.facebook.net'],
        'google_tag' => ['gtag(', 'googletagmanager.com/gtag', 'googletagmanager.com/gtm.js'],
        'tiktok_pixel' => ['ttq.', 'analytics.tiktok.com'],
        'snap_pixel' => ['snaptr(', 'sc-static.net/scevent.min.js'],
    ];

    /**
     * @param  list<string>  $expectedPixels
     */
    public static function watchUrl(Client $client, string $url, array $expectedPixels = ['meta_pixel', 'google_tag']): WatchedLandingUrl
    {
        $validPixels = array_values(array_intersect($expectedPixels, array_keys(self::PIXEL_SIGNATURES)));

        return WatchedLandingUrl::updateOrCreate(
            [
                'agency_id' => $client->agency_id,
                'client_id' => $client->id,
                'url' => trim($url),
            ],
            [
                'expected_pixels' => $validPixels,
            ]
        );
    }

    public static function check(WatchedLandingUrl $watched): WatchedUrlCheck
    {
        $res = SafeFetcher::fetchResource($watched->url, ['html', 'text']);
        $status = $res['status'];
        $hops = (int) ($res['redirects'] ?? 0);
        $html = (string) ($res['body'] ?? '');
        $xRobots = (string) ($res['x_robots_tag'] ?? '');
        $expected = is_array($watched->expected_pixels) ? $watched->expected_pixels : [];

        $hasNoindex = self::detectNoindex($html, $xRobots);
        $missingPixels = ($res['ok'] ?? false)
            ? self::detectMissingPixels($html, $expected)
            : array_values($expected);
        $brokenWhatsapp = ($res['ok'] ?? false) && self::detectBrokenWhatsappLinks($html);

        $now = now();
        $watched->update([
            'last_http_status' => $status,
            'redirect_hops' => $hops,
            'has_noindex' => $hasNoindex,
            'missing_pixels' => $missingPixels,
            'broken_whatsapp_link' => $brokenWhatsapp,
            'checked_at' => $now,
        ]);

        return WatchedUrlCheck::create([
            'agency_id' => $watched->agency_id,
            'client_id' => $watched->client_id,
            'watched_url_id' => $watched->id,
            'http_status' => $status,
            'redirect_hops' => $hops,
            'has_noindex' => $hasNoindex,
            'missing_pixels' => $missingPixels,
            'broken_whatsapp_link' => $brokenWhatsapp,
            'error_code' => $res['error'] ?? null,
            'checked_at' => $now,
        ]);
    }

    public static function detectNoindex(string $html, string $xRobotsHeader = ''): bool
    {
        if ($xRobotsHeader !== '' && stripos($xRobotsHeader, 'noindex') !== false) {
            return true;
        }
        if ($html === '') {
            return false;
        }
        if (preg_match_all('/<meta\b[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/\bname\s*=\s*["\']?(?:robots|googlebot)["\']?/i', $tag)
                    && preg_match('/\bcontent\s*=\s*["\'][^"\']*noindex/i', $tag)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $expectedPixels
     * @return list<string>
     */
    public static function detectMissingPixels(string $html, array $expectedPixels): array
    {
        $lower = strtolower($html);
        $missing = [];
        foreach ($expectedPixels as $pixelKey) {
            $patterns = self::PIXEL_SIGNATURES[$pixelKey] ?? [];
            if ($patterns === []) {
                continue;
            }
            $found = false;
            foreach ($patterns as $needle) {
                if (str_contains($lower, strtolower($needle))) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $missing[] = $pixelKey;
            }
        }

        return $missing;
    }

    public static function detectBrokenWhatsappLinks(string $html): bool
    {
        if (! preg_match_all('/<a\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            return false;
        }

        foreach ($matches[1] as $href) {
            $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $lower = strtolower($href);

            $isWaMe = str_contains($lower, 'wa.me');
            $isApiWa = str_contains($lower, 'api.whatsapp.com');
            $isWaScheme = str_starts_with($lower, 'whatsapp://');

            if (! $isWaMe && ! $isApiWa && ! $isWaScheme) {
                continue;
            }

            if (str_contains($lower, 'undefined') || str_contains($lower, 'null') || str_contains($href, '{')) {
                return true;
            }

            $phone = '';
            if ($isWaMe) {
                $path = trim((string) parse_url($href, PHP_URL_PATH), '/');
                // Allow wa.me/message/CODE business shortlinks when CODE is non-empty alphanumeric
                if (str_starts_with(strtolower($path), 'message/')) {
                    $code = substr($path, 8);
                    if ($code === '' || ! preg_match('/^[A-Za-z0-9_-]{4,}$/', $code)) {
                        return true;
                    }

                    continue;
                }
                $phone = $path;
            } else {
                parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
                $phone = (string) ($query['phone'] ?? '');
            }

            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15 || preg_match('/^0+$/', $digits)) {
                return true;
            }
        }

        return false;
    }
}
