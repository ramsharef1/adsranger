<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoAuditPage;
use App\Jobs\RunSeoAudit;
use App\Models\Agency;
use App\Models\Client;
use App\Models\SeoAudit;
use App\Models\SeoSite;
use App\Models\User;
use App\Support\Modules;
use App\Support\Seo\Audit\Crawler;
use App\Support\Seo\Audit\SiteAuditor;
use App\Support\Seo\SafeFetcher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** Site audit A1 (FR-089 / #183): fixtures bar, polite crawl with robots.txt, rules, score, screen gate, roles, tenant isolation, CSV. */
class SeoAuditA1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SafeFetcher::$resolver = fn (string $host) => '93.184.216.34';
        config(['seo_audit.request_interval_ms' => 0]);
    }

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    /** @return array{0: Agency, 1: User, 2: Client} */
    private function workspace(string $slug, string $role = 'owner', string $plan = 'pro'): array
    {
        $user = User::factory()->create();
        $agency = Agency::create(['name' => 'Agency '.$slug, 'slug' => $slug, 'plan' => $plan, 'status' => 'active', 'signup_source' => 'admin', 'owner_id' => $user->id, 'language' => 'ar']);
        DB::table('agency_user')->where('user_id', $user->id)->delete();
        $agency->members()->attach($user->id, ['role' => $role]);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Client '.$slug, 'currency' => 'SAR', 'status' => 'active']);

        return [$agency, $user, $client];
    }

    private function fakeSite(): void
    {
        $head = fn (string $lang, string $dir, string $title, string $self, string $other, string $otherLang) => "<!doctype html><html lang=\"$lang\" dir=\"$dir\"><head><title>$title</title><meta name=\"description\" content=\"وصف الصفحة\"><link rel=\"canonical\" href=\"$self\"><link rel=\"alternate\" hreflang=\"$lang\" href=\"$self\"><link rel=\"alternate\" hreflang=\"$otherLang\" href=\"$other\"><link rel=\"alternate\" hreflang=\"x-default\" href=\"$self\"><script type=\"application/ld+json\">{\"@type\":\"WebPage\"}</script></head>";
        Http::fake([
            'www.audit-site.test/robots.txt' => Http::response("User-agent: *\nDisallow: /private/\n", 200, ['Content-Type' => 'text/plain']),
            'www.audit-site.test/' => Http::response($head('ar', 'rtl', 'عيادة أسنان في عمّان', 'https://www.audit-site.test/', 'https://www.audit-site.test/en/', 'en').'<body><h1>عيادة أسنان</h1><a href="/en/">English</a><a href="/services">الخدمات</a><a href="/private/x">x</a><a href="/missing">مفقود</a><a href="https://other.test/x">out</a></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'www.audit-site.test/en/' => Http::response($head('en', 'ltr', 'Dental clinic in Amman', 'https://www.audit-site.test/en/', 'https://www.audit-site.test/', 'ar').'<body><h1>Dental clinic</h1><a href="/">Arabic</a></body></html>', 200, ['Content-Type' => 'text/html']),
            // Arabic page with no dir / lang and no hreflang pair → AR-RTL-01, AR-LANG-01 (critical), HREF-01, no H1.
            'www.audit-site.test/services' => Http::response('<!doctype html><html><head><title>خدمات العيادة وأسعار التقويم والتبييض وزراعة الأسنان في عمّان والزرقاء وإربد</title><link rel="canonical" href="https://www.audit-site.test/services"></head><body><p>تقويم وتبييض وزراعة الأسنان لكل الأعمار في عيادتنا.</p><img src="/a.png"></body></html>', 200, ['Content-Type' => 'text/html']),
            'www.audit-site.test/missing' => Http::response('not found', 404, ['Content-Type' => 'text/html']),
            'www.audit-site.test/private/*' => Http::response('secret', 200, ['Content-Type' => 'text/html']),
        ]);
    }

    public function test_fixture_harness_finds_every_critical_and_no_false_critical(): void
    {
        $this->artisan('seo:audit-fixtures')->assertExitCode(0);
    }

    public function test_crawler_reads_robots_stays_on_host_and_the_auditor_scores_the_site(): void
    {
        $this->fakeSite();
        [$agency, $owner, $client] = $this->workspace('crawl');
        $site = SeoSite::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'url' => 'https://www.audit-site.test/', 'host' => 'www.audit-site.test', 'consent_user_id' => $owner->id, 'consent_at' => now()]);
        $audit = SeoAudit::create(['agency_id' => $agency->id, 'seo_site_id' => $site->id, 'status' => 'queued']);

        $crawler = new Crawler('https://www.audit-site.test/', 200, 0, fn () => null);
        $this->assertTrue($crawler->readRobots());
        $this->assertFalse($crawler->allowed('https://www.audit-site.test/private/x'));

        (new SiteAuditor(fn () => null))->run($audit->fresh());
        $audit->refresh();

        $this->assertSame('done', $audit->status);
        $urls = $audit->pages()->pluck('url')->all();
        $this->assertEqualsCanonicalizing(['https://www.audit-site.test/', 'https://www.audit-site.test/en/', 'https://www.audit-site.test/services', 'https://www.audit-site.test/missing'], $urls);
        $this->assertNotContains('https://www.audit-site.test/private/x', $urls, 'robots.txt Disallow is respected');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'other.test'));

        $codes = $audit->issues()->pluck('code')->unique()->sort()->values()->all();
        foreach (['AR-RTL-01', 'AR-LANG-01', 'HREF-01', 'H1-01', 'LINK-01', 'STATUS-01', 'IMG-01', 'AR-TITLE-01'] as $expected) {
            $this->assertContains($expected, $codes, "expected $expected in ".implode(',', $codes));
        }
        $this->assertNotContains('HREF-02', $codes, 'the ar / en pair links both ways');
        $this->assertSame(3, $audit->critical_count); // AR-RTL-01 + AR-LANG-01 on /services, STATUS-01 on /missing
        $this->assertSame(SiteAuditor::score(['critical' => $audit->critical_count, 'warning' => $audit->warning_count, 'info' => $audit->info_count], 4), $audit->score);
        $this->assertGreaterThan(0, $audit->score);
        $this->assertLessThanOrEqual(79, $audit->score, 'UX-247: any error caps the score in the amber band');
        $this->assertSame(79, SiteAuditor::score(['critical' => 1, 'warning' => 0, 'info' => 0], 200));
        $this->assertSame(100, SiteAuditor::score(['critical' => 0, 'warning' => 0, 'info' => 50], 10));
    }

    public function test_robots_disallow_all_blocks_the_audit(): void
    {
        Http::fake(['www.closed.test/robots.txt' => Http::response("User-agent: AdsRangerBot\nDisallow: /\n", 200, ['Content-Type' => 'text/plain'])]);
        [$agency, $owner, $client] = $this->workspace('closed');
        $site = SeoSite::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'url' => 'https://www.closed.test/', 'host' => 'www.closed.test', 'consent_user_id' => $owner->id, 'consent_at' => now()]);
        $audit = SeoAudit::create(['agency_id' => $agency->id, 'seo_site_id' => $site->id, 'status' => 'queued']);
        (new SiteAuditor(fn () => null))->run($audit);
        $this->assertSame('blocked', $audit->fresh()->status);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://www.closed.test/');
    }

    public function test_page_is_held_back_by_the_module_and_owner_adds_a_site_with_consent_and_queues_an_audit(): void
    {
        $this->assertFalse(Modules::enabled('seo_audit'));
        [$agency, $owner, $client] = $this->workspace('gate');
        $this->assertContains($this->actingAs($owner)->get("/app/{$agency->slug}/seo-audit")->status(), [403, 404]);
        $this->assertContains($this->actingAs($owner)->get("/app/{$agency->slug}/arabic-seo")->status(), [403, 404], 'the E2 Arabic SEO scaffold is held with the suite');

        config(['modules.preview_agencies' => ['gate']]);
        $this->actingAs($owner)->get("/app/{$agency->slug}/seo-audit?lang=ar")->assertOk()->assertSee('أؤكد أن لدى الوكالة إذن العميل');

        Queue::fake();
        $this->actingAs($owner);
        Filament::setTenant($agency);
        Livewire::test(SeoAuditPage::class)
            ->set('newSiteUrl', 'https://www.audit-site.test/')
            ->set('consent', false)
            ->call('addSite')
            ->assertHasErrors(['newSiteUrl'])
            ->set('consent', true)
            ->call('addSite')
            ->assertHasNoErrors()
            ->call('runAudit');
        $site = SeoSite::where('agency_id', $agency->id)->firstOrFail();
        $this->assertNotNull($site->consent_at);
        $this->assertSame($owner->id, $site->consent_user_id);
        $this->assertSame('queued', SeoAudit::where('seo_site_id', $site->id)->firstOrFail()->status);
        Queue::assertPushed(RunSeoAudit::class);
    }

    public function test_analysts_cannot_add_sites_and_another_agency_cannot_see_the_audit(): void
    {
        config(['modules.enabled.seo_audit' => true]);
        [$agencyA, $ownerA, $clientA] = $this->workspace('tenant-a');
        [$agencyB, $analystB, $clientB] = $this->workspace('tenant-b', 'analyst');
        $site = SeoSite::create(['agency_id' => $agencyA->id, 'client_id' => $clientA->id, 'url' => 'https://www.audit-site.test/', 'host' => 'www.audit-site.test', 'consent_user_id' => $ownerA->id, 'consent_at' => now()]);
        SeoAudit::create(['agency_id' => $agencyA->id, 'seo_site_id' => $site->id, 'status' => 'done', 'score' => 77, 'pages_done' => 3, 'finished_at' => now()]);

        $this->actingAs($ownerA)->get("/app/{$agencyA->slug}/seo-audit")->assertOk()->assertSee('www.audit-site.test')->assertSee('77');
        $this->actingAs($analystB)->get("/app/{$agencyB->slug}/seo-audit")->assertOk()->assertDontSee('www.audit-site.test')->assertDontSee('I confirm the agency');

        $this->actingAs($analystB);
        Filament::setTenant($agencyB);
        Livewire::test(SeoAuditPage::class)->set('newSiteUrl', 'https://www.b.test/')->set('consent', true)->call('addSite');
        $this->assertSame(0, SeoSite::where('agency_id', $agencyB->id)->count());
    }

    public function test_csv_export_lists_issues_with_arabic_fix_lines(): void
    {
        config(['modules.enabled.seo_audit' => true]);
        $this->fakeSite();
        [$agency, $owner, $client] = $this->workspace('csv');
        $site = SeoSite::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'url' => 'https://www.audit-site.test/', 'host' => 'www.audit-site.test', 'consent_user_id' => $owner->id, 'consent_at' => now()]);
        $audit = SeoAudit::create(['agency_id' => $agency->id, 'seo_site_id' => $site->id, 'status' => 'queued']);
        (new SiteAuditor(fn () => null))->run($audit);

        app()->setLocale('ar');
        $this->actingAs($owner);
        Filament::setTenant($agency);
        $response = Livewire::test(SeoAuditPage::class)->call('downloadCsv');
        $response->assertFileDownloaded();
    }
}
