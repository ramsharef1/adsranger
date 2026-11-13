<?php

namespace Tests\Feature;

use App\Filament\Pages\AeoCitationRadarPage;
use App\Models\AeoBrandMonitor;
use App\Models\AeoCitationCheck;
use App\Models\Agency;
use App\Models\AiKey;
use App\Models\Client;
use App\Models\User;
use App\Support\Seo\AeoCitationRadar;
use App\Support\Seo\SafeFetcher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** E2-065 AEO / GEO radar: deterministic readiness, own-key probes under the monthly cap, no citation rate without probes. */
class AeoGeoCitationRadarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SafeFetcher::$resolver = fn (string $host) => '93.184.216.34';
    }

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    /** @return array{0: Agency, 1: User, 2: Client} */
    private function workspace(string $slug, string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $agency = Agency::create(['name' => 'Agency '.$slug, 'slug' => $slug, 'plan' => 'pro', 'status' => 'active', 'signup_source' => 'admin', 'owner_id' => $user->id, 'language' => 'ar']);
        DB::table('agency_user')->where('user_id', $user->id)->delete();
        $agency->members()->attach($user->id, ['role' => $role]);

        return [$agency, $user, Client::create(['agency_id' => $agency->id, 'name' => 'Client '.$slug, 'currency' => 'SAR', 'status' => 'active'])];
    }

    private function monitor(Client $client, array $prompts = []): AeoBrandMonitor
    {
        return AeoBrandMonitor::create(['agency_id' => $client->agency_id, 'client_id' => $client->id, 'brand_name_ar' => 'عطور الواحة', 'brand_name_en' => 'Oasis Perfumes', 'primary_domain' => 'oasis.test', 'tracked_prompts' => $prompts]);
    }

    private function fakeSite(): void
    {
        $org = '{"@context":"https://schema.org","@graph":[{"@type":"Organization","name":"عطور الواحة","alternateName":"Oasis Perfumes"},{"@type":"FAQPage","mainEntity":[]},{"@type":"Product","name":"عود"}]}';
        Http::fake([
            'oasis.test/robots.txt' => Http::response("User-agent: GPTBot\nDisallow: /\n\nUser-agent: *\nAllow: /\n", 200, ['Content-Type' => 'text/plain']),
            'oasis.test/llms.txt' => Http::response("# عطور الواحة\n\n> متجر عطور", 200, ['Content-Type' => 'text/plain']),
            'oasis.test/en/' => Http::response('<html lang="en"><head><title>Oasis Perfumes</title></head><body><h1>Oasis Perfumes</h1></body></html>', 200, ['Content-Type' => 'text/html']),
            'oasis.test/' => Http::response('<html lang="ar" dir="rtl"><head><title>عطور الواحة</title><link rel="alternate" hreflang="en" href="https://oasis.test/en/"><script type="application/ld+json">'.$org.'</script></head><body><h1>عطور الواحة</h1></body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
    }

    public function test_readiness_is_deterministic_from_robots_llms_schema_and_parity(): void
    {
        $this->fakeSite();
        [, , $client] = $this->workspace('aeo-ready');
        $m = $this->monitor($client);

        $check = AeoCitationRadar::readiness($m);
        $met = $check->signals['met'];
        $this->assertSame('static_readiness', $check->source);
        $this->assertSame(['GPTBot'], $check->signals['robots']['blocked']);
        $this->assertTrue($met['llms_txt'] && $met['organization'] && $met['faq'] && $met['offering'] && $met['bilingual'] && $met['brand_ar'] && $met['brand_en'] && $met['org_names']);
        $this->assertSame(98, $check->readiness_score, '25 × 10/11 crawlers + 75 for the rest');
        $this->assertNull($check->citations, 'a readiness check carries no citations');
        $this->assertSame($check->readiness_score, AeoCitationRadar::readiness($m)->readiness_score, 'same site, same score');
        $this->assertNull(AeoCitationRadar::rates(null));

        Http::fake(['bare.test/*' => Http::response('', 404), 'bare.test/' => Http::response('<html><body>hi</body></html>', 200, ['Content-Type' => 'text/html'])]);
        $bare = AeoBrandMonitor::create(['agency_id' => $client->agency_id, 'client_id' => Client::create(['agency_id' => $client->agency_id, 'name' => 'Bare', 'currency' => 'SAR', 'status' => 'active'])->id, 'brand_name_en' => 'Bare', 'primary_domain' => 'https://bare.test/x', 'tracked_prompts' => []]);
        $this->assertSame(25, AeoCitationRadar::readiness($bare)->readiness_score, 'only the no-robots.txt allowance');
        $this->assertNull(AeoCitationRadar::origin('localhost'));
    }

    public function test_probe_uses_the_own_key_records_mentions_and_respects_the_cap(): void
    {
        [$agency, , $client] = $this->workspace('aeo-probe');
        $m = $this->monitor($client, ['ما أفضل متجر عطور في الرياض؟', 'Best oud shop in Jeddah?']);
        $key = AiKey::create(['agency_id' => $agency->id, 'provider' => 'openai', 'api_key' => 'sk-test-abcdefghijklmnop', 'last4' => 'mnop', 'model' => 'gpt-4.1-mini', 'monthly_cap_usd' => 5, 'test_ok' => true]);
        $reply = fn (array $j) => ['choices' => [['message' => ['content' => json_encode($j, JSON_UNESCAPED_UNICODE)]]], 'usage' => ['prompt_tokens' => 300, 'completion_tokens' => 200]];
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push($reply(['answer' => 'جرّب عطور الواحه ومتجر آخر', 'brands' => ['متجر آخر', 'عطور الواحة'], 'sources' => ['https://www.oasis.test/ar']]))
            ->push($reply(['answer' => 'Try Other Oud House.', 'brands' => ['Other Oud House'], 'sources' => []]))]);

        $probe = AeoCitationRadar::probe($m, $key);
        $this->assertSame('byo_probe', $probe->source);
        $this->assertNull($probe->readiness_score);
        $this->assertSame(['answered' => 2, 'mentioned' => 1, 'cited' => 1], AeoCitationRadar::rates($probe));
        $this->assertSame(2, $probe->citations[0]['position']);
        $this->assertTrue($probe->citations[0]['mentioned'], 'Arabic match folds taa marbuta / haa');
        $this->assertFalse($probe->citations[1]['mentioned']);
        $this->assertGreaterThan(0, $key->fresh()->spentThisMonth());
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk-test-abcdefghijklmnop'));

        // Cap reached: no call goes out, nothing is invented.
        $key->forceFill(['monthly_cap_usd' => 0.0001])->save();
        Http::fake();
        $stopped = AeoCitationRadar::probe($m, $key->fresh());
        $this->assertSame([], $stopped->citations);
        $this->assertNotNull($stopped->signals['error']);
        $this->assertNull(AeoCitationRadar::rates($stopped));
        Http::assertNothingSent();
    }

    public function test_screen_is_held_by_seo_audit_and_shows_no_rate_without_probes(): void
    {
        [$agency, $owner, $client] = $this->workspace('aeo-ui');
        $this->assertContains($this->actingAs($owner)->get("/app/{$agency->slug}/aeo-radar")->status(), [403, 404]);

        config(['modules.preview_agencies' => ['aeo-ui']]);
        $this->fakeSite();
        $this->actingAs($owner);
        Filament::setTenant($agency);
        Livewire::test(AeoCitationRadarPage::class)
            ->set('brandAr', 'عطور الواحة')->set('brandEn', 'Oasis Perfumes')->set('domain', 'not a domain')->call('saveMonitor')->assertHasErrors(['domain'])
            ->set('domain', 'https://Oasis.test/ar')->set('promptsText', "سؤال 1\n\nسؤال 2")->call('saveMonitor')->assertHasNoErrors()
            ->call('runReadiness')
            ->call('runProbe');
        $m = AeoBrandMonitor::sole();
        $this->assertSame(['oasis.test', ['سؤال 1', 'سؤال 2']], [$m->primary_domain, $m->tracked_prompts]);
        $this->assertSame(1, AeoCitationCheck::where('source', 'static_readiness')->count());
        $this->assertSame(0, AeoCitationCheck::where('source', 'byo_probe')->count(), 'no own key → no probe');

        $this->actingAs($owner)->get("/app/{$agency->slug}/aeo-radar?lang=ar")->assertOk()
            ->assertSee('درجة الجاهزية')->assertSee('98')->assertSee('لم تُفحص أي إجابات بعد، فلا توجد نسبة استشهاد.')->assertSee('زواحف محظورة');

        $analyst = User::factory()->create();
        DB::table('agency_user')->where('user_id', $analyst->id)->delete();
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);
        $this->actingAs($analyst);
        Livewire::test(AeoCitationRadarPage::class)->call('runReadiness');
        $this->assertSame(1, AeoCitationCheck::count(), 'analysts view only');
    }
}
