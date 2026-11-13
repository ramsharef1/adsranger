<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\SeoAudit;
use App\Models\SeoSite;
use App\Models\User;
use App\Support\Seo\Audit\PageParser;
use App\Support\Seo\Audit\PageSpeed;
use App\Support\Seo\Audit\Rules;
use App\Support\Seo\Audit\SiteAuditor;
use App\Support\Seo\SafeFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Site audit A2 speed lab (E2-062): static asset checks, PageSpeed Insights contract, no invented CWV, UX-250 chip. */
class SeoAuditA2SpeedTest extends TestCase
{
    use RefreshDatabase;

    /** Trimmed PageSpeed Insights v5 answer (shape recorded from the public API docs): field data for LCP / INP / CLS / TTFB + lab. */
    private const PSI_WITH_FIELD = [
        'loadingExperience' => ['metrics' => [
            'LARGEST_CONTENTFUL_PAINT_MS' => ['percentile' => 2840, 'category' => 'AVERAGE'],
            'INTERACTION_TO_NEXT_PAINT' => ['percentile' => 212, 'category' => 'AVERAGE'],
            'CUMULATIVE_LAYOUT_SHIFT_SCORE' => ['percentile' => 12, 'category' => 'AVERAGE'],
            'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => ['percentile' => 910, 'category' => 'AVERAGE'],
        ]],
        'lighthouseResult' => [
            'categories' => ['performance' => ['score' => 0.63]],
            'audits' => [
                'largest-contentful-paint' => ['numericValue' => 4100.4],
                'cumulative-layout-shift' => ['numericValue' => 0.21],
                'server-response-time' => ['numericValue' => 640.2],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        SafeFetcher::$resolver = fn (string $host) => '93.184.216.34';
        config(['seo_audit.request_interval_ms' => 0, 'services.pagespeed.key' => null]);
    }

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    /** @return array{0: Agency, 1: User, 2: SeoAudit} */
    private function audit(string $slug): array
    {
        $user = User::factory()->create();
        $agency = Agency::create(['name' => 'Agency '.$slug, 'slug' => $slug, 'plan' => 'pro', 'status' => 'active', 'signup_source' => 'admin', 'owner_id' => $user->id, 'language' => 'ar']);
        DB::table('agency_user')->where('user_id', $user->id)->delete();
        $agency->members()->attach($user->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Client '.$slug, 'currency' => 'SAR', 'status' => 'active']);
        $site = SeoSite::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'url' => 'https://www.speed-site.test/', 'host' => 'www.speed-site.test', 'consent_user_id' => $user->id, 'consent_at' => now()]);

        return [$agency, $user, SeoAudit::create(['agency_id' => $agency->id, 'seo_site_id' => $site->id, 'status' => 'queued'])];
    }

    /** @param  array<string, mixed>  $extra */
    private function fakeSite(array $extra = []): void
    {
        Http::fake($extra + [
            'www.speed-site.test/robots.txt' => Http::response('', 404),
            'www.speed-site.test/' => Http::response('<!doctype html><html lang="ar" dir="rtl"><head><title>متجر العطور</title><link rel="canonical" href="https://www.speed-site.test/"><script src="/jquery.js"></script><link rel="stylesheet" href="/a.css"></head><body><h1>متجر العطور</h1><img src="/hero.jpg" alt="عطر"></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
    }

    public function test_static_checks_flag_each_speed_cause_and_fixtures_stay_green(): void
    {
        $this->artisan('seo:audit-fixtures')->assertExitCode(0);

        $html = '<html lang="ar" dir="rtl"><head><title>ت</title>'
            .'<script src="/a.js"></script><script src="/b.js" defer></script><script src="/c.js" async></script><script type="module" src="/d.js"></script>'
            .'<link rel="stylesheet" href="/1.css"><link rel="stylesheet" href="/2.css" media="all"><link rel="stylesheet" href="/3.css"><link rel="stylesheet" href="/p.css" media="print">'
            .'<style>'.str_repeat('a', 120_000).'</style></head><body><img src="/hero.webp" alt="x"><img src="/second.webp" alt="y"></body></html>';
        $speed = PageParser::parse($html, 'https://shop.test/')['speed'];

        $this->assertSame(1, $speed['blocking_scripts'], 'defer, async and type=module do not block');
        $this->assertSame(['https://shop.test/a.js'], $speed['blocking_scripts_src']);
        $this->assertSame(3, $speed['blocking_css'], 'media=print does not block');
        $this->assertSame('https://shop.test/hero.webp', $speed['hero_img_no_dims']);
        $this->assertGreaterThan(100_000, $speed['inline_bytes']);

        $codes = array_column(Rules::page(PageParser::parse($html, 'https://shop.test/'), 'https://shop.test/', 200), 0);
        foreach (['SPEED-01', 'SPEED-02', 'SPEED-03', 'SPEED-04'] as $code) {
            $this->assertContains($code, $codes);
        }

        $clean = PageParser::parse('<html><head><script src="/a.js" defer></script></head><body><img src="/h.webp" width="10" height="10" alt=""></body></html>', 'https://shop.test/');
        $this->assertSame([], array_values(array_filter(array_column(Rules::page($clean, 'https://shop.test/', 200), 0), fn (string $c) => str_starts_with($c, 'SPEED'))));
    }

    public function test_without_a_key_the_audit_stores_static_counts_and_no_core_web_vitals(): void
    {
        $this->fakeSite();
        [, , $audit] = $this->audit('static');
        (new SiteAuditor(fn () => null))->run($audit);
        $audit->refresh();

        $this->assertSame('done', $audit->status);
        $this->assertSame('static', $audit->speed_source);
        $this->assertNotNull($audit->speed_checked_at);
        $this->assertSame(2, $audit->render_blocking_count);
        foreach (['psi_mobile_score', 'lcp_ms', 'inp_ms', 'cls_score', 'ttfb_ms'] as $metric) {
            $this->assertNull($audit->{$metric}, "$metric must stay null without PageSpeed");
        }
        $this->assertSame(2, $audit->pages()->first()->render_blocking_count);
        $this->assertContains('SPEED-01', $audit->issues()->pluck('code')->all());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'pagespeedonline'));
    }

    public function test_pagespeed_contract_prefers_field_data_and_keeps_inp_null_without_it(): void
    {
        config(['services.pagespeed.key' => 'test-key']);
        $this->fakeSite(['www.googleapis.com/pagespeedonline/*' => Http::response(self::PSI_WITH_FIELD, 200)]);
        [, , $audit] = $this->audit('psi');
        (new SiteAuditor(fn () => null))->run($audit);
        $audit->refresh();

        $this->assertSame('psi', $audit->speed_source);
        $this->assertSame(63, $audit->psi_mobile_score);
        $this->assertSame(2840, $audit->lcp_ms, 'field LCP wins over lab');
        $this->assertSame(212, $audit->inp_ms);
        $this->assertSame(0.12, $audit->cls_score, 'CrUX CLS percentile is × 100');
        $this->assertSame(910, $audit->ttfb_ms);
        $this->assertSame('psi', $audit->pages()->first()->speed_source);
        Http::assertSent(function (Request $r): bool {
            parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

            return str_starts_with($r->url(), PageSpeed::ENDPOINT) && $q['strategy'] === 'mobile' && $q['key'] === 'test-key' && $q['url'] === 'https://www.speed-site.test/';
        });

        $labOnly = PageSpeed::parse(['lighthouseResult' => self::PSI_WITH_FIELD['lighthouseResult']]);
        $this->assertSame(['psi_mobile_score' => 63, 'lcp_ms' => 4100, 'inp_ms' => null, 'cls_score' => 0.21, 'ttfb_ms' => 640], $labOnly);
        $this->assertNull(PageSpeed::parse(['error' => ['code' => 429]]));
    }

    public function test_a_failed_pagespeed_call_falls_back_to_static_with_no_numbers(): void
    {
        config(['services.pagespeed.key' => 'test-key']);
        $this->fakeSite(['www.googleapis.com/pagespeedonline/*' => Http::response(['error' => ['code' => 500]], 500)]);
        [, , $audit] = $this->audit('psi-down');
        (new SiteAuditor(fn () => null))->run($audit);
        $audit->refresh();

        $this->assertSame('done', $audit->status);
        $this->assertSame('static', $audit->speed_source);
        $this->assertNull($audit->psi_mobile_score);
        $this->assertNull($audit->lcp_ms);
    }

    public function test_speed_chip_is_hidden_for_old_audits_and_shown_with_real_data(): void
    {
        config(['modules.enabled.seo_audit' => true]);
        [$agency, $owner, $audit] = $this->audit('chip');
        $audit->forceFill(['status' => 'done', 'score' => 90, 'pages_done' => 1, 'finished_at' => now()])->save();

        $this->actingAs($owner)->get("/app/{$agency->slug}/seo-audit?lang=en")->assertOk()
            ->assertDontSee('Page speed:')->assertDontSee('Render-blocking files on the first page');

        $audit->forceFill(['speed_source' => 'psi', 'speed_checked_at' => now(), 'psi_mobile_score' => 58, 'lcp_ms' => 3100, 'inp_ms' => null, 'cls_score' => 0.08, 'ttfb_ms' => 700, 'render_blocking_count' => 4])->save();
        $this->actingAs($owner)->get("/app/{$agency->slug}/seo-audit?lang=en")->assertOk()
            ->assertSee('Page speed: 0')->assertSee('58')->assertSee('3.1 s')->assertSee('0.08')
            ->assertSee('INP needs real-visitor data', false);

        $audit->forceFill(['speed_source' => 'static', 'psi_mobile_score' => null, 'lcp_ms' => null, 'cls_score' => null, 'ttfb_ms' => null])->save();
        $this->actingAs($owner)->get("/app/{$agency->slug}/seo-audit?lang=ar")->assertOk()
            ->assertSee('سرعة الصفحة')->assertSee('ملفات توقف عرض الصفحة الأولى')->assertDontSee('نتيجة الهاتف');
    }
}
