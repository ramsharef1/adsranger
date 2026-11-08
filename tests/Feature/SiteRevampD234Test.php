<?php

namespace Tests\Feature;

use App\Support\PublicSite;
use Tests\TestCase;

/** D-234 revamp: grouped menu, hero band + breadcrumb, related pages, closing band, footer columns, home sections. */
class SiteRevampD234Test extends TestCase
{
    public function test_header_has_grouped_menu_and_footer_columns(): void
    {
        $html = $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('aria-controls="mm-0"', $html);
        $this->assertMatchesRegularExpression('#<a href="/pricing"\s+aria-current="page"\s*>الباقات</a>#', $html);
        foreach (['/tools/ramadan-budget-calculator', '/mena-calendar', '/solutions/business-owners', '/data-deletion'] as $path) {
            $this->assertStringContainsString('href="'.$path.'"', $html, $path);
        }
    }

    public function test_inner_pages_get_hero_band_breadcrumb_related_and_closing_band(): void
    {
        $this->get('/security')->assertSee('class="ph"', false)->assertSee('aria-label="مسار الصفحة"', false)
            ->assertSee('صفحات ذات صلة')->assertSee('كن أول من يعرف عند الإطلاق')->assertSee('ما زالت <bdi dir="ltr">AdsRanger</bdi>', false);
        $this->get('/en/pricing')->assertSee('Breadcrumb')->assertSee('Related pages');
        $this->get('/privacy')->assertSee('class="ph"', false)->assertDontSee('id="cb-h"', false);
        $this->get('/waitlist')->assertDontSee('Related pages')->assertDontSee('id="ar-sticky"', false);
    }

    public function test_home_sections_faq_schema_and_occasions(): void
    {
        $this->get('/')->assertSee('"@type":"FAQPage"', false)->assertSee('كيف تعمل')->assertSee('مصمَّمة لأسواق المنطقة')
            ->assertSee('بياناتك وبيانات عملائك')->assertSee('هل تدير وكالة إعلانات في المنطقة؟')->assertSee('/waitlist?type=agency', false)
            ->assertSee('data-cta="waitlist"', false);

        $occ = PublicSite::nextOccasions(3, '2027-01-01');
        $this->assertCount(3, $occ);
        $this->assertSame(collect($occ)->pluck('date')->sort()->values()->all(), collect($occ)->pluck('date')->all());
    }

    public function test_waitlist_and_ramadan_copy_fixes_w7_to_w10(): void
    {
        $this->get('/waitlist')->assertSee('ما زالت <bdi dir="ltr">AdsRanger</bdi> قيد التطوير', false)->assertDontSee('الحقول المعلَّمة بـ * مطلوبة.');
        $this->get('/en/waitlist')->assertSee('the ad platforms you run')->assertDontSee('Fields marked * are required.');
        $this->get('/en/ramadan-2027')->assertSee('reporting across ad platforms.')->assertDontSee('across networks');
    }

    public function test_cl1_rev2_copy_is_on_the_pages(): void
    {
        $this->get('/tools/ramadan-budget-calculator')->assertSee('كيف تعمل الحاسبة')->assertSee('"@type":"FAQPage"', false)->assertSee('مواعيد رمضان والعيد في كل دولة');
        $this->get('/contact')->assertSee('بماذا نساعدك')->assertSee('طلب شراكة')->assertDontSee('hello@adsranger.com');
        $this->get('/security')->assertSee('ما لا نفعله أبدًا')->assertSee('إن وجدت ثغرة')->assertDontSee('قراءة فقط');
        $this->get('/en/security')->assertSee('What we never do')->assertDontSee('read-only');
        $this->get('/integrations')->assertSee('كيف سيتم الربط')->assertSee('تقرير موحد لكل المنصات')->assertDontSee('قراءة فقط');
        $this->get('/about')->assertSee('القصة')->assertSee('أين نحن الآن')->assertSee('ساعدنا في البناء كشريك');
        $this->get('/pricing')->assertSee('هل توجد فترة تجربة؟')->assertSee('شكل تقرير العميل')->assertSee('أصحاب المشاريع');
        $this->get('/demo-report')->assertSee('كيف تقرأ هذا التقرير')->assertSee('أي باقة تشمل هذا التقرير');
        $this->get('/mena-calendar')->assertSee('كيف تستخدم التقويم');
        $this->get('/partners')->assertSee('كيف نحمي بياناته');
        $this->get('/solutions/business-owners')->assertSee('تقويم المواسم')->assertSee('اعرف ما تخطط له');
    }

    public function test_calculator_currency_and_days_toggle_and_pricing_table(): void
    {
        $this->get('/tools/ramadan-budget-calculator')->assertSee('id="rc-currency"', false)->assertSee('value="KWD" data-dec="3"', false)
            ->assertSee('name="days" value="29"', false)->assertSee('عدد أيام رمضان')->assertSee('اختر عملة حساباتك الإعلانية من القائمة')->assertDontSee('id="rc-days"', false);
        $this->get('/pricing')->assertSee('مقارنة الباقات')->assertSee('توقع نهاية الشهر')->assertSee('خطة الميزانية السنوية')->assertDontSee('المقارنات المرجعية');
        $this->get('/en/pricing')->assertSee('Compare plans')->assertSee('Annual budget plan');
        $this->get('/pricing')->assertSee('دون الحاجة إلى بطاقة')->assertSee('ضمن حدود الباقة الأصغر');
        $this->get('/contact')->assertSee('نرد عادةً خلال يومَي عمل، من الأحد إلى الخميس.');
        $this->get('/tools/ramadan-budget-calculator')->assertSee('دينار أردني (JOD)');
        $this->get('/pricing')->assertSee('<caption class="sr">مقارنة الباقات</caption>', false)->assertSee('aria-label="مقارنة الباقات"', false);
        $this->get('/')->assertSee('عن <bdi dir="ltr">AdsRanger</bdi>', false)->assertSee('لا تغيّر <bdi dir="ltr">AdsRanger</bdi>', false);
    }

    public function test_home_occasions_show_ad_seasons_only(): void
    {
        $this->assertSame(['C5', 'A2', 'B1'], collect(PublicSite::nextOccasions(3, '2026-11-08'))->pluck('id')->all());
        $this->assertNotContains('B19', collect(PublicSite::nextOccasions(20, '2026-11-08'))->pluck('id')->all());

        $this->travelTo(now('Asia/Amman')->setDate(2026, 11, 8));
        $this->get('/')->assertSee('مهرجان دبي للتسوق')->assertDontSee('عيد الاستقلال السوداني');
    }
}
