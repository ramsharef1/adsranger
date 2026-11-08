<?php

namespace Tests\Feature;

use Tests\TestCase;

/** D-241: home rev 3 (Ads + SEO, in development), new title, honest permission wording across the site. */
class HomeRev3D241Test extends TestCase
{
    public function test_home_rev_3_copy_and_six_faqs(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSee('<title>منصة الإعلانات والسيو للوكالات بالعربية | AdsRanger</title>', false)
            ->assertSee('فحص المواقع وتتبّع الظهور في البحث.')
            ->assertSee('هل تشمل')->assertSee('قيد التطوير')
            ->getContent();

        preg_match('#<script type="application/ld\+json">(\{[^<]*FAQPage[^<]*\})</script>#', $html, $m);
        $this->assertCount(6, json_decode($m[1], true)['mainEntity']);
    }

    public function test_no_read_only_account_claims_on_public_pages(): void
    {
        foreach (['/', '/security', '/integrations', '/about', '/partners', '/en/', '/en/security', '/en/integrations', '/en/about'] as $path) {
            $html = $this->get($path)->getContent();
            foreach (['صلاحية قراءة فقط', 'بصلاحية قراءة فقط', 'Read-only access', 'read-only access', 'Read-only reporting', 'read-only reporting'] as $claim) {
                $this->assertStringNotContainsString($claim, $html, $path.' · '.$claim);
            }
        }
        $this->get('/')->assertSee('دون موافقة أحد أعضاء فريقك');
    }
}
