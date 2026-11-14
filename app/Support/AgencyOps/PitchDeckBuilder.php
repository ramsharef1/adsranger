<?php

namespace App\Support\AgencyOps;

use App\Models\Agency;
use App\Models\ProspectPitchDeck;
use App\Support\Attribution\LandingUrlWatchdog;
use App\Support\Seo\SafeFetcher;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

class PitchDeckBuilder
{
    public const ALL_PIXELS = ['meta_pixel', 'google_tag', 'tiktok_pixel', 'snap_pixel'];

    /**
     * Generates a 60-second agency-branded prospect pitch deck combining a SafeFetcher
     * technical/speed/pixel/AEO audit snapshot with a 3-tier retainer proposal.
     *
     * @param  list<array{tier_key: string, label_en: string, label_ar: string, monthly_fee: float, deliverables_en: list<string>, deliverables_ar: list<string>}>|null  $customTiers
     */
    public static function generate(
        Agency $agency,
        string $prospectName,
        string $prospectUrl,
        string $currency = 'SAR',
        ?array $customTiers = null
    ): ProspectPitchDeck {
        $cleanUrl = trim($prospectUrl);
        $snapshot = self::auditProspectUrl($cleanUrl);
        $tiers = $customTiers ?? self::defaultThreeTierQuote($currency);

        return ProspectPitchDeck::create([
            'agency_id' => $agency->id,
            'prospect_name' => trim($prospectName),
            'prospect_url' => $cleanUrl,
            'currency' => strtoupper(trim($currency)) ?: 'SAR',
            'share_token' => Str::random(48),
            'audit_snapshot' => $snapshot,
            'tier_quotes' => $tiers,
        ]);
    }

    /**
     * @return array{
     *   reachable: bool,
     *   http_status: ?int,
     *   https: bool,
     *   redirect_hops: int,
     *   has_title: bool,
     *   has_meta_description: bool,
     *   has_viewport: bool,
     *   render_blocking_scripts: int,
     *   detected_pixels: list<string>,
     *   missing_pixels: list<string>,
     *   has_json_ld: bool,
     *   has_llms_txt: bool,
     *   checked_at: string
     * }
     */
    public static function auditProspectUrl(string $url): array
    {
        $res = SafeFetcher::fetchResource($url, ['html', 'text']);
        $html = (string) ($res['body'] ?? '');
        $ok = (bool) ($res['ok'] ?? false);

        $missingPixels = $ok
            ? LandingUrlWatchdog::detectMissingPixels($html, self::ALL_PIXELS)
            : self::ALL_PIXELS;
        $detectedPixels = array_values(array_diff(self::ALL_PIXELS, $missingPixels));

        $hasTitle = $ok && (bool) preg_match('/<title\b[^>]*>\s*\S+/is', $html);
        $hasDesc = $ok && (bool) preg_match('/<meta\b[^>]*\bname\s*=\s*["\']?description["\']?[^>]*\bcontent\s*=\s*["\'][^"\']+["\']/i', $html);
        $hasViewport = $ok && (bool) preg_match('/<meta\b[^>]*\bname\s*=\s*["\']?viewport["\']?/i', $html);
        $hasJsonLd = $ok && str_contains(strtolower($html), 'application/ld+json');

        $blockingScripts = 0;
        if ($ok && preg_match('/<head\b[^>]*>(.*?)<\/head>/is', $html, $headMatch)) {
            if (preg_match_all('/<script\b[^>]*\bsrc\s*=\s*["\'][^"\']+["\'][^>]*>/i', $headMatch[1], $scripts)) {
                foreach ($scripts[0] as $tag) {
                    if (! preg_match('/\b(?:defer|async|type\s*=\s*["\']?module)\b/i', $tag)) {
                        $blockingScripts++;
                    }
                }
            }
        }

        $hasLlmsTxt = false;
        $parts = parse_url($url);
        if ($ok && ! empty($parts['scheme']) && ! empty($parts['host'])) {
            $root = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
            $llmsRes = SafeFetcher::fetchResource($root.'/llms.txt', ['plain', 'text']);
            $hasLlmsTxt = (bool) ($llmsRes['ok'] ?? false) && strlen(trim((string) ($llmsRes['body'] ?? ''))) > 10;
        }

        return [
            'reachable' => $ok,
            'http_status' => $res['status'],
            'https' => str_starts_with(strtolower($url), 'https://'),
            'redirect_hops' => (int) ($res['redirects'] ?? 0),
            'has_title' => $hasTitle,
            'has_meta_description' => $hasDesc,
            'has_viewport' => $hasViewport,
            'render_blocking_scripts' => $blockingScripts,
            'detected_pixels' => $detectedPixels,
            'missing_pixels' => $missingPixels,
            'has_json_ld' => $hasJsonLd,
            'has_llms_txt' => $hasLlmsTxt,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return list<array{
     *   tier_key: string,
     *   label_en: string,
     *   label_ar: string,
     *   monthly_fee: float,
     *   deliverables_en: list<string>,
     *   deliverables_ar: list<string>
     * }>
     */
    public static function defaultThreeTierQuote(string $currency = 'SAR'): array
    {
        $threeDecimal = in_array(strtoupper($currency), ['KWD', 'BHD', 'OMR', 'JOD'], true);
        $base = $threeDecimal ? [450.0, 950.0, 1800.0] : [4500.0, 9500.0, 18000.0];

        return [
            [
                'tier_key' => 'foundation',
                'label_en' => 'Foundation Retainer',
                'label_ar' => 'باقة التأسيس الشهري',
                'monthly_fee' => $base[0],
                'deliverables_en' => [
                    'Pixel & server-side event setup across core ad channels',
                    'Technical search & mobile speed cleanup',
                    'Monthly performance reporting',
                ],
                'deliverables_ar' => [
                    'إعداد وسوم التتبع وأحداث الخادم للقنوات الإعلانية الأساسية',
                    'معالجة أساسيات البحث والسرعة على الجوال',
                    'تقرير أداء شهري واضح',
                ],
            ],
            [
                'tier_key' => 'growth',
                'label_en' => 'Growth & Attribution Retainer',
                'label_ar' => 'باقة النمو والإسناد الربحي',
                'monthly_fee' => $base[1],
                'deliverables_en' => [
                    'Full-funnel paid search & paid social management',
                    'SKU profit-quadrant shopping feeds & negative stem mining',
                    'Bi-weekly creative refresh & landing page checks',
                ],
                'deliverables_ar' => [
                    'إدارة شاملة لحملات البحث والمنصات الاجتماعية المدفوعة',
                    'خلاصات تسوق مصنّفة بالربحية وتنقية عبارات البحث المهدرة',
                    'تحديث إبداعي نصف شهري ومراقبة مستمرة لصفحات الهبوط',
                ],
            ],
            [
                'tier_key' => 'scale',
                'label_en' => 'Full-Scale Revenue Partner',
                'label_ar' => 'باقة التوسع الشامل والشراكة',
                'monthly_fee' => $base[2],
                'deliverables_en' => [
                    'Cross-channel media, SEO topical clusters & AI answer readiness',
                    'Creator partnership POAS tracking & offline lead-to-sale loop',
                    'Dedicated weekly strategy review',
                ],
                'deliverables_ar' => [
                    'إدارة القنوات الإعلانية والعناقيد الموضوعية وجاهزية محركات الإجابة',
                    'قياس صافي ربحية صناع المحتوى وربط المبيعات الميدانية',
                    'اجتماع تخطيط استراتيجي أسبوعي مخصص',
                ],
            ],
        ];
    }

    public static function renderPdf(ProspectPitchDeck $deck, string $lang = 'both'): string
    {
        $deck->loadMissing('agency');
        $lang = in_array($lang, ['ar', 'en', 'both'], true) ? $lang : 'both';

        $html = view('reports.pitch-deck-pdf', [
            'deck' => $deck,
            'agency' => $deck->agency,
            'lang' => $lang,
        ])->render();

        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tmp,
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'default_font' => 'dejavusans',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'directionality' => $lang === 'en' ? 'ltr' : 'rtl',
        ]);

        $agencyName = (string) ($deck->agency?->name ?: 'Agency');
        $mpdf->SetTitle($deck->prospect_name.' — '.$agencyName);
        $mpdf->SetAuthor($agencyName);
        $mpdf->SetCreator($agencyName);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
