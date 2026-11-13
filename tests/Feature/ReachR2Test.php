<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\ReachCouponCode;
use App\Models\ReachLedgerEntry;
use App\Models\ReachOffer;
use App\Models\ReachPostbackEndpoint;
use App\Models\ReachPublisher;
use App\Models\ReachWidget;
use App\Models\User;
use App\Support\LlmsTxt;
use App\Support\PublicSite;
use App\Support\Reach\Postbacks;
use App\Support\Reach\Serve;
use App\Support\ShareCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** FR-REACH R-2: serve / offerwall / AI feed, web tag, human vs AI counters, publisher portal, landing, guards. */
class ReachR2Test extends TestCase
{
    use RefreshDatabase;

    private const UA_DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36';

    private const UA_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36';

    private function on(): void
    {
        config(['modules.enabled.reach' => true]);
    }

    /** @return array{0: ReachPublisher, 1: ReachWidget, 2: ReachWidget} */
    private function publisher(array $attrs = []): array
    {
        $p = ReachPublisher::factory()->verified()->create($attrs);

        return [$p, $p->widgets()->create(['type' => 'web_js', 'placement' => 'sidebar']), $p->widgets()->create(['type' => 'app_sdk', 'placement' => 'wall'])];
    }

    public function test_every_reach_url_is_a_404_while_the_module_is_off(): void
    {
        [, $web] = $this->publisher();
        foreach (['/reach', '/en/reach', '/reach/v1/tag.js', '/reach/v1/serve?w='.$web->public_key, '/reach/v1/ai-feed?w='.$web->public_key, '/reach/portal'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->actingAs(User::factory()->create())->get('/reach/portal')->assertNotFound();
        auth()->logout();
        $this->on();
        $this->get('/reach/portal')->assertRedirect(route('filament.app.auth.login'));
        config(['modules.enabled.reach' => false]);
        $this->post('/reach/v1/copy', ['w' => $web->public_key])->assertNotFound();
    }

    public function test_landing_is_noindex_and_kept_out_of_sitemap_menus_share_cards_and_llms_txt(): void
    {
        $this->on();
        config(['seo_tools.public' => true]);
        $this->get('/reach?ref=abcd1234')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertSee('AdsRanger Reach')
            ->assertSee('لا يُدفع شيء قبل الإطلاق')->assertSessionHas('reach_ref', 'abcd1234');
        $this->get('/en/reach')->assertOk()->assertSee('No money moves yet');

        // (/glossary/reach is the Reach metric's glossary page, not this product.)
        $own = '#(?<![a-z])/(en/)?reach(/portal)?(?=["<\s?]|$)#m';
        $this->assertDoesNotMatchRegularExpression($own, $this->get('/sitemaps/pages.xml')->assertOk()->getContent());
        $this->assertSame([], array_values(array_intersect(['/reach', '/en/reach'], ShareCard::paths())));
        $this->assertDoesNotMatchRegularExpression($own, json_encode([PublicSite::menu(true), PublicSite::menu(false), PublicSite::toolsMenu()], JSON_UNESCAPED_SLASHES));
        $this->assertDoesNotMatchRegularExpression($own, LlmsTxt::text());
    }

    public function test_web_tag_is_small_async_and_guards_zero_size_frames(): void
    {
        $this->on();
        $js = $this->get('/reach/v1/tag.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')->getContent();
        $this->assertLessThan(5 * 1024, strlen($js), 'web tag must stay under 5 KB');
        $this->assertStringContainsString('innerWidth < 2', $js, '0x0 iframe guard');
        $this->assertStringContainsString("box.style.height = h + 'px'", $js, 'locks the reserved height: no layout shift');
        $this->assertStringContainsString("credentials: 'omit'", $js, 'no cookies');
    }

    public function test_serve_filters_family_safe_blocklist_geo_os_and_ranks_by_page_keywords(): void
    {
        $this->on();
        [$p, $web] = $this->publisher(['blocked_domains' => ['rival.example']]);
        $shoes = ReachOffer::factory()->create(['title' => 'Shoes', 'payout_amount' => 2, 'publisher_payout_amount' => 1.4, 'keywords' => ['احذيه'], 'category' => 'fashion']);
        $big = ReachOffer::factory()->create(['title' => 'Big payout', 'payout_amount' => 30, 'publisher_payout_amount' => 21]);
        ReachOffer::factory()->create(['title' => 'Casino', 'category' => 'gambling']);
        ReachOffer::factory()->create(['title' => 'Rival', 'landing_url' => 'https://shop.rival.example/x']);
        ReachOffer::factory()->create(['title' => 'Kuwait only', 'countries' => ['KW']]);
        ReachOffer::factory()->create(['title' => 'App install', 'offer_type' => 'cpi']);
        ReachOffer::factory()->create(['title' => 'Incent only', 'allow_incent' => true, 'payout_amount' => 1, 'publisher_payout_amount' => 0.5]);

        $ids = Serve::candidates($web, 'SA', 'windows')->pluck('title')->sort()->values()->all();
        $this->assertSame(['Big payout', 'Incent only', 'Shoes'], $ids, 'no gambling (family-safe), no blocked domain, no other country, no CPI on desktop');

        // "أحذية" in the page URL matches the normalised keyword and beats the bigger payout.
        $first = Serve::pick($web, ['country' => 'SA', 'os' => 'windows', 'url' => 'https://blog.example/مقالات/أحذية-رياضية']);
        $this->assertSame($shoes->id, $first[0]->id);
        $again = Serve::pick($web, ['country' => 'SA', 'os' => 'windows', 'url' => 'https://blog.example/مقالات/أحذية-رياضية']);
        $this->assertSame(array_map(fn ($o) => $o->id, $first), array_map(fn ($o) => $o->id, $again), 'deterministic for the same state');

        $res = $this->withHeaders(['CF-IPCountry' => 'SA', 'User-Agent' => self::UA_DESKTOP])
            ->getJson('/reach/v1/serve?w='.$web->public_key.'&u='.urlencode('https://blog.example/news'))
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', '*')->json();
        $this->assertCount(3, $res['offers']);
        $this->assertSame($big->id, $res['offers'][0]['id']);
        $this->assertSame($p->referral_code, $res['badge']);
        $this->assertSame($big->landingHost(), array_search(collect($res['skim'])->first(fn ($u, $h) => $h === $big->landingHost()), $res['skim'], true));
        $this->assertStringContainsString('/reach/v1/click?', $res['skim'][$big->landingHost()], 'auto-skim sends merchant links through a tracked click');

        $p->update(['status' => 'pending_verification']);
        Serve::cache()->flush();
        $this->getJson('/reach/v1/serve?w='.$web->public_key)->assertNotFound();
    }

    public function test_payday_and_meal_time_windows(): void
    {
        $food = ReachOffer::factory()->make(['category' => 'food', 'offer_type' => 'cpl']);
        $shop = ReachOffer::factory()->make(['category' => 'shopping', 'offer_type' => 'cps']);
        $this->assertSame(1.5, Serve::timeBoost($food, Carbon::parse('2027-01-12 12:30', 'Asia/Riyadh')));
        $this->assertSame(0.0, Serve::timeBoost($food, Carbon::parse('2027-01-12 16:30', 'Asia/Riyadh')));
        $this->assertSame(1.0, Serve::timeBoost($shop, Carbon::parse('2027-01-27 16:30', 'Asia/Riyadh')));
        $this->assertSame(0.0, Serve::timeBoost($shop, Carbon::parse('2027-01-12 16:30', 'Asia/Riyadh')));
    }

    public function test_offerwall_firewall_click_subid_copy_and_postback_end_to_end(): void
    {
        $this->on();
        [$p, $web, $wall] = $this->publisher();
        $plain = ReachOffer::factory()->create(['title' => 'Plain', 'external_id' => 'X-1', 'landing_url' => 'https://merchant.example/p?a=1', 'payout_amount' => 10, 'publisher_payout_amount' => 7]);
        $incent = ReachOffer::factory()->create(['title' => 'Rewarded', 'allow_incent' => true, 'offer_type' => 'cpi']);
        foreach (['POOL1', 'POOL2'] as $c) {
            ReachCouponCode::create(['offer_id' => $plain->id, 'code' => $c, 'tier' => 'pool']);
        }

        $wallJson = $this->withHeaders(['User-Agent' => self::UA_ANDROID])->getJson('/reach/v1/offerwall?w='.$wall->public_key)->assertOk()->json();
        $this->assertSame([$incent->id], array_column($wallJson['offers'], 'id'), 'app offerwall carries allow_incent offers only');
        $this->withHeaders(['User-Agent' => self::UA_ANDROID])->get('/reach/v1/offerwall?format=html&w='.$wall->public_key)->assertOk()->assertSee('Rewarded');
        $this->getJson('/reach/v1/offerwall?w='.$web->public_key)->assertNotFound();

        $served = collect($this->withHeaders(['User-Agent' => self::UA_DESKTOP])->getJson('/reach/v1/serve?w='.$web->public_key.'&p=sidebar')->json('offers'))->firstWhere('id', $plain->id);
        $this->get('/reach/v1/click?w='.$web->public_key.'&o='.$plain->id.'&p=sidebar&t=forged')->assertNotFound();
        $this->get($served['click'])->assertRedirect('https://merchant.example/p?a=1&aff_sub='.$p->ref.'_sidebar'); // R-4 preset: Arabclicks reads aff_sub

        $copy = $this->postJson('/reach/v1/copy', ['w' => $web->public_key, 'o' => $plain->id, 'p' => 'sidebar', 't' => $served['t']])->assertOk()->json();
        $this->assertSame('POOL1', $copy['code']);

        [, $token, $secret] = ReachPostbackEndpoint::issue('arabclicks', 'AC');
        $params = ['conversion_id' => 'n-1', 'offer_id' => 'X-1', 'coupon' => 'pool1'];
        $this->get('/reach/v1/postback/'.$token.'?'.http_build_query($params + ['sig' => Postbacks::sign($params, $secret)]))->assertOk()->assertJson(['result' => 'credited']);
        $entry = ReachLedgerEntry::sole();
        $this->assertSame([$p->id, 'pool', 'sidebar', 7.0], [$entry->publisher_id, $entry->attribution_tier, $entry->placement, $entry->net_commission]);
    }

    public function test_human_views_and_ai_agent_reads_are_counted_apart(): void
    {
        $this->on();
        [$p, $web] = $this->publisher();
        $offer = ReachOffer::factory()->create();
        ReachCouponCode::create(['offer_id' => $offer->id, 'code' => 'GPT10', 'tier' => 'ai_bot', 'publisher_id' => $p->id]);

        $this->withHeaders(['User-Agent' => self::UA_DESKTOP])->getJson('/reach/v1/serve?w='.$web->public_key)->assertOk();
        $feed = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; GPTBot/1.2; +https://openai.com/gptbot)'])
            ->getJson('/reach/v1/ai-feed?w='.$web->public_key)->assertOk()->json();
        $this->assertSame('Offer', $feed['itemListElement'][0]['item']['@type']);
        $this->assertStringContainsString('GPT10', $feed['itemListElement'][0]['item']['description']);
        $this->withHeaders(['User-Agent' => 'ClaudeBot/1.0'])->get('/reach/v1/llms/'.$p->ref.'.txt')->assertOk()->assertSee('promo code: GPT10');

        $this->assertSame(['human' => 1, 'ai' => 2, 'agents' => ['GPTBot' => 1, 'ClaudeBot' => 1]], Serve::stats($p->id));

        // An AI-feed link stays valid after the 30-minute impression window.
        $this->travel(2)->hours();
        $this->get($feed['itemListElement'][0]['item']['url'])->assertRedirect();
    }

    public function test_publisher_portal_join_placements_safety_and_wallet_requests(): void
    {
        $this->on();
        $referrer = ReachPublisher::factory()->verified()->create();
        $user = User::factory()->create();
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-reach2', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'ar']);
        $agency->members()->attach($user->id, ['role' => 'owner']);
        $this->actingAs($user);

        $this->get('/reach/portal')->assertOk()->assertSee('انضم إلى')->assertSee('noindex', false);
        $this->post('/reach/portal/join', ['name' => 'مدونة سفر', 'domain_or_app' => 'Travel.example', 'referral' => strtoupper($referrer->referral_code), 'lang' => 'ar'])->assertRedirect('/reach/portal?tab=overview');
        $pub = ReachPublisher::where('user_id', $user->id)->sole();
        $this->assertSame(['pending_verification', 'travel.example', $referrer->id, $agency->id], [$pub->status, $pub->domain_or_app, $pub->referred_by_id, $pub->agency_id]);

        $this->post('/reach/portal/widgets', ['type' => 'web_js', 'placement' => 'in-article', 'lang' => 'en'])->assertRedirect('/en/reach/portal?tab=placements');
        $this->get('/en/reach/portal?tab=placements')->assertOk()->assertSee('data-reach=&quot;rw_', false)->assertSee('min-height:160px', false);
        $tooMany = implode("\n", array_map(fn ($i) => "d$i.example", range(1, 21)));
        $this->post('/reach/portal/settings', ['blocked_domains' => $tooMany, 'family_safe' => '1'])->assertSessionHasErrors('blocked_domains');
        $this->post('/reach/portal/settings', ['blocked_domains' => "https://www.Rival.example/\nnot a domain", 'family_safe' => '0'])->assertRedirect();
        $this->assertSame(['rival.example'], $pub->fresh()->blocked_domains);
        $this->assertFalse($pub->fresh()->family_safe);

        $this->post('/reach/portal/wallet/withdraw', ['amount' => '60'])->assertNotFound(); // not verified yet
        $pub->update(['status' => 'verified']);
        $this->post('/reach/portal/wallet/withdraw', ['amount' => '٦٠'])->assertRedirect()->assertSessionHas('reach_status', __('reach_ui.portal.wallet_error.over_balance'));
        $this->post('/reach/portal/wallet/early-cashout')->assertRedirect()->assertSessionHas('reach_status', __('reach_ui.portal.wallet_error.nothing_pending'));
        $this->get('/reach/portal/stats')->assertOk()->assertJsonStructure(['today' => ['human', 'ai', 'agents'], 'week']);
        $this->get('/reach/portal?tab=wallet')->assertOk()->assertSee('تقدير');
        $this->get('/reach/portal')->assertOk()->assertSee('<bdi dir="ltr">GPTBot</bdi>', false);
    }

    public function test_agency_surfaces_never_carry_reach_tags_or_links(): void
    {
        foreach (['client-report/show', 'client-report/ads', 'client-report/_style', 'reports/monthly-pdf', 'public/lead-desk'] as $view) {
            $src = file_get_contents(resource_path('views/'.$view.'.blade.php'));
            $this->assertStringNotContainsString('data-reach', $src, $view);
            $this->assertStringNotContainsString('/reach', $src, $view);
            $this->assertStringNotContainsStringIgnoringCase('Reach\\', $src, $view);
        }
    }
}
