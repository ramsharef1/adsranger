<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicSiteController;
use App\Support\PublicUrl;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** cl53: public site indexable (D-216 sign-offs, D-231) with the B-01 home title; private routes stay noindex. */
class GoLiveCl53Test extends TestCase
{
    public function test_every_sitemap_page_is_indexable_and_private_routes_are_not(): void
    {
        foreach (array_keys(PublicSiteController::sitemapPageViews()) as $path) {
            foreach ([PublicUrl::path($path, 'ar'), PublicUrl::path($path, 'en')] as $url) {
                $response = $this->raw($url);
                $response->assertOk();
                $this->assertStringNotContainsString('name="robots" content="noindex"', $response->getContent(), $url);
                $response->assertHeaderMissing('X-Robots-Tag');
            }
        }

        $this->get('/app/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringNotContainsString("Disallow: /\n", file_get_contents(public_path('robots.txt')));
    }

    public function test_home_title_is_b01(): void
    {
        $this->get('/')->assertSee('<title>منصة إدارة الإعلانات للوكالات بالعربية | AdsRanger</title>', false);
        $this->raw('/en/')->assertSee('<title>Arabic-First Ad Management Platform for Agencies | AdsRanger</title>', false);
    }

    /** The test client trims trailing slashes, so /en/ goes through the kernel directly. */
    private function raw(string $path): TestResponse
    {
        return TestResponse::fromBaseResponse($this->app->make(Kernel::class)->handle(Request::create($path, 'GET')));
    }
}
