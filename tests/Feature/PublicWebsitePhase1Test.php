<?php

namespace Tests\Feature;

use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsitePhase1Test extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_legal_pages_render_cookie_banner_and_phase1_links(): void
    {
        $this->get('/')->assertOk()->assertSee('ar-cookie-banner', false)->assertSee('/waitlist', false)->assertSee('/ramadan-2027', false);
        $this->get('/en')->assertOk()->assertSee('ar-cookie-banner', false)->assertSee('/en/waitlist', false)->assertSee('/en/ramadan-2027', false);
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

    /** P-0 shared checks: no banned strings; AdsRanger is one word. */
    public function test_public_pages_avoid_banned_strings(): void
    {
        $banned = ['Ads Ranger', 'Sep 2027', 'سبتمبر 2027', '14+', 'الشبكات الإعلانية', 'Staging environment', 'بيئة تجريبية', 'أدز رينجر'];

        foreach (['/waitlist', '/en/waitlist', '/ramadan-2027', '/en/ramadan-2027'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach ($banned as $phrase) {
                $this->assertStringNotContainsString($phrase, $html, "{$url} contains {$phrase}");
            }
        }

        $this->get('/ramadan-2027')->assertSee('8 فبراير 2027')->assertSee('أم القرى')->assertDontSee('2027-02-08')->assertDontSee('/app/login" class', false);
        $this->get('/en/ramadan-2027')->assertSee('8 February 2027')->assertSee('3,600 USD')->assertDontSee('Open MENA Calendar in App');
    }

    /** One shared header on the home, inner and legal pages (R-17, UX-103): same links, current page marked, mobile toggle. */
    public function test_home_inner_and_legal_pages_share_one_menu(): void
    {
        foreach (['/', '/pricing', '/waitlist', '/privacy', '/terms'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('id="arn-links"', false)
                ->assertSee('class="arn-toggle"', false)
                ->assertSee('تقويم المواسم')
                ->assertSee('href="/app/login"', false)
                ->assertDontSee('&quot;IBM Plex', false);
        }
        $this->get('/pricing')->assertSee('<a href="/pricing"  aria-current="page" >', false);
        $this->get('/en/integrations')->assertSee('Sample report')->assertSee('Seasons calendar')->assertDontSee('Live Demo');
        $this->get('/')->assertSee('عربية أولًا مع مخطط لرمضان')->assertDontSee('تجريبية خاصة');
    }

    public function test_ramadan_2027_planner_renders_in_ar_and_en(): void
    {
        $this->get('/ramadan-2027')
            ->assertOk()
            ->assertSee('رمضان 2027')
            ->assertSee('rm-out-budget', false);

        $this->get('/en/ramadan-2027')
            ->assertOk()
            ->assertSee('Ramadan 2027')
            ->assertSee('rm-out-budget', false);
    }

    public function test_phase2_public_pages_render_in_ar_and_en(): void
    {
        $this->get('/pricing')->assertOk()->assertSee('الباقة الأساسية')->assertSee('الباقة الاحترافية')->assertSee('باقة الوكالات');
        $this->get('/en/pricing')->assertOk()->assertSee('Starter')->assertSee('Pro')->assertSee('Agency');

        $this->get('/integrations')->assertOk()->assertSee('Meta Ads')->assertSee('Snapchat Ads');
        $this->get('/en/integrations')->assertOk()->assertSee('Meta Ads')->assertSee('Snapchat Ads');

        $this->get('/demo-report')->assertOk()->assertSee('18,450 USD')->assertSee('88 / 100');
        $this->get('/en/demo-report')->assertOk()->assertSee('18,450 USD')->assertSee('88 / 100');

        $this->get('/mena-calendar')->assertOk()->assertSee('الجمعة البيضاء')->assertSee('رمضان');
        $this->get('/en/mena-calendar')->assertOk()->assertSee('White Friday')->assertSee('Ramadan');

        $this->get('/security')->assertOk()->assertSee('ReadOnlyGuard')->assertSee('2FA');
        $this->get('/en/security')->assertOk()->assertSee('ReadOnlyGuard')->assertSee('2FA');
    }

    public function test_sitemap_xml_lists_all_public_ar_and_en_urls(): void
    {
        $res = $this->get('/sitemap.xml');
        $res->assertOk();
        $res->assertSee('https://adsranger.com/ramadan-2027', false);
        $res->assertSee('https://adsranger.com/en/waitlist', false);
        $res->assertSee('https://adsranger.com/pricing', false);
        $res->assertSee('https://adsranger.com/en/integrations', false);
        $res->assertSee('https://adsranger.com/demo-report', false);
        $res->assertSee('https://adsranger.com/en/mena-calendar', false);
        $res->assertSee('https://adsranger.com/security', false);
    }
}
