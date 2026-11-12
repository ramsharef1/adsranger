<?php

namespace App\Support;

use App\Http\Controllers\PublicSiteController;

/**
 * Per-page share images (R2-47, D-263). Text pattern: Growth, claude/site/Site_Adds_R2-46-48_2026-11-12.md §3.
 * `php artisan site:share-cards` writes the card data; scripts/og/render.mjs renders public/brand/og/<lang>/<slug>.jpg.
 * Pages without an image keep the logo square.
 */
class ShareCard
{
    /** @var array<string, array{0: string, 1: string}> path => [Arabic headline, English headline] where the page name is too long or opens / ends with Latin */
    private const SHARE_TITLES = [
        '/' => ['منصة الإعلانات والسيو للوكالات بالعربية', 'Arabic-first ads and SEO for agencies'],
        '/ramadan-2027' => ['دليل حملات رمضان 2027 للوكالات', 'Ramadan 2027 ad guide for agencies'],
        '/tools/ramadan-budget-calculator' => ['حاسبة ميزانية إعلانات رمضان 2027', 'Ramadan 2027 ad budget calculator'],
        '/mena-calendar' => ['تقويم المواسم 2027 للمنطقة', 'MENA ad calendar 2027'],
        '/tools/roas-calculator' => ['حاسبة العائد على الإنفاق الإعلاني', 'ROAS calculator'],
        '/tools/cod-poas-calculator' => ['حاسبة ربح الدفع عند الاستلام', 'Cash-on-delivery profit calculator'],
        '/tools/arabic-keyword-generator' => ['مولّد الكلمات المفتاحية العربية', 'Arabic keyword generator'],
        '/tools/page-audit' => ['فحص سيو الصفحة العربية', 'Arabic page SEO audit'],
        '/tools/ad-pixel-checker' => ['فاحص بكسلات الإعلانات ووسومها', 'Ad pixel and tag checker'],
        '/tools/whatsapp-link-generator' => ['مولّد روابط WhatsApp للإعلانات', 'WhatsApp ad link generator'],
        '/tools/safe-zone-checker' => ['فاحص مقاس 9:16 والمناطق الآمنة', '9:16 safe-zone checker'],
        '/tools' => ['أدوات عربية مجانية للسيو والإعلانات', 'Free Arabic SEO and ad tools'],
        // E1: names that open or end with Latin, or run over 42 / 48 characters, reworded in the same pattern.
        '/tools/llms-txt-generator' => ['منشئ ملف llms.txt لموقعك', 'llms.txt generator'],
        '/tools/schema-generator' => ['مولّد البيانات المنظمة للصفحات', 'JSON-LD schema generator'],
        '/tools/robots-sitemap-checker' => ['فاحص ملف robots.txt وخريطة الموقع', 'Robots.txt and sitemap checker'],
        '/tools/agency-fee-vat-calculator' => ['حاسبة أتعاب الوكالة والضريبة', 'Agency fee and VAT calculator'],
        '/tools/ab-significance-calculator' => ['حاسبة دلالة اختبارات المقارنة', 'A/B test significance calculator'],
        '/tools/gulf-ad-disclosure-checklist' => ['قائمة إفصاحات الإعلانات الخليجية', 'KSA and UAE ad disclosure checklist'],
        '/tools/cpm-cpc-cpa-calculator' => ['حاسبة مؤشرات تكلفة الإعلانات', 'CPM, CPC and CPA calculator'],
        '/tools/meta-length-checker' => ['فاحص طول العنوان والوصف', 'Title and description length checker'],
        '/bot' => ['زاحف AdsRanger لفحص الصفحات', 'AdsRangerBot: our page checker'],
        '/about' => ['من نحن ولماذا نبني في عمّان', 'Why we build AdsRanger in Amman'],
    ];

    /** Free tools that open a URL, so "nothing stored" isn't promised on their card. */
    private const URL_TOOLS = ['page-audit', 'hreflang-checker', 'ad-pixel-checker', 'robots-sitemap-checker', 'llms-txt-generator'];

    public static function slug(string $arPath): string
    {
        return $arPath === '/' ? 'home' : str_replace('/', '-', trim($arPath, '/'));
    }

    public static function imageUrl(string $arPath, bool $ar): ?string
    {
        $rel = 'brand/og/'.($ar ? 'ar' : 'en').'/'.self::slug($arPath).'.jpg';

        return is_file(public_path($rel)) ? 'https://adsranger.com/'.$rel : null;
    }

    /** @return list<string> every public page that gets a card */
    public static function paths(): array
    {
        $tools = array_map(fn (string $t) => '/tools/'.$t, PublicSite::publicTools());
        $pages = array_keys(PublicSiteController::sitemapPageViews());

        return array_values(array_unique(array_merge(['/'], $pages, config('seo_tools.public') ? ['/tools'] : [], $tools)));
    }

    /** @return array{eyebrow: string, headline: string, subline: string} */
    public static function card(string $arPath, bool $ar): array
    {
        $headline = self::SHARE_TITLES[$arPath][$ar ? 0 : 1] ?? PublicSite::name($arPath, $ar);
        [$eyebrow, $sub] = match (true) {
            $arPath === '/' => [['قيد التطوير · الإطلاق في 2027', 'In development · launching 2027'], ['منصة عربية أولًا لوكالات المنطقة', 'An Arabic-first platform for agencies in the region']],
            $arPath === '/tools' || str_starts_with($arPath, '/tools/') => [['أداة مجانية', 'Free tool'], in_array(substr($arPath, 7), self::URL_TOOLS, true) || $arPath === '/tools'
                ? ['مجانًا ودون تسجيل', 'Free, no sign-up']
                : ['مجانًا، دون تسجيل، ولا نحفظ شيئًا', 'Free, no sign-up, nothing stored']],
            in_array($arPath, ['/ramadan-2027', '/mena-calendar'], true) => [['دليل للوكالات', 'Guide for agencies'], ['تواريخ متوقعة مع مصدر لكل تاريخ', 'Expected dates, with a source for each']],
            in_array($arPath, ['/guides', '/glossary', '/blog'], true) => [['دليل للوكالات', 'Guide for agencies'], ['', '']],
            in_array($arPath, ['/features', '/reports', '/studio', '/search', '/ai', '/demo-report', '/integrations', '/pricing', '/roadmap', '/solutions/agencies', '/solutions/freelancers', '/solutions/business-owners', '/for-clients', '/for-teams', '/partners', '/waitlist'], true) => [['قيد التطوير · الإطلاق في 2027', 'In development · launching 2027'], ['', '']],
            default => [['', ''], ['', '']],
        };

        return ['eyebrow' => $eyebrow[$ar ? 0 : 1], 'headline' => $headline, 'subline' => $sub[$ar ? 0 : 1]];
    }
}
