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
        $this->get('/waitlist')->assertOk()->assertSee('قائمة الانتظار');
        $this->get('/en/waitlist')->assertOk()->assertSee('Join the waitlist');

        $this->post('/waitlist', [
            'name' => 'رامي الشريف',
            'agency_name' => 'وكالة عمان الرقمية',
            'email' => 'rami@example.com',
            'monthly_spend' => '50k_200k',
            'notes' => 'Meta, Google, TikTok',
        ])->assertRedirect('/waitlist');

        $this->assertDatabaseHas('waitlist_entries', [
            'email' => 'rami@example.com',
            'agency_name' => 'وكالة عمان الرقمية',
            'lang' => 'ar',
        ]);

        $this->assertSame(1, WaitlistEntry::count());
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

        $this->get('/demo-report')->assertOk()->assertSee('$18,450')->assertSee('88 / 100');
        $this->get('/en/demo-report')->assertOk()->assertSee('$18,450')->assertSee('88 / 100');

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
