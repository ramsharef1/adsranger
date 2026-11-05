<?php

namespace Tests\Feature;

use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Public Website Phase 1: Cookie consent banner, Waitlist (AR/EN), Ramadan 2027 planner (AR/EN), and XML Sitemap. */
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
            'agency_name' => 'وكالة عمّان',
            'email' => 'Rami@Agency.jo',
            'monthly_spend' => '50k_200k',
            'notes' => 'Meta, Google, TikTok',
        ])->assertRedirect('/waitlist');

        $this->assertDatabaseHas('waitlist_entries', [
            'email' => 'rami@agency.jo',
            'agency_name' => 'وكالة عمّان',
            'monthly_spend' => '50k_200k',
            'lang' => 'ar',
        ]);
        $this->assertSame(1, WaitlistEntry::count());
    }

    public function test_ramadan_2027_planner_renders_in_ar_and_en(): void
    {
        $this->get('/ramadan-2027')
            ->assertOk()
            ->assertSee('2027-02-08')
            ->assertSee('2027-03-09')
            ->assertSee('rm-out-budget', false);

        $this->get('/en/ramadan-2027')
            ->assertOk()
            ->assertSee('Ramadan 2027')
            ->assertSee('2027-02-08')
            ->assertSee('rm-out-budget', false);
    }

    public function test_sitemap_xml_lists_all_public_ar_and_en_urls(): void
    {
        $res = $this->get('/sitemap.xml');
        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $res->assertSee('https://adsranger.com/ramadan-2027', false);
        $res->assertSee('https://adsranger.com/en/waitlist', false);
    }
}
