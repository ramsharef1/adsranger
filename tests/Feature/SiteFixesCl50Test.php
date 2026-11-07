<?php

namespace Tests\Feature;

use App\Jobs\RunAlerts;
use App\Mail\AlertsDigestMail;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\PartnerApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** cl50: /about, /contact, /partners, business-owners hub (D-213, D-216, D-219), R-21, R-22, UX-191, agency guard (D-183). */
class SiteFixesCl50Test extends TestCase
{
    use RefreshDatabase;

    public function test_about_contact_partners_and_hub_render_in_both_languages(): void
    {
        $this->get('/about')->assertOk()->assertSee('نبنيه في عمّان لمعلني المنطقة')->assertSee('تأسست في 2026')->assertSee('"@type":"AboutPage"', false);
        $this->get('/en/about')->assertOk()->assertSee('Founded 2026 · Amman, Jordan');
        $this->get('/contact')->assertOk()->assertSee('support@adsranger.com')->assertSee('privacy@adsranger.com')->assertDontSee('WhatsApp')->assertDontSee('<form', false);
        $this->get('/partners')->assertOk()->assertSee('قدّم طلبك')->assertSee('name="clients_range"', false)
            ->assertDontSee('تحتفظ الوكالات المؤسِّسة بسعر الإطلاق')->assertDontSee('30%');
        $this->get('/en/partners')->assertOk()->assertDontSee('Founding agencies keep their launch price');
        $this->get('/solutions/business-owners')->assertOk()->assertSee('أدِر إعلانات مشروعك بنفسك')->assertSee('/waitlist?type=business', false)
            ->assertSee('/tools/ramadan-budget-calculator', false)->assertDontSee('USD');
        $this->get('/en/solutions/business-owners')->assertOk()->assertSee('Run your own business&#039;s ads', false);
        $this->get('/waitlist?type=business')->assertSee('value="business" checked', false);

        $xml = $this->get('/sitemaps/pages.xml')->getContent();
        foreach (['/about', '/en/contact', '/partners', '/en/solutions/business-owners'] as $p) {
            $this->assertStringContainsString('<loc>https://adsranger.com'.$p.'</loc>', $xml);
        }
    }

    public function test_a_partner_application_is_stored_with_its_consent_version(): void
    {
        $this->post('/partners', ['email' => 'Lead@Agency.test', 'agency_name' => 'Nour Media', 'country' => 'JO', 'clients_range' => '6-15', 'consent' => '1', 'networks' => ['meta']])
            ->assertRedirect('/partners')->assertSessionHas('partners_saved');
        $app = PartnerApplication::sole();
        $this->assertSame(['lead@agency.test', '6-15', 'meta', PartnerApplication::CONSENT_TEXT_VERSION], [$app->email, $app->clients_range, $app->networks, $app->consent_text_version]);

        $this->post('/en/partners', ['email' => 'x@y.test', 'agency_name' => 'X', 'country' => 'JO'])
            ->assertSessionHasErrors(['agency_name', 'clients_range', 'consent']);
        $this->post('/partners', ['website' => 'bot', 'email' => 'bot@x.test'])->assertRedirect('/partners');
        $this->assertSame(1, PartnerApplication::count());
    }

    /** R-21 (brand is feminine in Arabic) and R-22 (byline "AdsRanger team", Organization author). */
    public function test_brand_gender_and_team_byline(): void
    {
        $this->get('/security')->assertSee('كيف تحمي AdsRanger')->assertDontSee('كيف يحمي');
        $this->get('/integrations')->assertSee('تخطط AdsRanger لربطها')->assertDontSee('يخطط AdsRanger');
        $this->get('/privacy')->assertSee('كيف تجمع AdsRanger');
        $this->get('/ramadan-2027')->assertSee('"author":{"@type":"Organization","name":"AdsRanger"', false)->assertDontSee('رامي الشريف')->assertDontSee('Rami Alsharef');
        $this->get('/en/ramadan-2027')->assertSee('AdsRanger team · dates expected')->assertDontSee('linkedin.com/in/ramsharef');
        $this->get('/')->assertSee('<title>مساحة عمل إعلانية عربية لوكالات المنطقة | AdsRanger</title>', false);
    }

    /** UX-191: no digest while no ad account is tracked; the Arabic subject starts with Arabic words. */
    public function test_digest_skips_workspaces_without_accounts_and_subject_starts_in_arabic(): void
    {
        Mail::fake();
        $a = Agency::create(['name' => 'Souq', 'slug' => 'souq', 'plan' => 'starter', 'status' => 'active', 'currency' => 'USD', 'timezone' => 'Asia/Amman', 'language' => 'ar']);
        $u = User::factory()->create(['email_digest' => true]);
        $a->members()->attach($u->id, ['role' => 'owner']);
        $this->travelTo(Carbon::parse('2026-11-10 09:00', 'Asia/Amman'));
        (new RunAlerts)->handle();
        Mail::assertNothingQueued();

        $subject = (new AlertsDigestMail($a, $u, '2026-11-09'))->envelope()->subject;
        $this->assertStringStartsWith('ملخّص الصباح في Souq: لا تنبيهات', $subject);
    }

    /** D-183 agency guard, proof (a) + (c): no SME hub, partner or "Find an expert" link on a client surface or in a client email. */
    public function test_client_surfaces_never_show_sme_or_partner_links(): void
    {
        $a = Agency::create(['name' => 'Agency', 'slug' => 'ag', 'plan' => 'pro', 'status' => 'active', 'currency' => 'USD']);
        $c = Client::create(['agency_id' => $a->id, 'name' => 'Client', 'currency' => 'USD', 'status' => 'active']);
        $html = $this->get('/r/'.ClientLink::issue($c, null, null, null)->token)->assertOk()->getContent();
        foreach (['/solutions/business-owners', '/partners', 'type=business', 'Find an expert'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
        foreach (glob(resource_path('views/mail/*.blade.php')) as $file) {
            $mail = file_get_contents($file);
            foreach (['/solutions/business-owners', '/partners', 'type=business', 'Find an expert'] as $needle) {
                $this->assertStringNotContainsString($needle, $mail, basename($file));
            }
        }
    }
}
