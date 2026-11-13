<?php

namespace Tests\Feature;

use App\Support\LlmsTxt;
use App\Support\PublicSite;
use App\Support\Seo\AiCrawlerChecker;
use App\Support\Seo\SafeFetcher;
use App\Support\ShareCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** FR-REACH R-3: free AI Bot Traffic & Unmonetized Revenue Checker, gated by the reach module and never indexed. */
class ReachR3Test extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/tools/ai-crawler-revenue-checker';

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    public function test_tool_is_a_404_while_reach_is_off(): void
    {
        $this->get(self::PATH)->assertNotFound();
        $this->get('/en'.self::PATH)->assertNotFound();
        $this->postJson(self::PATH.'/run', ['url' => 'https://example.com/'])->assertNotFound();
    }

    public function test_page_is_noindex_bilingual_and_names_no_competitor(): void
    {
        config(['modules.enabled.reach' => true]);
        $ar = $this->get(self::PATH)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('مدقق زيارات بوتات الذكاء الاصطناعي والعائد غير المستثمر')
            ->assertSee('<bdi dir="ltr">robots.txt</bdi>', false)->getContent();
        $en = $this->get('/en'.self::PATH)->assertOk()->assertSee('AI Bot Traffic &amp; Unmonetized Revenue Checker', false)->getContent();
        foreach (['adsense', 'admob', 'taboola', 'outbrain', 'ezoic', 'mediavine', 'propellerads'] as $rival) {
            $this->assertStringNotContainsStringIgnoringCase($rival, $ar.$en);
        }
        $this->assertStringContainsString('/reach', $ar, 'funnels publishers to Reach');
    }

    public function test_kept_out_of_sitemap_menus_hub_share_cards_and_llms_txt(): void
    {
        config(['modules.enabled.reach' => true, 'seo_tools.public' => true]);
        $this->assertSame('reach', config('seo_tools.module_gated.ai-crawler-revenue-checker'));
        $this->assertNotContains('ai-crawler-revenue-checker', config('seo_tools.tools'));
        $this->assertNotContains('ai-crawler-revenue-checker', PublicSite::publicTools());
        $this->assertNotContains(self::PATH, ShareCard::paths());
        $this->get('/sitemaps/pages.xml')->assertOk()->assertDontSee('ai-crawler-revenue-checker');
        $this->get('/tools')->assertOk()->assertDontSee('ai-crawler-revenue-checker');
        $this->assertStringNotContainsString('ai-crawler-revenue-checker', json_encode([PublicSite::menu(true), PublicSite::menu(false), PublicSite::toolsMenu()]));
        $this->assertStringNotContainsString('ai-crawler-revenue-checker', LlmsTxt::text());
    }

    public function test_robots_groups_wildcards_and_offer_signals(): void
    {
        config(['modules.enabled.reach' => true]);
        SafeFetcher::$resolver = fn () => '93.184.216.34';
        $robots = "User-agent: GPTBot\nUser-agent: CCBot\nDisallow: /\n\nUser-agent: ClaudeBot\nDisallow: /blog/*.pdf$\nAllow: /\n\n"
            ."User-agent: *\nDisallow: /blog/\nAllow: /blog/public\n";
        Http::fake([
            'https://pub.example/robots.txt' => Http::response($robots, 200, ['Content-Type' => 'text/plain']),
            'https://pub.example/llms.txt' => Http::response('', 404),
            'https://pub.example/blog/public-post' => Http::response('<html><head><title>x</title></head><body>hi</body></html>', 200, ['Content-Type' => 'text/html']),
            'https://shop.example/robots.txt' => Http::response('', 404),
            'https://shop.example/llms.txt' => Http::response("# Shop\n- offers", 200, ['Content-Type' => 'text/plain']),
            'https://shop.example/' => Http::response('<script type="application/ld+json">{"@type":"Product","offers":{"@type":"Offer","price":"10"}}</script>', 200, ['Content-Type' => 'text/html']),
        ]);

        $r = $this->postJson(self::PATH.'/run', ['url' => 'https://pub.example/blog/public-post'])->assertOk()->json();
        $by = collect($r['bots'])->keyBy('bot');
        $this->assertSame(['blocked', 'own'], [$by['GPTBot']['status'], $by['GPTBot']['rule']], 'grouped user-agents share rules');
        $this->assertSame('blocked', $by['CCBot']['status']);
        $this->assertSame('allowed', $by['ClaudeBot']['status'], '$-anchored rule does not match a non-pdf path');
        $this->assertSame(['allowed', 'wildcard'], [$by['PerplexityBot']['status'], $by['PerplexityBot']['rule']], 'longer Allow beats Disallow /blog/');
        $this->assertSame(['read_not_monetised', 'no_llms_txt'], $r['findings']);
        $this->assertFalse($r['has_offer_schema']);

        $s = $this->postJson(self::PATH.'/run', ['url' => 'https://shop.example/'])->assertOk()->json();
        $this->assertSame(11, $s['allowed']);
        $this->assertTrue($s['has_llms_txt']);
        $this->assertTrue($s['has_offer_schema']);
        $this->assertSame(['no_robots'], $s['findings']);

        $this->assertFalse(AiCrawlerChecker::allowed(AiCrawlerChecker::parse("User-agent: *\nDisallow: /blog/*.pdf$"), 'GPTBot', '/blog/a.pdf')[0]);
        $this->assertTrue(AiCrawlerChecker::allowed(AiCrawlerChecker::parse("User-agent: *\nDisallow:"), 'GPTBot', '/x')[0], 'empty Disallow allows all');
    }

    public function test_private_addresses_are_refused_and_visitors_are_limited(): void
    {
        config(['modules.enabled.reach' => true]);
        SafeFetcher::$resolver = fn () => null; // private, loopback and metadata hosts resolve to nothing public
        $this->postJson(self::PATH.'/run', ['url' => 'http://intranet.example/'])->assertOk()->assertJson(['ok' => false, 'error' => 'blocked']);
        $this->postJson(self::PATH.'/run', ['url' => 'ftp://x'])->assertUnprocessable();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson(self::PATH.'/run', ['url' => 'http://intranet.example/'])->assertOk();
        }
        $this->postJson(self::PATH.'/run', ['url' => 'http://intranet.example/'])->assertStatus(429)->assertJson(['error' => 'limit']);
    }
}
