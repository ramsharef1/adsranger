<?php

namespace App\Support\Seo\Audit;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Optional Google PageSpeed Insights v5 client for site audit A2 (E2-062). Runs only when services.pagespeed.key is set.
 * Returns null on any failure: no number is ever invented. Field data (real Chrome users, loadingExperience) wins over lab
 * data for LCP / CLS / TTFB; INP exists only as field data, so it stays null when Google has none for the page.
 */
class PageSpeed
{
    public const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    public static function configured(): bool
    {
        return filled(config('services.pagespeed.key'));
    }

    /** @return array{psi_mobile_score: ?int, lcp_ms: ?int, inp_ms: ?int, cls_score: ?float, ttfb_ms: ?int}|null */
    public static function run(string $url): ?array
    {
        if (! self::configured()) {
            return null;
        }
        try {
            $response = Http::timeout((int) config('seo_audit.speed.psi_timeout_s', 60))->get(self::ENDPOINT, [
                'url' => $url,
                'strategy' => 'mobile',
                'category' => 'performance',
                'key' => config('services.pagespeed.key'),
            ]);
            if (! $response->successful()) {
                return null;
            }

            return self::parse((array) $response->json());
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{psi_mobile_score: ?int, lcp_ms: ?int, inp_ms: ?int, cls_score: ?float, ttfb_ms: ?int}|null
     */
    public static function parse(array $json): ?array
    {
        $score = data_get($json, 'lighthouseResult.categories.performance.score');
        $field = (array) data_get($json, 'loadingExperience.metrics', []);
        $lab = (array) data_get($json, 'lighthouseResult.audits', []);
        $num = fn (mixed $v): ?float => is_numeric($v) ? (float) $v : null;

        $lcp = $num(data_get($field, 'LARGEST_CONTENTFUL_PAINT_MS.percentile')) ?? $num(data_get($lab, 'largest-contentful-paint.numericValue'));
        $inp = $num(data_get($field, 'INTERACTION_TO_NEXT_PAINT.percentile'));
        // CrUX reports CLS × 100 as an integer percentile; Lighthouse reports the raw value.
        $clsField = $num(data_get($field, 'CUMULATIVE_LAYOUT_SHIFT_SCORE.percentile'));
        $cls = $clsField !== null ? $clsField / 100 : $num(data_get($lab, 'cumulative-layout-shift.numericValue'));
        $ttfb = $num(data_get($field, 'EXPERIMENTAL_TIME_TO_FIRST_BYTE.percentile')) ?? $num(data_get($lab, 'server-response-time.numericValue'));

        $out = [
            'psi_mobile_score' => is_numeric($score) ? (int) round($score * 100) : null,
            'lcp_ms' => $lcp !== null ? (int) round($lcp) : null,
            'inp_ms' => $inp !== null ? (int) round($inp) : null,
            'cls_score' => $cls !== null ? round($cls, 3) : null,
            'ttfb_ms' => $ttfb !== null ? (int) round($ttfb) : null,
        ];

        return array_filter($out, fn ($v) => $v !== null) === [] ? null : $out;
    }
}
