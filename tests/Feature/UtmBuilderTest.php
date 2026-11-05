<?php

namespace Tests\Feature;

use App\Filament\Pages\UtmBuilderPage;
use App\Models\Agency;
use App\Models\LandingPage;
use App\Models\User;
use App\Support\LandingPages;
use App\Support\UtmBuilder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** #86 UTM builder + link checker (Starter). */
class UtmBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $plan = 'starter', string $role = 'analyst'): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-'.$plan.'-'.$role, 'plan' => $plan, 'status' => 'active']);
        $user = User::factory()->create();
        $agency->members()->attach($user->id, ['role' => $role]);

        return [$agency, $user];
    }

    public function test_build_adds_utm_keeps_other_params_and_fragment(): void
    {
        $url = UtmBuilder::build('https://shop.example.com/offer?ref=abc&utm_source=old#form', [
            'utm_source' => 'facebook', 'utm_medium' => 'paid_social', 'utm_campaign' => 'ramadan offer', 'utm_content' => '',
        ]);

        $this->assertSame('https://shop.example.com/offer?ref=abc&utm_source=facebook&utm_medium=paid_social&utm_campaign=ramadan%20offer#form', $url);
    }

    public function test_network_placeholders_stay_unencoded(): void
    {
        $meta = UtmBuilder::presets()['meta'];
        $url = UtmBuilder::build('https://example.com/', ['source' => $meta['source'], 'medium' => $meta['medium'], 'campaign' => $meta['campaign'], 'content' => $meta['content']]);
        $this->assertSame('https://example.com/?utm_source={{site_source_name}}&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_content={{ad.name}}', $url);

        $g = UtmBuilder::build('https://example.com/a', ['source' => 'google', 'medium' => 'cpc', 'campaign' => '{campaignid}', 'term' => '{keyword}']);
        $this->assertStringContainsString('utm_campaign={campaignid}', $g);
        $this->assertStringContainsString('utm_term={keyword}', $g);

        $this->assertTrue(UtmBuilder::isPlaceholder('__CAMPAIGN_NAME__'));
        $this->assertFalse(UtmBuilder::isPlaceholder('{campaign name}'));
    }

    public function test_invalid_base_returns_null_and_warnings_explain_issues(): void
    {
        $this->assertNull(UtmBuilder::build('example.com/offer', ['source' => 'x']));
        $this->assertNull(UtmBuilder::build('ftp://example.com/', ['source' => 'x']));

        $w = UtmBuilder::warnings('http://example.com/?utm_medium=a', ['source' => 'Facebook', 'medium' => 'paid social', 'campaign' => '']);
        $this->assertContains('Source, medium and campaign are all needed for clean reports in GA4.', $w);
        $this->assertContains('Use lowercase: GA4 treats "Facebook" and "facebook" as two different sources.', $w);
        $this->assertContains('Spaces become %20 in the link; use _ or - instead.', $w);
        $this->assertContains('The address already had UTM parameters; they were replaced.', $w);
        $this->assertContains('The page is not HTTPS: browsers warn visitors and some networks reject it.', $w);

        $this->assertSame([], UtmBuilder::warnings('https://example.com/', UtmBuilder::presets()['google'] + ['campaign' => 'brand']));
    }

    public function test_page_renders_on_starter_for_every_role_in_both_languages(): void
    {
        foreach (['owner', 'analyst'] as $role) {
            [$agency, $user] = $this->workspace('starter', $role);
            $this->withSession(['locale' => 'en'])->actingAs($user)->get("/app/{$agency->slug}/utm-builder")
                ->assertOk()->assertSee('UTM builder')->assertSee('Enter a landing page address to see the tracked link.');
            $this->withSession(['locale' => 'ar'])->actingAs($user)->get("/app/{$agency->slug}/utm-builder")
                ->assertOk()->assertSee('منشئ روابط UTM')->assertSee('عنوان صفحة الهبوط');
        }
    }

    public function test_preset_fills_fields_and_content_lines_make_one_link_each(): void
    {
        [$agency, $user] = $this->workspace();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        $c = Livewire::test(UtmBuilderPage::class)
            ->set('baseUrl', 'https://example.com/offer')
            ->set('preset', 'google')
            ->assertSet('source', 'google')
            ->assertSet('medium', 'cpc')
            ->assertSet('term', '{keyword}')
            ->set('campaign', 'brand')
            ->set('content', "video_30s\ncarousel_a\n\nvideo_30s");

        $links = $c->instance()->links();
        $this->assertCount(2, $links);
        $this->assertStringContainsString('utm_content=video_30s', $links[0]);
        $this->assertStringContainsString('utm_content=carousel_a', $links[1]);
        $c->assertSee('utm_campaign=brand', false);
    }

    public function test_link_check_grades_page_without_storing_and_refuses_private_hosts(): void
    {
        [$agency, $user] = $this->workspace();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        Http::fake(['*' => Http::response('<html>ok</html>', 200)]);
        Livewire::test(UtmBuilderPage::class)
            ->set('baseUrl', 'https://93.184.216.34/offer')
            ->call('checkLink')
            ->assertSet('check.state', 'ok')
            ->assertSet('check.status_code', 200);

        Livewire::test(UtmBuilderPage::class)
            ->set('baseUrl', 'http://127.0.0.1/admin')
            ->call('checkLink')
            ->assertSet('check.state', 'blocked');

        $this->assertSame(0, LandingPage::count());
        Http::assertSentCount(1);
    }

    public function test_link_check_is_rate_limited(): void
    {
        [$agency, $user] = $this->workspace();
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        $c = Livewire::test(UtmBuilderPage::class)->set('baseUrl', 'http://127.0.0.1/');
        for ($i = 0; $i < 10; $i++) {
            $c->call('checkLink');
        }
        $c->call('checkLink')->assertHasErrors('baseUrl');
    }

    public function test_landing_page_check_still_saves_through_probe(): void
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-lp', 'plan' => 'pro', 'status' => 'active']);
        Http::fake(['*' => Http::response('', 404)]);
        $lp = LandingPage::create(['agency_id' => $agency->id, 'url' => 'https://93.184.216.34/gone', 'url_hash' => sha1('x'), 'source' => 'manual']);

        LandingPages::check($lp);

        $lp->refresh();
        $this->assertSame('broken', $lp->state);
        $this->assertSame(404, (int) $lp->status_code);
        $this->assertNotNull($lp->checked_at);
    }
}
