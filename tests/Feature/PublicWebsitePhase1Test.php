<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicSiteController;
use App\Models\WaitlistEntry;
use App\Support\PublicUrl;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicWebsitePhase1Test extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_legal_pages_render_cookie_banner_and_phase1_links(): void
    {
        $this->get('/')->assertOk()->assertSee('ar-cookie-banner', false)->assertSee('/waitlist', false)->assertSee('/ramadan-2027', false);
        $this->raw('/en/')->assertOk()->assertSee('ar-cookie-banner', false)->assertSee('/en/waitlist', false)->assertSee('/en/ramadan-2027', false);
        $this->get('/privacy')->assertOk()->assertSee('ar-cookie-banner', false);
        $this->get('/en/privacy')->assertOk()->assertSee('ar-cookie-banner', false);
    }

    public function test_waitlist_pages_render_and_store_entries_in_ar_and_en(): void
    {
        $this->get('/waitlist')->assertOk()->assertSee('قائمة انتظار')->assertSee('name="consent"', false)->assertSee('name="country"', false);
        $this->get('/en/waitlist')->assertOk()->assertSee('Join the AdsRanger waitlist');

        $this->post('/waitlist', [
            'agency_name' => 'وكالة عمان الرقمية',
            'email' => 'Rami@Example.com',
            'country' => 'JO',
            'consent' => '1',
            'monthly_spend' => '50k_200k',
            'networks' => ['meta', 'tiktok'],
        ])->assertRedirect('/waitlist')->assertSessionHas('waitlist_saved');

        $entry = WaitlistEntry::sole();
        $this->assertSame('rami@example.com', $entry->email);
        $this->assertSame('JO', $entry->country);
        $this->assertSame('meta,tiktok', $entry->networks);
        $this->assertNull($entry->name);
        $this->assertSame(WaitlistEntry::CONSENT_TEXT_VERSION, $entry->consent_text_version);
        $this->assertTrue($entry->mayBeEmailed());

        $this->followingRedirects()->post('/en/waitlist', [
            'agency_name' => 'Amman Digital', 'email' => 'rami@example.com', 'country' => 'JO', 'consent' => '1',
        ])->assertSee('Thanks, you&#039;re on the list', false);
        $this->assertSame(1, WaitlistEntry::count());
    }

    /** O-230 / D-189: "agency or company" label, the optional "You are…" choice, the new privacy line. */
    public function test_waitlist_asks_who_you_are_and_stores_the_answer(): void
    {
        $this->get('/waitlist')->assertOk()->assertSee('اسم الوكالة أو الشركة')->assertSee('من أنت؟ (اختياري)')
            ->assertSee('شركة تدير إعلاناتها بنفسها')->assertSee('نستخدم بريدك واسم شركتك أو وكالتك ودولتك فقط لإبلاغك بإطلاق', false);
        $this->get('/en/waitlist')->assertOk()->assertSee('Agency or company name')->assertSee('A business running its own ads');

        $this->post('/waitlist', ['agency_name' => 'متجر سوق', 'email' => 'owner@souq.test', 'country' => 'SA', 'consent' => '1', 'audience' => 'business'])
            ->assertRedirect('/waitlist');
        $this->assertSame('business', WaitlistEntry::where('email', 'owner@souq.test')->value('audience'));

        $this->post('/waitlist', ['agency_name' => 'X Co', 'email' => 'x@co.test', 'country' => 'SA', 'consent' => '1', 'audience' => 'reseller'])
            ->assertSessionHasErrors('audience');
        $this->post('/waitlist', ['agency_name' => 'A', 'email' => 'y@co.test', 'country' => 'SA', 'consent' => '1'])
            ->assertSessionHasErrors(['agency_name' => 'أدخل اسم وكالتك أو شركتك (حرفان على الأقل).']);
    }

    /** D-177 / UX-133: the sample report uses an invented furniture retailer, not a medical client. */
    public function test_demo_report_uses_the_fictional_furniture_retailer(): void
    {
        $this->get('/demo-report')->assertOk()->assertSee('مفروشات سِدرة')->assertDontSee('عيادة');
        $this->get('/en/demo-report')->assertOk()->assertSee('Sidra Home Furnishings')->assertSee('fictional store')->assertDontSee('Dental');
    }

    /** D-152 / D-159: country and an unticked consent box are required; the honeypot saves nothing. */
    public function test_waitlist_requires_country_and_consent_and_ignores_bots(): void
    {
        $this->post('/en/waitlist', ['agency_name' => 'A', 'email' => 'bad'])
            ->assertSessionHasErrors(['email', 'agency_name', 'country', 'consent']);

        $this->post('/waitlist', ['agency_name' => 'وكالة', 'email' => 'a@b.co', 'country' => 'ZZ'])
            ->assertSessionHasErrors(['country' => 'اختر دولتك.', 'consent' => 'ضع علامة في المربع للموافقة على رسائل الإطلاق.']);

        $this->post('/waitlist', [
            'agency_name' => 'Bot', 'email' => 'bot@example.com', 'country' => 'JO', 'consent' => '1', 'website' => 'http://spam.test',
        ])->assertRedirect('/waitlist')->assertSessionHas('waitlist_saved');

        $this->assertSame(0, WaitlistEntry::count());
    }

    public function test_waitlist_allows_five_submits_per_ip_per_hour(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/en/waitlist', ['agency_name' => 'Agency', 'email' => "a{$i}@example.com", 'country' => 'AE', 'consent' => '1']);
        }

        $this->post('/en/waitlist', ['agency_name' => 'Agency', 'email' => 'a6@example.com', 'country' => 'AE', 'consent' => '1'])
            ->assertSessionHasErrors(['form' => 'Too many tries. Please wait an hour and try again.']);
        $this->assertSame(5, WaitlistEntry::count());
    }

    /** Requests the exact path: the test client's get() trims a trailing slash, so it can't reach /en/ or /pricing/. */
    private function raw(string $path): TestResponse
    {
        $response = $this->app->make(Kernel::class)->handle(Request::create($path, 'GET'));

        return TestResponse::fromBaseResponse($response);
    }

    /** @return list<string> every public URL (P-0 set + R-19 pages), Arabic and English. */
    private function publicUrls(): array
    {
        $urls = [];
        foreach (array_keys(PublicSiteController::sitemapPageViews()) as $arPath) {
            $urls[] = PublicUrl::path($arPath, 'ar');
            $urls[] = PublicUrl::path($arPath, 'en');
        }

        return $urls;
    }

    /** P-0 + P-5 on every public URL: one <main>, skip link, menu button, font, self canonical, 3 hreflang incl. x-default, meta description, no banned strings. */
    public function test_every_public_page_uses_the_shared_layout_and_seo_head(): void
    {
        $banned = ['Ads Ranger', 'Sep 2027', 'سبتمبر 2027', '14+', 'الشبكات الإعلانية', 'Staging environment', 'بيئة تجريبية', 'أدز رينجر', 'ReadOnlyGuard', 'Blended ROAS', 'CAPI'];
        foreach ($this->publicUrls() as $url) {
            $html = $this->raw($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, '<main '), "{$url}: one <main>");
            $this->assertStringContainsString('class="skip" href="#main"', $html, "{$url}: skip link");
            $this->assertStringContainsString('id="sh-menu"', $html, "{$url}: menu button");
            $this->assertStringContainsString('fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic', $html, "{$url}: font");
            $this->assertStringContainsString('<link rel="canonical" href="https://adsranger.com'.$url.'">', $html, "{$url}: self canonical");
            $this->assertSame(3, preg_match_all('/<link rel="alternate" hreflang="(ar|en|x-default)"/', $html), "{$url}: hreflang");
            $this->assertMatchesRegularExpression('/<meta name="description" content="[^"]{20,}">/u', $html, "{$url}: meta description");
            foreach ($banned as $phrase) {
                if (str_contains($url, 'mena-calendar') && str_contains($phrase, '2027')) {
                    continue; // the calendar lists real September 2027 dates; the rule is about the launch month
                }
                $this->assertStringNotContainsString($phrase, $html, "{$url} contains {$phrase}");
            }
        }
        $this->get('/pricing')->assertSee('<a href="/pricing"  aria-current="page" >', false);
        $this->get('/en/integrations')->assertSee('Sample report')->assertSee('Seasons calendar');
    }

    /** R-14 / D-152: /en/ is the English home; /en and other trailing-slash variants 301 to the one form. */
    public function test_english_home_lives_at_en_slash(): void
    {
        $this->get('/en')->assertStatus(301)->assertHeader('Location', '/en/');
        $this->get('/en?for=agency')->assertStatus(301)->assertHeader('Location', '/en/?for=agency');
        $this->raw('/pricing/')->assertStatus(301)->assertHeader('Location', '/pricing');
        $this->raw('/en/')->assertOk()->assertSee('<link rel="canonical" href="https://adsranger.com/en/">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="https://adsranger.com/">', false);
    }

    /** Home = Package G rev 2 (D-232, D-234): new H1 + lead, status line, problem → answer, planned platforms. */
    public function test_home_rev_4_copy(): void
    {
        $this->get('/')->assertSee('اعرف ما تحققه إعلانات كل عميل، قبل أن يسألك')->assertSee('قيد التطوير · الإطلاق في 2027')
            ->assertSee('ما يُبطئ الوكالات، وما نبنيه لحلّه')->assertSee('استوديو الإعلانات')->assertSee('المنصات الإعلانية التي نخطط لربطها')
            ->assertDontSee('Google Analytics 4')->assertDontSee('تجريبية خاصة')->assertDontSee('افتح المنصة')->assertDontSee('24/7');
        $this->raw('/en/')->assertSee('Know what every client&#039;s ads achieve, before they ask', false)->assertSee('Microsoft Advertising');
    }

    /** P-3 pillar body + P-4 calculator at its own URL (defaults 3,600 USD · 5.50 USD · 654). */
    public function test_ramadan_pillar_and_calculator(): void
    {
        $this->get('/ramadan-2027')->assertOk()->assertSee('8 فبراير 2027')->assertSee('العشر الأواخر')->assertSee('/tools/ramadan-budget-calculator', false)
            ->assertSee('"@type":"FAQPage"', false)->assertDontSee('2027-02-08')->assertDontSee('أم القرى')->assertDontSee('rm-out-budget', false);
        $this->get('/en/ramadan-2027')->assertSee('Monday 8 February 2027')->assertSee('/en/tools/ramadan-budget-calculator', false);
        $this->get('/tools/ramadan-budget-calculator')->assertOk()->assertSee('حاسبة ميزانية إعلانات رمضان 2027')->assertSee('أدخل رقمًا يساوي 0 أو أكثر.');
        $this->get('/en/tools/ramadan-budget-calculator')->assertOk()->assertSee('3,600 USD')->assertSee('5.50 USD')->assertSee('>654<', false);
    }

    /** P-6 Plans, P-7 Ad platforms, P-8 Sample report, P-9 calendar, P-10 Security. */
    public function test_fixed_public_pages_follow_their_packages(): void
    {
        $this->get('/pricing')->assertOk()->assertSee('باقة المستقل')->assertSee('الباقة الأساسية')->assertSee('باقة الوكالات')
            ->assertSee('قواعد التنبيه المتقدمة')->assertSee('تحتفظ الوكالات المؤسِّسة بسعر الإطلاق.')->assertDontSee('Webhooks');
        $this->get('/en/pricing')->assertOk()->assertSee('Solo')->assertDontSee('Request Pro');

        $this->get('/integrations')->assertOk()->assertSee('المرحلة الأولى')->assertSee('Microsoft Advertising')->assertDontSee('ACOS');
        $this->get('/en/integrations')->assertOk()->assertSee('Later stage')->assertSee('Outbrain');

        $this->get('/demo-report')->assertOk()->assertSee('18,450 USD')->assertSee('88 / 100')->assertSee('مفروشات سِدرة')
            ->assertSee('وليس عميلًا حقيقيًا')->assertSee('role="region"', false)->assertDontSee('الرياض')->assertDontSee('عيادة');
        $this->get('/en/demo-report')->assertOk()->assertSee('Sidra Home Furnishings')->assertSee('Not a real client.')->assertDontSee('Riyadh');

        $this->travelTo(Carbon::parse('2026-11-07 12:00', 'Asia/Amman'));
        $this->get('/mena-calendar')->assertOk()->assertSee('تقويم مواسم الإعلانات في المنطقة 2027')->assertSee('9 أو 10 مارس 2027')->assertSee('الجمعة البيضاء');
        $this->get('/en/mena-calendar')->assertOk()->assertSee('White Friday')->assertSee('9 or 10 March 2027')->assertDontSee('CPMs shift');

        $this->get('/security')->assertOk()->assertSee('privacy@adsranger.com')->assertDontSee('agency_id');
        $this->get('/en/security')->assertOk()->assertSee('network access keys are stored encrypted')->assertDontSee('TOTP');
    }

    /** R-13 / R-19: /sitemap.xml is an index; the pages child lists every public URL with ar / en / x-default and lastmod. */
    public function test_sitemap_index_and_pages_child(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertSee('<sitemapindex', false)->assertSee('https://adsranger.com/sitemaps/pages.xml', false);
        $xml = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
        foreach ($this->publicUrls() as $url) {
            $this->assertStringContainsString('<loc>https://adsranger.com'.$url.'</loc>', $xml);
        }
        $this->assertStringNotContainsString('<loc>https://adsranger.com/en</loc>', $xml);
        $this->assertSame(count($this->publicUrls()), substr_count($xml, 'hreflang="x-default"'));
        $this->assertSame(count($this->publicUrls()), substr_count($xml, '<lastmod>'));
        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString("Disallow: /app/\n", $robots);
        $this->assertStringContainsString('Sitemap: https://adsranger.com/sitemap.xml', $robots);
    }
}
