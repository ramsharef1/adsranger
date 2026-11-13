<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiBrainItem;
use App\Models\PlatformSetting;
use App\Support\DemoData;
use App\Support\Ops\LiveSiteCheck;
use App\Support\Ops\NetworkDeadlines;
use App\Support\Ops\Runbook;
use App\Support\PlatformHealth;
use App\Support\Seo\PublicCatalog;
use App\Support\Site\CopyLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** cl111: R1 part 2 (D-289) — EG-10 deadlines, WS-01 / WS-02 / LV-17 live-site check, FS-54 runbook hints, EG-11 demo brain items, GR-40, GR-43. */
class OpsR1bTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        LiveSiteCheck::$fetch = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_deadline_watch_pushes_once_per_step(): void
    {
        Carbon::setTestNow('2027-06-05 08:00:00');
        $texts = NetworkDeadlines::watch();
        $this->assertCount(2, $texts, 'Amazon v3 (25 days) and the Amazon list endpoint (26 days) are inside 30 days');
        $this->assertStringContainsString('Amazon Ads', $texts[0]);
        $this->assertSame([], NetworkDeadlines::watch(), 'the same step never fires twice');
        Carbon::setTestNow('2027-06-25 08:00:00');
        $this->assertCount(2, NetworkDeadlines::watch(), 'the 7-day step fires for both');
        $this->assertTrue(collect(NetworkDeadlines::all())->firstWhere('network', 'Google Ads')['done']);
    }

    public function test_live_site_check_reads_sitemap_lints_pages_and_llms_links(): void
    {
        $page = fn (string $title, string $main) => '<html lang="ar"><head><title>'.$title.'</title><meta name="description" content="'.str_repeat('وصف ', 25).'"><link rel="alternate" hreflang="ar" href="https://a/x"><link rel="alternate" hreflang="en" href="https://a/en/x"></head><body><main>'.$main.'</main></body></html>';
        $site = [
            '/sitemap.xml' => '<sitemapindex><sitemap><loc>https://adsranger.com/sitemaps/pages.xml</loc></sitemap></sitemapindex>',
            '/sitemaps/pages.xml' => '<urlset><url><loc>https://adsranger.com/good</loc></url><url><loc>https://adsranger.com/bad</loc></url></urlset>',
            '/good' => $page('عنوان', '<p>تقارير عربية للوكالات.</p>'),
            '/bad' => $page('عنوان', '<p>إعلانات Meta وGoogle.</p>'),
            '/llms.txt' => "- [Gone](https://adsranger.com/gone): x\n- [Good](https://adsranger.com/good): y",
        ];
        LiveSiteCheck::$fetch = fn (string $url) => ['status' => isset($site[parse_url($url, PHP_URL_PATH)]) ? 200 : 404, 'body' => $site[parse_url($url, PHP_URL_PATH)] ?? ''];
        config(['app.url' => 'https://adsranger.com']);
        $r = LiveSiteCheck::run();
        $this->assertSame(2, $r['pages']);
        $errors = implode(' | ', $r['errors']);
        $this->assertStringContainsString('/bad CR-07', $errors);
        $this->assertStringContainsString('/llms.txt → /gone: HTTP 404', $errors);
        $this->assertStringNotContainsString('/good', $errors);
        config(['health.check_urls' => false, 'health.restore_test' => false]);
        $p = collect(PlatformHealth::check())->firstWhere('kind', 'live_site0');
        $this->assertNotNull($p);
        $this->assertSame(Runbook::hint('live_site0'), $p['fix'], 'FS-54: the alert carries its runbook step');
        $this->assertStringContainsString('ADSR-003', (string) collect(PlatformSetting::get(PlatformHealth::LAST_KEY)['problems'])->firstWhere('kind', 'live_site0')['fix']);
    }

    public function test_demo_data_fills_the_client_brain(): void
    {
        $this->seed();
        $agency = Agency::create(['name' => 'Demo Agency', 'slug' => 'demo-ag', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'currency' => 'USD']);
        DemoData::seed($agency, 3);
        $this->assertSame(9, AiBrainItem::where('agency_id', $agency->id)->count());
        DemoData::remove($agency);
        $this->assertSame(0, AiBrainItem::where('agency_id', $agency->id)->count());
    }

    public function test_dual_glossary_titles_and_stale_season_warning(): void
    {
        $this->assertSame('ما هما الحدّان؟', PublicCatalog::glossaryHeading(['term_ar' => 'الحدّان', 'term_en' => 'Two limits', 'ar_dual' => true], true));
        $html = '<html lang="ar"><head><title>عنوان</title></head><body><main><p>خطة رمضان 2025 للوكالات.</p></main></body></html>';
        $found = collect(CopyLint::check('/x', $html))->firstWhere('rule', 'CR-24');
        $this->assertSame('warning', $found['severity'] ?? null);
        $this->assertNull(collect(CopyLint::check('/x', str_replace('2025', '2027', $html)))->firstWhere('rule', 'CR-24'));
    }
}
