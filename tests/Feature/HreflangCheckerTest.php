<?php

namespace Tests\Feature;

use App\Support\LandingPages;
use App\Support\Seo\Hreflang;
use App\Support\Seo\SafeFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** PR-015 §2.4 hreflang checker: the rule module, the SSRF guard and the run endpoint's limits. */
class HreflangCheckerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SafeFetcher::$resolver = fn (string $host) => str_ends_with($host, '.test-site.com') ? '93.184.216.34' : LandingPages::publicIp($host);
    }

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    private function page(string $lang, string $dir, array $links, string $canonical = ''): string
    {
        $tags = implode('', array_map(fn ($c, $h) => '<link rel="alternate" hreflang="'.$c.'" href="'.$h.'">', array_keys($links), $links));

        return '<!doctype html><html lang="'.$lang.'" dir="'.$dir.'"><head>'.$tags.($canonical ? '<link rel="canonical" href="'.$canonical.'">' : '').'</head><body>x</body></html>';
    }

    public function test_a_correct_pair_passes(): void
    {
        $links = ['ar' => 'https://www.test-site.com/', 'en' => 'https://www.test-site.com/en/', 'x-default' => 'https://www.test-site.com/'];
        Http::fake([
            'www.test-site.com/en/' => Http::response($this->page('en', 'ltr', $links), 200, ['Content-Type' => 'text/html']),
            'www.test-site.com/' => Http::response($this->page('ar', 'rtl', $links), 200, ['Content-Type' => 'text/html']),
        ]);
        $r = Hreflang::check('https://www.test-site.com/');
        $this->assertSame([], $r['issues']);
        $this->assertCount(2, $r['pages']);
    }

    public function test_missing_return_link_bad_codes_and_arabic_direction_are_flagged(): void
    {
        Http::fake([
            'www.test-site.com/en/' => Http::response($this->page('en', 'ltr', ['en' => 'https://www.test-site.com/en/']), 200, ['Content-Type' => 'text/html']),
            'www.test-site.com/' => Http::response($this->page('ar', 'ltr', ['ar-UAE' => 'https://www.test-site.com/', 'en-UK' => 'https://www.test-site.com/en/', 'ar_SA' => 'https://www.test-site.com/']), 200, ['Content-Type' => 'text/html']),
        ]);
        $codes = array_column(Hreflang::check('https://www.test-site.com/')['issues'], 'code');
        foreach (['bad_code', 'no_x_default', 'no_return'] as $c) {
            $this->assertContains($c, $codes);
        }
        $this->assertSame(3, count(array_filter(Hreflang::check('https://www.test-site.com/')['issues'], fn ($i) => $i['code'] === 'bad_code')));
        $this->assertTrue(Hreflang::validCode('ar-AE'));
        $this->assertFalse(Hreflang::validCode('en-UK'));
    }

    public function test_arabic_page_without_rtl_is_critical(): void
    {
        $links = ['ar' => 'https://www.test-site.com/', 'x-default' => 'https://www.test-site.com/'];
        Http::fake(['www.test-site.com/' => Http::response($this->page('ar', 'ltr', $links), 200, ['Content-Type' => 'text/html'])]);
        $this->assertContains('ar_no_rtl', array_column(Hreflang::check('https://www.test-site.com/')['issues'], 'code'));
    }

    public function test_private_and_metadata_addresses_are_refused(): void
    {
        Http::fake();
        foreach (['http://127.0.0.1/', 'http://169.254.169.254/latest/meta-data/', 'http://10.0.0.5/', 'ftp://www.test-site.com/', 'https://www.test-site.com:8443/'] as $url) {
            $this->assertSame('blocked', SafeFetcher::fetch($url)['error'], $url);
        }
        Http::assertNothingSent();
    }

    public function test_redirects_to_a_private_address_are_refused(): void
    {
        Http::fake(['www.test-site.com/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);
        $this->assertSame('blocked', SafeFetcher::fetch('https://www.test-site.com/go')['error']);
    }

    public function test_run_endpoint_checks_and_limits_to_five_a_day(): void
    {
        RateLimiter::clear('hreflang-hour:'.hash('sha256', config('app.key').'|127.0.0.1'));
        $links = ['ar' => 'https://www.test-site.com/', 'x-default' => 'https://www.test-site.com/'];
        Http::fake(['www.test-site.com/' => Http::response($this->page('ar', 'rtl', $links), 200, ['Content-Type' => 'text/html'])]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/tools/hreflang-checker/run', ['url' => 'https://www.test-site.com/'])->assertOk()->assertJsonStructure(['pages', 'issues']);
        }
        $this->postJson('/tools/hreflang-checker/run', ['url' => 'https://www.test-site.com/'])->assertStatus(429);
        $this->postJson('/tools/hreflang-checker/run', ['url' => 'javascript:alert(1)'])->assertStatus(422);
    }
}
