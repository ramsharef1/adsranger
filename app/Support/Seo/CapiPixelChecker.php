<?php

namespace App\Support\Seo;

/**
 * #289 Public CAPI & Ad Pixel Checker: inspects public page HTML/scripts for client-side ad tags and ecommerce hooks.
 * D-229 Honesty: explicitly states that server-to-server CAPI events cannot be verified from public HTML alone.
 */
class CapiPixelChecker
{
    /**
     * @return array{
     *   detected: list<array{key: string, label: string, ids: list<string>}>,
     *   missing_basics: list<string>,
     *   ecommerce_platform: ?string,
     *   has_datalayer: bool,
     *   has_noscript: bool
     * }
     */
    public static function inspectHtml(string $html): array
    {
        $detected = [];

        // 1. Meta Pixel
        $metaIds = [];
        if (preg_match_all("/fbq\s*\(\s*['\"]init['\"]\s*,\s*['\"](\d{8,22})['\"]/i", $html, $m)) {
            $metaIds = array_values(array_unique($m[1]));
        }
        if ($metaIds || stripos($html, 'connect.facebook.net') !== false || stripos($html, 'facebook.com/tr?') !== false) {
            $detected[] = ['key' => 'meta_pixel', 'label' => 'Meta Pixel', 'ids' => $metaIds];
        }

        // 2. Google Tag Manager
        $gtmIds = [];
        if (preg_match_all('/\b(GTM-[A-Z0-9]{4,12})\b/', $html, $m)) {
            $gtmIds = array_values(array_unique($m[1]));
        }
        if ($gtmIds || stripos($html, 'googletagmanager.com/gtm.js') !== false) {
            $detected[] = ['key' => 'gtm', 'label' => 'Google Tag Manager', 'ids' => $gtmIds];
        }

        // 3. Google Analytics 4
        $ga4Ids = [];
        if (preg_match_all('/\b(G-[A-Z0-9]{6,14})\b/', $html, $m)) {
            $ga4Ids = array_values(array_unique($m[1]));
        }
        if ($ga4Ids) {
            $detected[] = ['key' => 'ga4', 'label' => 'Google Analytics 4 (GA4)', 'ids' => $ga4Ids];
        }

        // 4. Google Ads Tag
        $awIds = [];
        if (preg_match_all('/\b(AW-\d{6,14})\b/', $html, $m)) {
            $awIds = array_values(array_unique($m[1]));
        }
        if ($awIds || stripos($html, 'googleadservices.com/pagead/conversion') !== false) {
            $detected[] = ['key' => 'google_ads', 'label' => 'Google Ads Conversion Tag', 'ids' => $awIds];
        }

        // 5. TikTok Pixel
        $ttIds = [];
        if (preg_match_all("/ttq\.load\s*\(\s*['\"]([A-Z0-9]{10,28})['\"]/i", $html, $m)) {
            $ttIds = array_values(array_unique($m[1]));
        }
        if ($ttIds || stripos($html, 'analytics.tiktok.com') !== false) {
            $detected[] = ['key' => 'tiktok_pixel', 'label' => 'TikTok Pixel', 'ids' => $ttIds];
        }

        // 6. Snap Pixel
        $snapIds = [];
        if (preg_match_all("/snaptr\s*\(\s*['\"]init['\"]\s*,\s*['\"]([a-f0-9-]{16,40})['\"]/i", $html, $m)) {
            $snapIds = array_values(array_unique($m[1]));
        }
        if ($snapIds || stripos($html, 'sc-static.net/scevent.min.js') !== false) {
            $detected[] = ['key' => 'snap_pixel', 'label' => 'Snap Pixel', 'ids' => $snapIds];
        }

        // 7. LinkedIn Insight Tag
        $liIds = [];
        if (preg_match_all("/_linkedin_partner_id\s*=\s*['\"](\d{4,14})['\"]/i", $html, $m)) {
            $liIds = array_values(array_unique($m[1]));
        }
        if ($liIds || stripos($html, 'snap.licdn.com/li.lms-analytics') !== false) {
            $detected[] = ['key' => 'linkedin_insight', 'label' => 'LinkedIn Insight Tag', 'ids' => $liIds];
        }

        // 8. Salla / Zid MENA e-commerce hooks
        $ecom = null;
        if (stripos($html, 'salla.event') !== false || stripos($html, 'cdn.salla.network') !== false || stripos($html, 'salla.sa') !== false) {
            $ecom = 'salla';
            $detected[] = ['key' => 'salla_hooks', 'label' => 'Salla Storefront Tracking Hooks', 'ids' => []];
        } elseif (stripos($html, 'zid.store') !== false || stripos($html, 'media.zid.store') !== false || stripos($html, 'window.zid') !== false) {
            $ecom = 'zid';
            $detected[] = ['key' => 'zid_hooks', 'label' => 'Zid Storefront Tracking Hooks', 'ids' => []];
        }

        $hasDataLayer = preg_match('/\bdataLayer\b/', $html) === 1;
        $hasNoscript = preg_match('/<noscript\b[^>]*>.*?(googletagmanager\.com\/ns\.html|facebook\.com\/tr\?).*?<\/noscript>/is', $html) === 1;

        $keys = array_column($detected, 'key');
        $missing = [];
        if ((in_array('gtm', $keys, true) || in_array('ga4', $keys, true) || in_array('google_ads', $keys, true)) && ! $hasDataLayer) {
            $missing[] = 'missing_datalayer';
        }
        if ((in_array('gtm', $keys, true) || in_array('meta_pixel', $keys, true)) && ! $hasNoscript) {
            $missing[] = 'missing_noscript';
        }
        if ($detected === []) {
            $missing[] = 'no_tags_detected';
        }

        return [
            'detected' => $detected,
            'missing_basics' => $missing,
            'ecommerce_platform' => $ecom,
            'has_datalayer' => $hasDataLayer,
            'has_noscript' => $hasNoscript,
        ];
    }
}
