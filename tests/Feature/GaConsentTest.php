<?php

namespace Tests\Feature;

use Tests\TestCase;

/** GA4 on the public site loads only after "Accept analytics"; without an ID the site stays essential-only. */
class GaConsentTest extends TestCase
{
    public function test_without_a_measurement_id_the_banner_stays_essential_only(): void
    {
        config(['services.ga4.measurement_id' => null]);
        $this->get('/en/pricing')->assertOk()->assertSee('Essential cookies only', false)->assertDontSee('Accept analytics')
            ->assertSee('var GA=null', false);
    }

    public function test_with_an_id_the_banner_asks_and_ga_waits_for_consent(): void
    {
        config(['services.ga4.measurement_id' => 'G-TEST12345']);
        $html = $this->get('/en/pricing')->assertOk()->getContent();
        $this->assertStringContainsString('Accept analytics', $html);
        $this->assertStringContainsString('Essential only', $html);
        $this->assertStringContainsString('var GA="G-TEST12345"', $html);
        $this->assertStringNotContainsString('<script async src="https://www.googletagmanager.com', $html, 'GA must not load before consent');
        $this->assertStringContainsString('allow_ad_personalization_signals:false', $html);
        $this->get('/')->assertOk()->assertSee('قبول التحليلات', false);
    }

    public function test_privacy_pages_name_google_analytics_and_offer_settings(): void
    {
        $this->get('/en/privacy')->assertOk()->assertSee('Google Analytics')->assertSee('data-cookie-settings', false);
        $this->get('/privacy')->assertOk()->assertSee('قبول التحليلات')->assertSee('data-cookie-settings', false);
    }
}
