<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use Tests\TestCase;

/** cl51: security headers + security.txt (Website W2), A-8 font loading, UX-195, UX-196, R-23. */
class SiteFixesCl51Test extends TestCase
{
    public function test_public_and_private_responses_carry_the_security_headers(): void
    {
        foreach (['/', '/about', '/app/login'] as $path) {
            $response = $this->get($path);
            foreach (SecurityHeaders::HEADERS as $name => $value) {
                $response->assertHeader($name, $value);
            }
        }

        $this->get('https://adsranger.com/about')->assertHeader('Strict-Transport-Security', SecurityHeaders::HSTS);
    }

    public function test_security_txt_has_contact_and_expiry(): void
    {
        $txt = file_get_contents(public_path('.well-known/security.txt'));

        $this->assertStringContainsString('Contact: mailto:', $txt);
        $this->assertMatchesRegularExpression('/^Expires: 20\d\d-\d\d-\d\dT/m', $txt);
    }

    public function test_site_copy_fixes(): void
    {
        $this->get('/')->assertSee('display=optional', false)->assertDontSee('display=swap', false);
        $this->get('/about')->assertSee('تُعدّ <bdi dir="ltr">AdsRanger</bdi> مساحة عمل', false)->assertSee('القصة')->assertDontSee('نبنيه ');
        $this->get('/partners')->assertSee('Google Ads')->assertDontSee('الحقول المعلَّمة بـ * مطلوبة.');
        $this->get('/waitlist')->assertSee('Google Ads');
        $this->get('/ramadan-2027')->assertSee('<a href="/mena-calendar">تقويم المواسم</a>', false);
        $this->get('/en/ramadan-2027')->assertSee('<a href="/en/mena-calendar">Seasons calendar</a>', false);
    }
}
