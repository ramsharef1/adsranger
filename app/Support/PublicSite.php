<?php

namespace App\Support;

/**
 * Public-site structure for the shared template (D-234 revamp): page labels, the grouped header menu,
 * footer columns, breadcrumbs and "related pages". All paths are Arabic paths; PublicUrl::path() localises them.
 */
class PublicSite
{
    /**
     * Short page names, used by breadcrumbs, related-page cards and the footer.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}> path => [AR name, EN name, AR line, EN line]
     */
    public static function pages(): array
    {
        return [
            '/' => ['الرئيسية', 'Home', '', ''],
            '/demo-report' => ['تقرير تجريبي', 'Sample report', 'تقرير عميل كامل ببيانات تجريبية', 'A full client report with demo data'],
            '/integrations' => ['المنصات الإعلانية', 'Ad platforms', 'المنصات التي نخطط لربطها، وكيف', 'The platforms we plan to connect, and how'],
            '/pricing' => ['الباقات', 'Plans', 'ما تخطط كل باقة لتقديمه', 'What each plan is planned to include'],
            '/security' => ['الأمان وبياناتك', 'Security and your data', 'كيف نحمي بيانات وكالتك وعملائك', 'How we protect agency and client data'],
            '/ramadan-2027' => ['دليل رمضان 2027', 'Ramadan 2027 guide', 'خطة إعلانية لكل مرحلة من الشهر', 'An ad plan for every stage of the month'],
            '/tools/ramadan-budget-calculator' => ['حاسبة ميزانية رمضان', 'Ramadan budget calculator', 'قدّر إنفاق رمضان ونتائجه', 'Estimate Ramadan spend and results'],
            '/tools/serp-preview' => ['معاينة نتائج البحث', 'Search results preview', 'كيف يظهر عنوان صفحتك ووصفها', 'How your title and description may look'],
            '/tools/meta-length-checker' => ['فاحص طول العنوان والوصف', 'Title and description length checker', 'حتى 50 صفحة في كل مرة', 'Up to 50 pages at a time'],
            '/tools/arabic-keyword-generator' => ['مولد الكلمات المفتاحية العربية', 'Arabic keyword generator', 'صيغ البحث العربية لكلمتك', 'The Arabic forms people search with'],
            '/tools/arabizi-converter' => ['محوّل العربيزي', 'Arabizi converter', 'من العربيزي إلى العربية', 'From Arabizi to Arabic'],
            '/tools/roas-calculator' => ['حاسبة العائد على الإنفاق الإعلاني', 'ROAS calculator', 'العائد ونقطة التعادل', 'Return and break-even'],
            '/tools/budget-pacing-calculator' => ['حاسبة وتيرة الإنفاق', 'Budget pacing calculator', 'هل ينفق الحساب حسب الخطة؟', 'Is the account spending to plan?'],
            '/tools/hreflang-checker' => ['فاحص روابط اللغات', 'Hreflang checker', 'روابط اللغات بين نسخ صفحتك', 'Language links between your page versions'],
            '/tools/page-audit' => ['فاحص السيو العربي للصفحة', 'Arabic page SEO audit', 'فحص العنوان والاتجاه والكلمات والبيانات المنظمة', 'Check title, RTL direction, keywords and schema'],
            '/tools/utm-builder' => ['منشئ روابط التتبّع', 'UTM link builder', 'روابط تتبّع للحملات', 'Tracking links for campaigns'],
            '/mena-calendar' => ['تقويم المواسم', 'Seasons calendar', 'مواسم 2027 ومناسباتها في المنطقة', 'The region\'s 2027 seasons and occasions'],
            '/about' => ['من نحن', 'About', 'لماذا نبني AdsRanger في عمّان', 'Why we\'re building AdsRanger in Amman'],
            '/contact' => ['تواصل معنا', 'Contact', 'راسلنا بالعربية أو الإنجليزية', 'Email us in Arabic or English'],
            '/partners' => ['شراكة التصميم', 'Design partners', 'اعمل معنا قبل الإطلاق', 'Work with us before launch'],
            '/solutions/business-owners' => ['لأصحاب المشاريع', 'For business owners', 'لمن يدير إعلانات مشروعه بنفسه', 'For owners who run their own ads'],
            '/waitlist' => ['قائمة الانتظار', 'Waitlist', 'نراسلك عند الإطلاق', 'We\'ll email you at launch'],
            '/privacy' => ['الخصوصية', 'Privacy', 'سياسة الخصوصية', 'Privacy policy'],
            '/terms' => ['الشروط', 'Terms', 'شروط الاستخدام', 'Terms of use'],
            '/data-deletion' => ['حذف البيانات', 'Data deletion', 'كيف تطلب حذف بياناتك', 'How to ask us to delete your data'],
        ];
    }

    public static function name(string $path, bool $ar): string
    {
        return static::pages()[$path][$ar ? 0 : 1] ?? '';
    }

    public static function line(string $path, bool $ar): string
    {
        return static::pages()[$path][$ar ? 2 : 3] ?? '';
    }

    /**
     * Header menu (D-234): Product · Free tools & guides · Plans · Ad platforms · About. A group lists paths; a link is one path.
     *
     * @return list<array{label: string, path?: string, items?: list<string>}>
     */
    public static function menu(bool $ar): array
    {
        return [
            ['label' => $ar ? 'المنتج' : 'Product', 'items' => ['/demo-report', '/security', '/partners']],
            ['label' => $ar ? 'أدوات وأدلة مجانية' : 'Free tools & guides', 'items' => array_merge(['/tools/ramadan-budget-calculator', '/ramadan-2027', '/mena-calendar'], config('seo_tools.public') ? array_map(fn (string $t) => '/tools/'.$t, config('seo_tools.tools', [])) : [])],
            ['label' => $ar ? 'الباقات' : 'Plans', 'path' => '/pricing'],
            ['label' => $ar ? 'المنصات الإعلانية' : 'Ad platforms', 'path' => '/integrations'],
            ['label' => $ar ? 'عن AdsRanger' : 'About', 'items' => ['/about', '/contact']],
        ];
    }

    /** @return list<array{0: string, 1: list<string>}> footer columns: [heading, paths] */
    public static function footer(bool $ar): array
    {
        return [
            [$ar ? 'المنتج' : 'Product', ['/demo-report', '/integrations', '/pricing', '/security']],
            [$ar ? 'أدوات وأدلة مجانية' : 'Free tools & guides', ['/tools/ramadan-budget-calculator', '/ramadan-2027', '/mena-calendar']],
            [$ar ? 'الشركة' : 'Company', ['/about', '/partners', '/contact', '/solutions/business-owners']],
            [$ar ? 'القانونية' : 'Legal', ['/privacy', '/terms', '/data-deletion']],
        ];
    }

    /** @return list<string> up to 3 related pages for the "related pages" row (none on home and the waitlist). */
    public static function related(string $path): array
    {
        return match ($path) {
            '/ramadan-2027' => ['/tools/ramadan-budget-calculator', '/mena-calendar', '/demo-report'],
            '/tools/ramadan-budget-calculator' => ['/ramadan-2027', '/mena-calendar', '/pricing'],
            '/mena-calendar' => ['/ramadan-2027', '/tools/ramadan-budget-calculator', '/demo-report'],
            '/pricing' => ['/demo-report', '/integrations', '/security'],
            '/integrations' => ['/demo-report', '/security', '/pricing'],
            '/demo-report' => ['/pricing', '/integrations', '/security'],
            '/security' => ['/privacy', '/data-deletion', '/integrations'],
            '/about' => ['/security', '/partners', '/contact'],
            '/contact' => ['/about', '/security', '/partners'],
            '/partners' => ['/about', '/demo-report', '/pricing'],
            '/solutions/business-owners' => ['/tools/ramadan-budget-calculator', '/ramadan-2027', '/integrations'],
            '/privacy' => ['/terms', '/data-deletion', '/security'],
            '/terms' => ['/privacy', '/data-deletion', '/security'],
            '/data-deletion' => ['/privacy', '/terms', '/security'],
            default => [],
        };
    }

    /** Pages that end with the waitlist closing band (not home, which has its own, nor the forms and legal pages). */
    public static function hasClosingBand(string $path): bool
    {
        return ! in_array($path, ['/', '/waitlist', '/partners', '/privacy', '/terms', '/data-deletion'], true);
    }

    /**
     * Next ad seasons from the seasons calendar (D-95 data), soonest first, for the home block.
     * Only rows tagged in `mena_calendar_2027.ad_seasons` (W-6); other national days stay on /mena-calendar.
     */
    public static function nextOccasions(int $count = 3, ?string $today = null): array
    {
        $today ??= now('Asia/Amman')->toDateString();
        $adSeasons = config('mena_calendar_2027.ad_seasons', []);
        $seen = [];

        return collect(config('mena_calendar_2027.rows', []))
            ->filter(fn ($r) => in_array($r['id'], $adSeasons, true))
            ->filter(fn ($r) => ($r['end'] ?? $r['date']) >= $today)
            ->sortBy('date')
            ->filter(function ($r) use (&$seen) {
                $key = $r['event_en'];
                if (isset($seen[$key])) {
                    return false;
                }
                $seen[$key] = true;

                return true;
            })
            ->take($count)
            ->values()
            ->all();
    }
}
