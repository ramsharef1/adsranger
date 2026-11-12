<?php

namespace Tests\Feature;

use App\Filament\Pages\AeoGeoHubPage;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\AeoCitationRun;
use App\Models\AeoTrackedPrompt;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Connection;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\CurrentClient;
use App\Support\Modules;
use App\Support\Seo\AeoGeoManager;
use App\Support\Seo\SafeFetcher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WaveB1AeoGeoSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    private function createWorkspace(string $slug, string $plan = 'agency', string $status = 'active', string $source = 'admin'): array
    {
        $owner = User::factory()->create();
        $agency = Agency::create([
            'name' => 'Agency '.$slug,
            'slug' => $slug,
            'plan' => $plan,
            'status' => $status,
            'signup_source' => $source,
            'owner_id' => $owner->id,
            'language' => 'en',
        ]);
        DB::table('agency_user')->where('user_id', $owner->id)->delete();
        $agency->members()->attach($owner->id, ['role' => 'owner']);

        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Oasis Brand '.$slug,
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        return [$agency, $owner, $client];
    }

    public function test_aeo_geo_hub_is_held_back_by_default_and_opens_when_organic_module_is_on(): void
    {
        $this->assertFalse(Modules::enabled('organic'));
        [$agency, $owner] = $this->createWorkspace('held-aeo');

        $this->assertContains($this->actingAs($owner)->get("/app/{$agency->slug}/aeo-geo-hub")->status(), [403, 404]);

        config(['modules.preview_agencies' => ['held-aeo']]);
        $this->actingAs($owner)->get("/app/{$agency->slug}/aeo-geo-hub")->assertOk();
        $this->actingAs($owner)->get("/app/{$agency->slug}/aeo-geo-hub?lang=ar")->assertOk()
            ->assertSee('الظهور في محركات الإجابة الذكية');
    }

    public function test_181_182_183_187_citation_tracker_d229_deterministic_honesty_share_of_model_referrals_and_gaps(): void
    {
        [$agencyA, $ownerA, $clientA] = $this->createWorkspace('tenant-a');
        [$agencyB, $ownerB, $clientB] = $this->createWorkspace('tenant-b');

        $promptA = AeoTrackedPrompt::create([
            'agency_id' => $agencyA->id,
            'client_id' => $clientA->id,
            'prompt_text' => 'Compare Oasis Brand tenant-a vs RivalOne in Riyadh',
            'locale' => 'en',
            'funnel_stage' => 'comparison',
            'competitors_json' => ['RivalOne', 'RivalTwo'],
        ]);

        $runs = AeoGeoManager::runCitationCheck($promptA, ['chatgpt', 'perplexity']);
        $this->assertCount(2, $runs);
        foreach ($runs as $r) {
            $this->assertSame('deterministic', $r->mode);
            $this->assertStringContainsString('not connected yet', $r->response_excerpt);
            $this->assertTrue($r->client_mentioned);
            $this->assertContains('RivalOne', $r->competitor_mentions_json);
        }

        AeoCitationRun::create([
            'agency_id' => $agencyA->id,
            'client_id' => $clientA->id,
            'aeo_tracked_prompt_id' => $promptA->id,
            'engine' => 'gemini',
            'mode' => 'live',
            'client_mentioned' => false,
            'sentiment' => 'neutral',
            'competitor_mentions_json' => ['RivalOne'],
            'cited_urls_json' => ['https://g2.com/categories/mena-software', 'https://oasisbrand.sa/pricing'],
            'response_excerpt' => 'RivalOne listed on G2.',
            'checked_at' => now(),
        ]);

        $promptB = AeoTrackedPrompt::create([
            'agency_id' => $agencyB->id,
            'client_id' => $clientB->id,
            'prompt_text' => 'Best agency in Dubai',
            'locale' => 'en',
            'competitors_json' => ['OtherComp'],
        ]);
        AeoCitationRun::create([
            'agency_id' => $agencyB->id,
            'client_id' => $clientB->id,
            'aeo_tracked_prompt_id' => $promptB->id,
            'engine' => 'claude',
            'mode' => 'live',
            'client_mentioned' => false,
            'competitor_mentions_json' => ['OtherComp'],
            'cited_urls_json' => ['https://clutch.co/ae/agencies'],
            'checked_at' => now(),
        ]);

        $matrixA = AeoGeoManager::shareOfModelMatrix($clientA);
        $this->assertSame(3, $matrixA['total_runs']);
        $this->assertSame(1, $matrixA['live_runs']);
        $this->assertSame(2, $matrixA['deterministic_runs']);
        $this->assertSame(66.7, $matrixA['client_share_pct']);

        $gapsA = AeoGeoManager::findCitationGaps($clientA, 'https://oasisbrand.sa');
        $this->assertCount(1, $gapsA);
        $this->assertSame('g2.com', $gapsA[0]['domain']);
        $this->assertNotContains('clutch.co', array_column($gapsA, 'domain'));

        $emptyRef = AeoGeoManager::llmReferralSlice($clientA);
        $this->assertSame('no_synced_rows', $emptyRef['status']);
        $this->assertSame(0, $emptyRef['totals']['sessions']);

        $net = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta Ads', 'category' => 'social', 'stage' => 1, 'auth_type' => 'oauth', 'is_active' => true]);
        $conn = Connection::create(['agency_id' => $agencyA->id, 'ad_network_id' => $net->id, 'status' => 'active']);
        $acct = AdAccount::create([
            'agency_id' => $agencyA->id, 'client_id' => $clientA->id, 'ad_network_id' => $net->id, 'connection_id' => $conn->id,
            'external_id' => 'act_aeo', 'name' => 'AEO Acct', 'currency' => 'SAR', 'is_active' => true,
        ]);
        DailyMetric::create([
            'agency_id' => $agencyA->id, 'client_id' => $clientA->id, 'ad_account_id' => $acct->id,
            'date' => now()->toDateString(), 'network' => 'perplexity', 'currency' => 'SAR',
            'spend' => 0, 'impressions' => 0, 'clicks' => 42, 'results' => 5, 'revenue' => 1850,
        ]);

        $liveRef = AeoGeoManager::llmReferralSlice($clientA->fresh());
        $this->assertSame('live', $liveRef['status']);
        $this->assertSame(42, $liveRef['by_engine']['perplexity.ai']['sessions']);
        $this->assertSame(5, $liveRef['by_engine']['perplexity.ai']['conversions']);
        $this->assertSame(1850.0, $liveRef['by_engine']['perplexity.ai']['revenue']);
    }

    public function test_184_185_186_188_189_190_191_192_seo_depth_engines_and_ssrf_guard(): void
    {
        [$agency, $owner, $client] = $this->createWorkspace('depth-co');

        SafeFetcher::$resolver = fn () => null;
        $blockedAudit = AeoGeoManager::auditAiCrawlers('http://169.254.169.254/');
        $this->assertFalse($blockedAudit['ok']);
        $this->assertSame('blocked', $blockedAudit['error']);
        $this->assertNull(AeoGeoManager::runAndSaveDomainAudit($client, 'http://127.0.0.1/'));

        SafeFetcher::$resolver = fn () => '93.184.216.34';
        $robots = "User-agent: GPTBot\nDisallow: /\n\nUser-agent: PerplexityBot\nAllow: /\n\nUser-agent: Googlebot\nAllow: /\n";
        $homeHtml = '<!doctype html><html lang="ar" dir="rtl"><head><script type="application/ld+json">{"@type":"FAQPage"}</script></head>'
            .'<body><h1>منصة إدارة حملات التجارة الإلكترونية</h1>'
            .'<p>تساعد المنصة فرق التسويق في الرياض ودبي وعمّان على توحيد تقارير الإعلانات وتحليل تكلفة الاكتساب بدقة يومية واضحة وربط الأهداف مباشرة بنمو المبيعات الفعلي خلال أقل من أسبوع واحد من التفعيل الأولي.</p>'
            .'<a href="/features">اضغط هنا</a><a href="https://oasis.example/blog">مدونة التسويق</a></body></html>';

        Http::fake([
            'https://oasis.example/robots.txt' => Http::response($robots, 200, ['Content-Type' => 'text/plain']),
            'https://oasis.example/llms.txt' => Http::response("# Oasis\n\n> Summary\n", 200, ['Content-Type' => 'text/plain']),
            'https://oasis.example/' => Http::response($homeHtml, 200, ['Content-Type' => 'text/html', 'X-Robots-Tag' => 'all']),
        ]);

        $llms = AeoGeoManager::generateAndValidateLlmsTxt($client, 'https://oasis.example');
        $this->assertStringContainsString('# Oasis Brand depth-co', $llms['llms_txt']);
        $this->assertStringContainsString('### Oasis Brand depth-co — Home', $llms['llms_full_txt']);
        $this->assertTrue($llms['live_validation']['valid_markdown']);

        $crawlers = AeoGeoManager::auditAiCrawlers('https://oasis.example');
        $this->assertTrue($crawlers['ok']);
        $this->assertSame('blocked', $crawlers['bots']['GPTBot']);
        $this->assertSame('allowed', $crawlers['bots']['PerplexityBot']);
        $this->assertSame('allowed', $crawlers['bots']['Googlebot']);
        $this->assertFalse($crawlers['x_robots_blocks_ai']);

        $fanOut = AeoGeoManager::generateQueryFanOut('إدارة إعلانات المتاجر', 'ar');
        $this->assertSame('اداره اعلانات المتاجر', $fanOut['normalized_key']);
        $this->assertCount(6, $fanOut['prompts']);

        $schema = AeoGeoManager::buildEntitySchema('LocalBusiness', [
            'name' => 'Oasis Digital',
            'url' => 'https://oasis.example',
            'description' => 'MENA agency',
            'city' => 'Riyadh',
            'country' => 'SA',
            'same_as' => ['https://www.linkedin.com/company/oasis', 'https://x.com/oasis'],
        ]);
        $this->assertTrue($schema['valid']);
        $this->assertCount(2, $schema['data']['sameAs']);

        $snip = AeoGeoManager::rewriteAnswerFirstSnippet('https://oasis.example/', 'ما هي المنصة؟', 'ar');
        $this->assertTrue($snip['ok']);
        $this->assertGreaterThanOrEqual(80, $snip['readiness_score']);
        $this->assertNotEmpty($snip['draft_ar']);

        $cann = AeoGeoManager::detectCannibalizationAndDecay(
            [
                ['query' => 'شركة تسويق إلكتروني', 'url' => 'https://oasis.example/ar/seo', 'clicks' => 80, 'impressions' => 1200],
                ['query' => 'شركه تسويق الكتروني', 'url' => 'https://oasis.example/blog/agency', 'clicks' => 55, 'impressions' => 950],
            ],
            [
                ['url' => 'https://oasis.example/blog/old-guide', 'previous_clicks' => 200, 'current_clicks' => 120],
                ['url' => 'https://oasis.example/ar/seo', 'previous_clicks' => 100, 'current_clicks' => 90],
            ]
        );
        $this->assertCount(1, $cann['cannibalized']);
        $this->assertCount(2, $cann['cannibalized'][0]['urls']);
        $this->assertCount(1, $cann['decaying_pages']);
        $this->assertSame(40.0, $cann['decaying_pages'][0]['drop_pct']);

        $links = AeoGeoManager::auditInternalLinksAndAnchors(
            ['https://oasis.example/'],
            ['https://oasis.example/pricing', 'https://oasis.example/blog']
        );
        $this->assertTrue($links['ok']);
        $this->assertContains('https://oasis.example/pricing', $links['orphans']);
        $this->assertNotContains('https://oasis.example/blog', $links['orphans']);
        $this->assertSame('اضغط هنا', $links['weak_anchors'][0]['anchor']);

        $brief = AeoGeoManager::buildComparisonBrief('AdsRanger', 'Supermetrics', 'Riyadh', 'en', 'vs');
        $this->assertStringContainsString('AdsRanger vs Supermetrics', $brief['h1']);
        $this->assertCount(4, $brief['h2_sections']);
    }

    public function test_readonly_guard_allows_previews_and_blocks_writes_on_expired_trial(): void
    {
        config(['modules.enabled.organic' => true]);
        [$agency, $owner, $client] = $this->createWorkspace('ro-aeo', 'starter', 'trial', 'self_signup');
        $agency->forceFill(['trial_ends_at' => now()->subDays(2)])->save();

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        if (method_exists(CurrentClient::class, 'set')) {
            CurrentClient::set($client);
        }

        $lw = Livewire::test(AeoGeoHubPage::class)
            ->set('fanOutSeed', 'إعلانات جوجل')
            ->call('previewFanOut');
        $this->assertNotEmpty($lw->get('fanOutResults'));

        $lw->set('promptText', 'Should not be saved in read-only mode')
            ->set('promptLocale', 'en')
            ->set('funnelStage', 'decision')
            ->call('addTrackedPrompt');

        $this->assertDatabaseMissing('aeo_tracked_prompts', [
            'agency_id' => $agency->id,
            'prompt_text' => 'Should not be saved in read-only mode',
        ]);
    }
}
