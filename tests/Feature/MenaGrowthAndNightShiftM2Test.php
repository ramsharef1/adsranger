<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientUnitEconomic;
use App\Models\User;
use App\Support\AgencyOps\PitchDeckBuilder;
use App\Support\AiWorkspace\NightShiftActionQueue;
use App\Support\Attribution\CreatorPoasTracker;
use App\Support\Attribution\OfflineConversionDispatcher;
use App\Support\LandingPages;
use App\Support\Seo\SafeFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MenaGrowthAndNightShiftM2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SafeFetcher::$resolver = fn (string $host) => str_ends_with($host, '.pitch-test.com') ? '93.184.216.34' : LandingPages::publicIp($host);
    }

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    private function workspace(): array
    {
        $agency = Agency::create([
            'name' => 'Gulf Growth Partners',
            'slug' => 'gulf-growth-partners',
            'plan' => 'pro',
            'status' => 'active',
            'timezone' => 'Asia/Riyadh',
            'language' => 'en',
        ]);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Jeddah Perfumes',
            'currency' => 'KWD',
            'status' => 'active',
        ]);

        return [$agency, $owner, $client];
    }

    public function test_creator_partnership_poas_accounts_for_flat_fee_gifted_cogs_boosted_spend_and_cod_returns(): void
    {
        [, , $client] = $this->workspace();

        ClientUnitEconomic::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'cogs_pct' => 30.0,
            'shipping_fee' => 2.0,
            'return_shipping_fee' => 3.0,
            'cod_share_pct' => 50.0,
            'cod_return_rate_pct' => 20.0,
            'costs_entered_at' => now(),
        ]);

        // Effective COD return share = 50% * 20% = 10%
        // Gross revenue = 2,000 KWD -> Realized = 1,800 KWD
        // COGS (30% of 1,800) = 540 KWD
        // Shipping = 20*2 + (20*0.1)*3 = 40 + 6 = 46 KWD
        // Net contribution before creator cost = 1,800 - 540 - 46 = 1,214 KWD
        // Total creator cost = 250 + 50 + 200 = 500 KWD -> Net POAS = 1214 / 500 = 2.428
        $partnership = CreatorPoasTracker::recordPartnership($client, [
            'creator_name' => 'سارة العتيبي',
            'platform' => 'snapchat',
            'flat_fee' => 250.0,
            'gifted_cogs' => 50.0,
            'boosted_spend' => 200.0,
            'promo_code' => 'sara15',
            'utm_campaign' => 'snap_ramadan_sara',
            'attributed_orders' => 20,
            'gross_revenue' => 2000.0,
        ]);

        $this->assertSame('SARA15', $partnership->promo_code);
        $this->assertEqualsWithDelta(500.0, $partnership->totalInvestment(), 0.001);
        $this->assertEqualsWithDelta(2.428, $partnership->net_poas, 0.002);
    }

    public function test_pitch_deck_gates_agency_ops_hub_exports_bilingual_pdf_with_zero_brand_or_verdict_leaks(): void
    {
        [$agency] = $this->workspace();

        Http::fake([
            'https://shop.pitch-test.com/' => Http::response(
                '<!doctype html><html><head>'
                .'<title>Perfume Store</title>'
                .'<meta name="viewport" content="width=device-width, initial-scale=1">'
                .'<script src="/blocking.js"></script>'
                ."<script>fbq('init','999');</script>"
                .'<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization"}</script>'
                .'</head><body><h1>Welcome</h1></body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
            'https://shop.pitch-test.com/llms.txt' => Http::response("# Perfume Store\nCatalog summary", 200, ['Content-Type' => 'text/plain']),
        ]);

        $deck = PitchDeckBuilder::generate($agency, 'Noor Scents', 'https://shop.pitch-test.com/', 'KWD');
        $this->assertTrue($deck->audit_snapshot['reachable']);
        $this->assertContains('meta_pixel', $deck->audit_snapshot['detected_pixels']);
        $this->assertContains('google_tag', $deck->audit_snapshot['missing_pixels']);
        $this->assertSame(1, $deck->audit_snapshot['render_blocking_scripts']);
        $this->assertTrue($deck->audit_snapshot['has_json_ld']);
        $this->assertTrue($deck->audit_snapshot['has_llms_txt']);
        $this->assertCount(3, $deck->tier_quotes);

        // Module agency_ops_hub is off by default -> 404 on both preview and PDF
        $this->get('/pitch/'.$deck->share_token)->assertNotFound();
        $this->get('/pitch/'.$deck->share_token.'/pdf')->assertNotFound();

        config(['modules.preview_agencies' => [$agency->slug]]);

        $preview = $this->get('/pitch/'.$deck->share_token.'?lang=both')->assertOk();
        $this->assertSame('noindex, nofollow', $preview->headers->get('X-Robots-Tag'));
        $html = (string) $preview->getContent();
        $this->assertStringContainsString('Gulf Growth Partners', $html);
        $this->assertStringContainsString('Noor Scents', $html);
        $this->assertStringContainsString('450.000 KWD', $html);
        $this->assertStringNotContainsStringIgnoringCase('AdsRanger', $html);
        $this->assertStringNotContainsStringIgnoringCase('Reach', $html);
        foreach (['compliant', 'certified', 'approved', 'semrush', 'ahrefs', 'moz'] as $banned) {
            $this->assertStringNotContainsStringIgnoringCase($banned, $html);
        }

        $pdfRes = $this->get('/pitch/'.$deck->share_token.'/pdf?lang=both')->assertOk();
        $this->assertSame('application/pdf', $pdfRes->headers->get('Content-Type'));
        $pdfBytes = (string) $pdfRes->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertStringNotContainsStringIgnoringCase('AdsRanger', $pdfBytes);
    }

    public function test_offline_conversion_loop_stays_queued_without_credentials_and_sends_hashed_payload_when_configured(): void
    {
        [$agency, $owner, $client] = $this->workspace();

        Http::fake([
            'https://graph.facebook.com/v20.0/*' => Http::response(['events_received' => 1], 200),
            'https://googleads.googleapis.com/v17/customers/*' => Http::response(['results' => [['gclid' => 'EAIaIQobChMI']]], 200),
        ]);

        // Without live credentials -> strictly queued_until_credentials, zero HTTP sent
        $queued = OfflineConversionDispatcher::queueOrSend($client, [
            'lead_identifier' => 'CRM-DEAL-101',
            'deal_stage' => 'qualified',
            'deal_value' => 350.5,
            'ctwa_clid' => 'ctwa_abc_999',
            'phone' => '+965 5001 2345',
            'email' => 'VIP@Example.com',
            'target_network' => 'meta_capi',
        ]);

        $this->assertSame('queued_until_credentials', $queued->status);
        $this->assertSame(hash('sha256', '96550012345'), $queued->hashed_phone);
        $this->assertSame(hash('sha256', 'vip@example.com'), $queued->hashed_email);
        Http::assertNothingSent();

        // With Meta CAPI credentials -> sent via contract
        $sentMeta = OfflineConversionDispatcher::queueOrSend(
            $client,
            [
                'lead_identifier' => 'CRM-DEAL-102',
                'deal_stage' => 'won',
                'deal_value' => 920.75,
                'ctwa_clid' => 'ctwa_won_777',
                'phone' => '0096550012345',
                'target_network' => 'meta_capi',
            ],
            [
                'pixel_id' => '1234567890',
                'access_token' => 'EAAB_LIVE_TOKEN',
            ]
        );
        $this->assertSame('sent', $sentMeta->status);

        // With Google Ads Offline credentials -> sent via contract
        $sentGoogle = OfflineConversionDispatcher::queueOrSend(
            $client,
            [
                'lead_identifier' => 'CRM-DEAL-103',
                'deal_stage' => 'won',
                'deal_value' => 1500.0,
                'gclid' => 'EAIaIQobChMI',
                'email' => 'buyer@client.kw',
                'target_network' => 'google_offline',
            ],
            [
                'customer_id' => '123-456-7890',
                'conversion_action' => 'customers/1234567890/conversionActions/555',
                'access_token' => 'ya29.live_token',
                'developer_token' => 'dev_tok_123',
            ]
        );
        $this->assertSame('sent', $sentGoogle->status);
        Http::assertSentCount(2);

        // Module lead_desk off -> 403 on Filament page; preview agency -> 200
        $this->actingAs($owner)->get('/app/'.$agency->slug.'/offline-conversions')->assertForbidden();
        config(['modules.preview_agencies' => [$agency->slug]]);
        $this->actingAs($owner)
            ->withSession(['locale' => 'en'])
            ->get('/app/'.$agency->slug.'/offline-conversions')
            ->assertOk()
            ->assertSee('CRM-DEAL-101');
    }

    public function test_night_shift_v1_supervised_action_queue_clamps_deltas_supports_confirm_reject_and_undo(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        config(['modules.preview_agencies' => [$agency->slug]]);

        $action = NightShiftActionQueue::propose(
            $client,
            'adjust_budget_pct',
            ['campaign' => 'Search_Perfumes', 'delta_pct' => -45],
            'CPA rose 38% over 7-day baseline while stock fell to 3 units.'
        );

        $this->assertSame('pending_approval', $action->status);
        $this->assertEqualsWithDelta(-20.0, $action->payload['delta_pct'], 0.001);

        $confirmed = NightShiftActionQueue::decide($action, $owner, 'queue');
        $this->assertSame('approved_queued', $confirmed->status);
        $this->assertSame($owner->id, $confirmed->decided_by);
        $this->assertNotNull($confirmed->decided_at);

        $undone = NightShiftActionQueue::undo($confirmed, $owner);
        $this->assertSame('undone', $undone->status);

        $second = NightShiftActionQueue::propose($client, 'pause_ad', ['ad_id' => 'ad_91'], 'Zero purchases after 450 KWD.');
        $rejected = NightShiftActionQueue::decide($second, $owner, 'reject');
        $this->assertSame('rejected', $rejected->status);

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar'])
            ->get('/app/'.$agency->slug.'/ai-night-shift')
            ->assertOk()
            ->assertSee('طابور الإجراءات الصباحية الخاضع للإشراف')
            ->assertDontSee('approve');
    }
}
