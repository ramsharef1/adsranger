<?php

namespace Tests\Feature;

use App\Alerts\AlertEngine;
use App\Alerts\AlertText;
use App\Models\Agency;
use App\Models\Alert;
use App\Support\ArabicCount;
use App\Support\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UX-121: alert titles and details are shown in Arabic, keeping numbers, codes and names. */
class AlertTextTest extends TestCase
{
    use RefreshDatabase;

    private function plain(string $s): string
    {
        return str_replace(["\u{2066}", "\u{2068}", "\u{2069}"], '', $s);
    }

    public function test_titles_and_details_translate_and_keep_values(): void
    {
        $this->assertSame('ارتفاع تكلفة النتيجة 23%: Souq Store', $this->plain(AlertText::title('Cost per result up 23%: Souq Store', 'ar')));
        $this->assertSame('معدل تكرار مرتفع (4.2): عيادة', $this->plain(AlertText::title('High ad frequency (4.2): عيادة', 'ar')));
        $this->assertSame('صفحة هبوط معطّلة', AlertText::title('Broken landing page', 'ar'));
        $this->assertSame('أُنفق 120.50 USD خلال آخر 7 أيام دون أي نتيجة.', $this->plain(AlertText::detail('120.50 USD spent in the last 7 days with 0 results.', 'ar')));
        $this->assertSame('الإنفاق المتوقع 3,000.00 مقابل ميزانية 2,500.00 USD لشهر November 2026. أنفِق نحو 80.00 يوميًا لتصل إلى الميزانية.',
            $this->plain(AlertText::detail('Forecast 3,000.00 vs budget 2,500.00 USD for November 2026. Spend about 80.00 a day to land on budget.', 'ar')));
        $this->assertSame('تنتهي الحملة في 2026-11-09.', $this->plain(AlertText::detail('Campaign ends in 2 days (2026-11-09).', 'ar')));

        $this->assertSame('Cost per result up 23%: Souq Store', AlertText::title('Cost per result up 23%: Souq Store', 'en'));
        $this->assertSame('Some network message', AlertText::detail('Some network message', 'ar'));
    }

    public function test_every_alert_the_engine_writes_on_demo_data_gets_an_arabic_title(): void
    {
        $a = Agency::create(['name' => 'Acme', 'slug' => 'acme-alert-text', 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        DemoData::seed($a, 30);
        AlertEngine::run($a, now($a->timezone)->toDateString());

        $alerts = Alert::where('agency_id', $a->id)->get();
        $this->assertGreaterThan(0, $alerts->count());
        foreach ($alerts as $al) {
            $this->assertNotSame($al->title, $al->localTitle('ar'), 'No Arabic for: '.$al->title);
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $al->localTitle('ar'));
            if ($al->detail) {
                $this->assertMatchesRegularExpression('/\p{Arabic}/u', $al->localDetail('ar'), 'No Arabic for: '.$al->detail);
            }
        }
    }

    public function test_counts_use_the_right_arabic_plural_form(): void
    {
        $this->assertSame('أُنفق 50 USD خلال آخر 30 يومًا دون أي نتيجة.', $this->plain(AlertText::detail('50 USD spent in the last 30 days with 0 results.', 'ar')));
        $this->assertSame('أُنفق 50 USD خلال آخر يومين دون أي نتيجة.', $this->plain(AlertText::detail('50 USD spent in the last 2 days with 0 results.', 'ar')));
        $this->assertSame('فشلت المزامنة 12 مرة متتالية: timeout', $this->plain(AlertText::detail('Sync failed 12 times in a row: timeout', 'ar')));
        $this->assertSame('فشلت المزامنة 3 مرات متتالية: timeout', $this->plain(AlertText::detail('Sync failed 3 times in a row: timeout', 'ar')));
        $this->assertStringContainsString('على 4 عبارات بحث دون', $this->plain(AlertText::detail('20 USD on 4 search terms with no conversions in the last 7 days. Review them in Google Ads → Search terms.', 'ar')));
        $this->assertStringContainsString('أرسلت الإعلانات 150 نقرة', $this->plain(AlertText::detail('https://x.test — 404. Ads sent 150 clicks here in 30 days.', 'ar')));
        $this->assertSame('300 يوم', $this->plain(ArabicCount::phrase(300, 'days')));
    }
}
