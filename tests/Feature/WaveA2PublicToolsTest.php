<?php

namespace Tests\Feature;

use App\Support\PublicSite;
use App\Support\Seo\CapiPixelChecker;
use App\Support\Seo\LlmsTxtAuditor;
use App\Support\Seo\RobotsSitemapChecker;
use App\Support\Seo\SafeFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class WaveA2PublicToolsTest extends TestCase
{
    private const WAVE_A2_SLUGS = [
        'ad-pixel-checker',
        'cod-poas-calculator',
        'whatsapp-link-generator',
        'ad-mockup-preview',
        'llms-txt-generator',
        'schema-generator',
        'ab-significance-calculator',
        'safe-zone-checker',
        'gulf-ad-disclosure-checklist',
        'cpm-cpc-cpa-calculator',
        'agency-fee-vat-calculator',
        'robots-sitemap-checker',
    ];

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    public function test_all_12_ar_and_12_en_wave_a2_tool_pages_render_with_seo_head_and_respect_visibility_gate(): void
    {
        config(['seo_tools.public' => false]);

        foreach (self::WAVE_A2_SLUGS as $slug) {
            $ar = $this->get('/tools/'.$slug);
            $ar->assertOk();
            $ar->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $arHtml = $ar->getContent();
            $this->assertStringContainsString('dir="rtl"', $arHtml, "AR RTL missing on $slug");
            $this->assertStringContainsString('rel="canonical"', $arHtml, "Canonical missing on $slug");
            $this->assertStringContainsString('hreflang="en"', $arHtml, "EN hreflang missing on $slug");
            $this->assertStringContainsString('"@type":"FAQPage"', $arHtml, "FAQPage JSON-LD missing on $slug");
            $this->assertStringNotContainsString('#f97316', strtolower($arHtml), "Banned orange on $slug");

            $en = $this->get('/en/tools/'.$slug);
            $en->assertOk();
            $en->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $enHtml = $en->getContent();
            $this->assertStringContainsString('lang="en"', $enHtml, "EN lang missing on $slug");
            $this->assertStringContainsString('hreflang="ar"', $enHtml, "AR hreflang missing on $slug");
            $this->assertStringContainsString('"@type":"FAQPage"', $enHtml, "EN FAQPage JSON-LD missing on $slug");
        }

        // Sitemap hides tools when SEO_TOOLS_PUBLIC=false, and includes all 12 AR+EN pairs when SEO_TOOLS_PUBLIC=true
        $hiddenXml = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('/tools/ad-pixel-checker', $hiddenXml);

        // D-249: with the tools public, the held-back ones still stay out of the sitemap and hub and keep noindex.
        config(['seo_tools.public' => true, 'seo_tools.held' => ['ad-pixel-checker']]);
        $heldXml = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('/tools/ad-pixel-checker', $heldXml);
        $this->assertStringNotContainsString('/tools/ad-pixel-checker', $this->get('/tools')->getContent());
        $this->get('/tools/ad-pixel-checker')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        config(['seo_tools.held' => []]);
        $publicXml = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
        $hubAr = $this->get('/tools')->assertOk()->getContent();
        $hubEn = $this->get('/en/tools')->assertOk()->getContent();

        foreach (self::WAVE_A2_SLUGS as $slug) {
            $this->assertStringContainsString('/tools/'.$slug, $publicXml);
            $this->assertStringContainsString('/en/tools/'.$slug, $publicXml);
            $this->assertStringContainsString('/tools/'.$slug, $hubAr);
            $this->assertStringContainsString('/en/tools/'.$slug, $hubEn);
        }
    }

    public function test_ssrf_guard_and_5_per_day_rate_limit_on_all_three_safe_fetcher_endpoints(): void
    {
        RateLimiter::clear('capi-pixel-day:'.hash('sha256', config('app.key').'|127.0.0.1').':'.now()->toDateString());
        RateLimiter::clear('capi-pixel-hour:'.hash('sha256', config('app.key').'|127.0.0.1'));

        // Private / loopback / metadata addresses are refused by SafeFetcher
        SafeFetcher::$resolver = fn () => null;

        $r1 = $this->postJson('/tools/ad-pixel-checker/run', ['url' => 'http://169.254.169.254/latest/meta-data/']);
        $r1->assertOk()->assertJson(['ok' => false, 'error' => 'blocked']);

        $r2 = $this->postJson('/tools/llms-txt-generator/check', ['url' => 'http://127.0.0.1/']);
        $r2->assertOk()->assertJson(['ok' => false, 'error' => 'blocked']);

        $r3 = $this->postJson('/tools/robots-sitemap-checker/run', ['url' => 'http://10.0.0.5/']);
        $r3->assertOk()->assertJson(['ok' => false, 'error' => 'blocked']);

        // 5 runs/day visitor limit: 4 more runs on ad-pixel-checker succeed (total 5), 6th returns 429
        for ($i = 2; $i <= 5; $i++) {
            $this->postJson('/tools/ad-pixel-checker/run', ['url' => 'https://private.example/'])->assertOk();
        }
        $this->postJson('/tools/ad-pixel-checker/run', ['url' => 'https://private.example/'])
            ->assertStatus(429)
            ->assertJson(['error' => 'limit']);
    }

    public function test_capi_pixel_llms_txt_and_robots_sitemap_analyzers_detect_tags_bots_and_conflicts(): void
    {
        $sampleHtml = <<<'HTML'
<!doctype html>
<html>
<head>
<link rel="canonical" href="https://store.example/ar">
<script>window.dataLayer = window.dataLayer || []; gtag('config', 'G-ABC1234567'); gtag('config', 'AW-99887766');</script>
<script src="https://www.googletagmanager.com/gtm.js?id=GTM-KSA999"></script>
<script>fbq('init', '123456789012345'); ttq.load('C9ABCDEF123456'); snaptr('init', '11112222-3333-4444-5555-666677778888'); _linkedin_partner_id = "654321";</script>
<script src="https://cdn.salla.network/js/app.js"></script>
</head>
<body><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-KSA999"></iframe></noscript></body>
</html>
HTML;

        $pixelReport = CapiPixelChecker::inspectHtml($sampleHtml);
        $keys = array_column($pixelReport['detected'], 'key');
        $this->assertContains('meta_pixel', $keys);
        $this->assertContains('gtm', $keys);
        $this->assertContains('ga4', $keys);
        $this->assertContains('google_ads', $keys);
        $this->assertContains('tiktok_pixel', $keys);
        $this->assertContains('snap_pixel', $keys);
        $this->assertContains('linkedin_insight', $keys);
        $this->assertContains('salla_hooks', $keys);
        $this->assertSame('salla', $pixelReport['ecommerce_platform']);
        $this->assertTrue($pixelReport['has_datalayer']);
        $this->assertTrue($pixelReport['has_noscript']);
        $this->assertSame([], $pixelReport['missing_basics']);

        $robots = "User-agent: GPTBot\nDisallow: /\n\nUser-agent: ClaudeBot\nAllow: /\n\nUser-agent: *\nDisallow: /checkout\nSitemap: https://store.example/sitemap.xml\n";
        $bots = LlmsTxtAuditor::auditRobotsText($robots);
        $this->assertSame('blocked', $bots['GPTBot']);
        $this->assertSame('allowed', $bots['ClaudeBot']);
        $this->assertSame('unspecified', $bots['PerplexityBot']);

        SafeFetcher::$resolver = fn () => '93.184.216.34';
        Http::fake([
            'https://store.example/robots.txt' => Http::response($robots, 200, ['Content-Type' => 'text/plain']),
            'https://store.example/checkout' => Http::response($sampleHtml, 200, ['Content-Type' => 'text/html']),
            'https://store.example/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset></urlset>', 200, ['Content-Type' => 'application/xml']),
        ]);

        $rs = RobotsSitemapChecker::check('https://store.example/checkout');
        $this->assertTrue($rs['ok']);
        $this->assertTrue($rs['blocked_by_robots']);
        $this->assertContains('url_disallowed', $rs['issues']);
        $this->assertContains('canonical_points_elsewhere', $rs['issues']);
        $this->assertTrue($rs['sitemaps'][0]['valid_xml']);
    }

    public function test_robots_allow_rule_beats_a_shorter_disallow(): void
    {
        SafeFetcher::$resolver = fn () => '93.184.216.34';
        Http::fake([
            'https://store.example/robots.txt' => Http::response("User-agent: *\nDisallow: /checkout\nAllow: /checkout/help\n", 200, ['Content-Type' => 'text/plain']),
            'https://store.example/checkout/help' => Http::response('<html><head><link rel="canonical" href="https://store.example/checkout/help"></head></html>', 200, ['Content-Type' => 'text/html']),
            'https://store.example/checkout/pay' => Http::response('<html><head><link rel="canonical" href="https://store.example/checkout/pay"></head></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->assertFalse(RobotsSitemapChecker::check('https://store.example/checkout/help')['blocked_by_robots']);
        $this->assertTrue(RobotsSitemapChecker::check('https://store.example/checkout/pay')['blocked_by_robots']);
    }

    public function test_disclosure_checklist_never_gives_a_pass_verdict(): void
    {
        $html = $this->get('/en/tools/gulf-ad-disclosure-checklist')->assertOk()->getContent();
        $this->assertStringNotContainsString("'Pass'", $html);
        $this->assertStringContainsString('not a legal check', $html);
    }

    public function test_header_menu_is_short_and_points_to_the_full_tools_page(): void
    {
        config(['seo_tools.public' => true, 'seo_tools.held' => []]);
        $menu = PublicSite::toolsMenu();
        $this->assertSame('/tools', $menu[0]);
        $this->assertContains('/tools/page-audit', $menu);
        $this->assertNotContains('/tools/safe-zone-checker', $menu);
        $this->assertLessThanOrEqual(8, count($menu));

        config(['seo_tools.public' => false]);
        $this->assertSame(['/tools/ramadan-budget-calculator', '/ramadan-2027', '/mena-calendar'], PublicSite::toolsMenu());
    }

    public function test_renamed_tools_redirect_permanently(): void
    {
        $this->get('/tools/capi-pixel-checker')->assertStatus(301)->assertRedirect('/tools/ad-pixel-checker');
        $this->get('/en/tools/ksa-ad-compliance-checker')->assertStatus(301)->assertRedirect('/en/tools/gulf-ad-disclosure-checklist');
    }

    public function test_bot_page_names_the_user_agent_the_fetcher_sends_and_is_in_the_sitemap(): void
    {
        $this->assertSame('AdsRangerBot/1.0 (+https://adsranger.com/bot)', SafeFetcher::USER_AGENT);
        $this->get('/bot')->assertOk()->assertSee('AdsRangerBot/1.0 (+https://adsranger.com/bot)')->assertSee('لا يسجّل الدخول');
        $this->get('/en/bot')->assertOk()->assertSee('never signs in or submits forms', false);
        $this->assertStringContainsString('/bot', $this->get('/sitemaps/pages.xml')->getContent());
    }

    public function test_tools_menu_link_shows_its_label_not_raw_paths(): void
    {
        config(['seo_tools.public' => true]);
        $this->get('/')->assertOk()->assertSee('كل الأدوات المجانية')->assertDontSee('>/tools/page-audit<', false);
        $this->get('/en/about')->assertOk()->assertSee('All free tools');
    }

    public function test_llms_txt_accessibility_page_and_share_images(): void
    {
        config(['seo_tools.public' => true]);
        $txt = $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8')->getContent();
        $this->assertStringStartsWith('# AdsRanger', $txt);
        $this->assertStringContainsString('(https://adsranger.com/en/tools/page-audit)', $txt);
        $this->assertStringNotContainsString('never trained', $this->get('/en/ai')->getContent());

        $this->get('/accessibility')->assertOk()->assertSee('إمكانية الوصول')->assertDontSee('compliant');
        $this->get('/en/accessibility')->assertOk()->assertSee('WCAG 2.2 level AA');
        $this->assertStringContainsString('/accessibility', $this->get('/sitemaps/pages.xml')->getContent());

        $this->get('/tools/roas-calculator')->assertSee('https://adsranger.com/brand/og/ar/tools-roas-calculator.jpg', false);
        $this->artisan('site:share-cards', ['--out' => 'storage/framework/testing-share-cards.json'])->assertExitCode(0);
    }
}
