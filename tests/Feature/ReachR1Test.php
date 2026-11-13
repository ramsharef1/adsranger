<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ReachControlBoard;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ReachAdvertiserBilling;
use App\Models\ReachCouponCode;
use App\Models\ReachLedgerEntry;
use App\Models\ReachOffer;
use App\Models\ReachPostbackEndpoint;
use App\Models\ReachPublisher;
use App\Models\User;
use App\Support\Reach\Attribution;
use App\Support\Reach\OfferImporter;
use App\Support\Reach\Postbacks;
use App\Support\Reach\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** FR-REACH R-1: module gate, AR/EN CSV import, S2S postbacks, 3-tier attribution, estimate wallet, /admin board. */
class ReachR1Test extends TestCase
{
    use RefreshDatabase;

    private function on(): void
    {
        config(['modules.enabled.reach' => true]);
    }

    /** @return array{0: ReachPostbackEndpoint, 1: string, 2: string} */
    private function endpoint(array $ips = []): array
    {
        return ReachPostbackEndpoint::issue('arabclicks', 'Arabclicks main', $ips);
    }

    private function signed(string $token, string $secret, array $params): string
    {
        return '/reach/v1/postback/'.$token.'?'.http_build_query($params + ['sig' => Postbacks::sign($params, $secret)]);
    }

    public function test_everything_is_inert_while_the_module_is_off(): void
    {
        [, $token, $secret] = $this->endpoint();
        $this->get($this->signed($token, $secret, ['conversion_id' => 'c1']))->assertNotFound();
        $this->actingAs(User::factory()->create())->get('/admin/reach')->assertForbidden();
        $this->assertFalse(config('modules.enabled.reach'));
    }

    public function test_csv_import_reads_arabic_headers_and_eastern_digits(): void
    {
        $csv = "العنوان,الشبكة,رقم العرض,العمولة,النوع,الكوبون,الرابط,الدول\n"
            ."نون - إلكترونيات,arabclicks,A-1,١٢٫٥,cps,NOON10|NOON11|NOON12,https://noon.example/,SA AE\n"
            ."بلا عمولة,arabclicks,A-2,,cps,,,\n"
            ."Talabat,admitad,B-7,\"1,250.75\",cpl,,http://insecure.example/,\n";
        $r = OfferImporter::importCsv($csv);

        $this->assertSame(1, $r['created']);
        $this->assertSame(3, $r['codes']);
        $this->assertSame(['3' => 'bad_payout', '4' => 'bad_url'], array_map('strval', $r['errors']));
        $offer = ReachOffer::where('external_id', 'A-1')->firstOrFail();
        $this->assertSame(12.5, $offer->payout_amount);
        $this->assertSame(8.75, $offer->publisher_payout_amount, 'default publisher share is 70%');
        $this->assertSame(['SA', 'AE'], $offer->countries);
        $this->assertSame(3, $offer->codes()->where('tier', 'pool')->count());

        // Same network + offer id updates instead of duplicating.
        $again = OfferImporter::importCsv("title,network,offer_id,payout,publisher_payout\nNoon,arabclicks,A-1,20,15\n");
        $this->assertSame(1, $again['updated']);
        $this->assertSame(15.0, $offer->fresh()->publisher_payout_amount);
        $this->assertSame(1, ReachOffer::count());
    }

    public function test_postback_trust_rules(): void
    {
        $this->on();
        $publisher = ReachPublisher::factory()->verified()->create();
        ReachOffer::factory()->create(['external_id' => 'A-1', 'payout_amount' => 10, 'publisher_payout_amount' => 7]);
        [, $token, $secret] = $this->endpoint();
        $params = ['conversion_id' => 'c1', 'offer_id' => 'A-1', 'subid' => $publisher->ref.'_sidebar'];

        $this->get('/reach/v1/postback/'.$token.'?'.http_build_query($params))->assertForbidden()->assertJson(['result' => 'unsigned']);
        $this->get('/reach/v1/postback/'.$token.'?'.http_build_query($params + ['sig' => str_repeat('a', 64)]))->assertForbidden()->assertJson(['result' => 'bad_signature']);
        $this->get('/reach/v1/postback/'.str_repeat('x', 40))->assertNotFound();

        $this->get($this->signed($token, $secret, $params))->assertOk()->assertJson(['result' => 'credited'])
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get($this->signed($token, $secret, $params))->assertOk()->assertJson(['result' => 'duplicate']);

        $entry = ReachLedgerEntry::where('conversion_type', 'conversion')->sole();
        $this->assertSame('estimate', $entry->status);
        $this->assertSame('subid', $entry->attribution_tier);
        $this->assertSame('sidebar', $entry->placement);
        $this->assertSame(7.0, $entry->net_commission);
        $this->assertSame(7.0, Wallet::for($publisher)->fresh()->pending_balance);

        // IP allow-list: the listed IP passes without a signature; any other IP is refused even when signed.
        [, $ipToken, $ipSecret] = $this->endpoint(['203.0.113.10']);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])->post('/reach/v1/postback/'.$ipToken, ['conversion_id' => 'c2', 'subid' => $publisher->ref])
            ->assertOk()->assertJson(['result' => 'credited']);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.1'])->get($this->signed($ipToken, $ipSecret, ['conversion_id' => 'c3']))
            ->assertForbidden()->assertJson(['result' => 'bad_ip']);

        // A reversal nets the credit back out, once.
        $this->get($this->signed($token, $secret, $params + ['status' => 'reversed']))->assertOk()->assertJson(['result' => 'refunded']);
        $this->get($this->signed($token, $secret, $params + ['status' => 'reversed']))->assertOk()->assertJson(['result' => 'duplicate']);
        $this->assertSame('refunded', $entry->fresh()->wallet_state);
    }

    public function test_attribution_waterfall_subid_then_owned_code_then_pool_copy_within_24h(): void
    {
        $a = ReachPublisher::factory()->verified()->create();
        $vip = ReachPublisher::factory()->verified()->create();
        $poolPub = ReachPublisher::factory()->verified()->create();
        $offer = ReachOffer::factory()->create();
        ReachCouponCode::create(['offer_id' => $offer->id, 'code' => 'SARA15', 'tier' => 'vip', 'publisher_id' => $vip->id]);
        foreach (['P1', 'P2'] as $c) {
            ReachCouponCode::create(['offer_id' => $offer->id, 'code' => $c, 'tier' => 'pool']);
        }

        $this->assertSame($a->id, Attribution::resolve($a->ref.'_footer', 'SARA15', $offer)['publisher']->id, 'Sub-ID wins');
        $this->assertSame('vip', Attribution::resolve(null, ' sara15 ', $offer)['tier']);

        $copied = Attribution::copyPoolCode($offer, $poolPub, 'article');
        $this->assertNotNull($copied);
        $this->travel(23)->hours();
        $hit = Attribution::resolve(null, $copied->code, $offer);
        $this->assertSame(['pool', $poolPub->id, 'article'], [$hit['tier'], $hit['publisher']->id, $hit['placement']]);
        $this->assertNotSame($copied->code, Attribution::copyPoolCode($offer, $poolPub)->code, 'pool rotates to the least recently copied code');

        $this->travel(2)->hours();
        $this->assertSame($poolPub->id, Attribution::resolve(null, 'P2', $offer)['publisher']->id);
        $this->assertNull(Attribution::resolve(null, $copied->code, $offer), 'copy older than 24 h is not credited');
        $this->assertNull(Attribution::resolve('zzzz_x', 'NOPE', $offer));
    }

    public function test_wallet_clearing_early_cashout_refund_netting_withdrawal_ad_credit_and_referral(): void
    {
        $referrer = ReachPublisher::factory()->verified()->create();
        $p = ReachPublisher::factory()->verified()->create(['referred_by_id' => $referrer->id]);
        $e1 = Wallet::creditConversion($p, ['gross_amount' => 100, 'net_commission' => 70, 'converted_at' => now()]);
        $e2 = Wallet::creditConversion($p, ['gross_amount' => 50, 'net_commission' => 30, 'converted_at' => now()]);
        $this->assertSame(5.0, Wallet::for($referrer)->fresh()->pending_balance, '5% lifetime referral override');

        $q = Wallet::earlyCashoutQuote($p);
        $this->assertSame(['advance' => 85.0, 'fee' => 5.1, 'net' => 79.9, 'reserve' => 15.0], $q);
        Wallet::requestEarlyCashout($p);
        $this->assertSame(0.0, Wallet::earlyCashoutQuote($p)['advance'], 'cannot advance the same pending twice');

        // Refund of 30 after the advance: nets 15 from the reserve, the other 15 from cleared (below zero = owed).
        Wallet::refund($e2);
        $this->assertSame(3.5, Wallet::for($referrer)->fresh()->pending_balance, 'the referrer override of a refunded sale is reversed too');
        $w = Wallet::for($p)->fresh();
        $this->assertSame([70.0, 70.0, 0.0, -15.0], [$w->pending_balance, $w->advanced_balance, $w->reserve_balance, $w->cleared_balance]);

        $this->travel(31)->days();
        $this->assertSame(2, Wallet::clearDue(), 'e1 and the referrer override');
        $w->refresh();
        $this->assertSame([0.0, 0.0, -15.0], [$w->pending_balance, $w->advanced_balance, $w->cleared_balance], 'advanced money is not paid twice');

        $q2 = Wallet::creditConversion($p, ['gross_amount' => 200, 'net_commission' => 140, 'converted_at' => now()]);
        $this->travel(31)->days();
        Wallet::clearDue();
        $this->assertSame(125.0, Wallet::for($p)->fresh()->cleared_balance);
        $this->assertSame('below_minimum', Wallet::requestWithdrawal($p, 49.99));
        $this->assertSame('over_balance', Wallet::requestWithdrawal($p, 500));
        $this->assertInstanceOf(ReachLedgerEntry::class, Wallet::requestWithdrawal($p, 50));
        $credit = Wallet::reinvestAsAdCredit($p, 20);
        $this->assertSame(23.0, $credit->net_commission, '+15% ad credit bonus');
        $this->assertSame(55.0, Wallet::for($p)->fresh()->cleared_balance);
        $this->assertSame(0, ReachLedgerEntry::where('status', '!=', 'estimate')->count(), 'no row is ever more than an estimate (D-262)');
        $this->assertNotNull($e1->fresh()->cleared_at);
        $this->assertSame('cleared', $q2->fresh()->wallet_state);
    }

    public function test_admin_board_imports_issues_endpoints_and_sets_billing_with_estimate_notice(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        $this->actingAs($admin)->get('/admin/reach?lang=en')->assertOk()
            ->assertSee('No money moves yet')->assertSee('Reach');
        $this->actingAs(User::factory()->create())->get('/admin/reach')->assertForbidden();

        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-reach', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Noor', 'currency' => 'KWD', 'status' => 'active']);
        $pub = ReachPublisher::factory()->create();

        $this->actingAs($admin);
        Livewire::test(ReachControlBoard::class)
            ->set('csv', "title,payout\nOffer A,9\n")->call('importCsv')->assertSet('csv', '')
            ->set('endpoint', ['network' => 'impact', 'label' => 'Impact', 'ips' => '203.0.113.5, not-an-ip'])->call('createEndpoint')
            ->assertSee('/reach/v1/postback/')
            ->set('billing', ['advertiser_id' => $client->id, 'billing_mode' => 'agency_guaranteed', 'credit_cap' => '١٠٠٠٫٥٠٠'])->call('saveBilling')
            ->call('setPublisherStatus', $pub->id, 'verified');

        $this->assertSame(1, ReachOffer::where('title', 'Offer A')->count());
        $this->assertSame(['203.0.113.5'], ReachPostbackEndpoint::where('network', 'impact')->sole()->allowed_ips);
        $billing = ReachAdvertiserBilling::sole();
        $this->assertSame(['agency_guaranteed', 1000.5, 'KWD'], [$billing->billing_mode, $billing->credit_cap, $billing->currency]);
        $this->assertSame('verified', $pub->fresh()->status);
        $other = Client::create(['agency_id' => $agency->id, 'name' => 'Lina', 'currency' => 'SAR', 'status' => 'active']);
        $this->assertSame('escrow_prepaid', ReachAdvertiserBilling::create(['advertiser_id' => $other->id])->fresh()->billing_mode, 'escrow is the default');
    }

    public function test_arabic_reach_strings_pass_the_arabic_quality_rules(): void
    {
        $flat = fn (array $a) => collect($a)->dot()->all();
        $ar = $flat(require lang_path('ar/reach_ui.php'));
        $en = $flat(require lang_path('en/reach_ui.php'));
        $this->assertSame([], array_keys(array_diff_key($en, $ar)), 'every English Reach line has Arabic');
        foreach ($ar as $k => $v) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\x{0600}-\x{06FF}])[وبلك]\x{0640}?(?=[A-Za-z:<])/u', $v, "joined letter in reach_ui.$k");
            $this->assertDoesNotMatchRegularExpression('/[٠-٩]/u', $v, "Eastern digit in reach_ui.$k");
        }
        foreach (array_filter($ar, fn ($v) => str_starts_with($v, '{0}')) as $k => $v) {
            $this->assertCount(5, explode('|', $v), "reach_ui.$k: Arabic plural needs {0}|{1}|{2}|[3,10]|[11,*]");
            $this->assertStringContainsString('|[3,10] ', $v, "reach_ui.$k");
            $this->assertStringContainsString('|[11,*] ', $v, "reach_ui.$k");
        }
        app()->setLocale('en');
        $this->assertSame('Reach', __('Reach'), 'the Meta "Reach" metric label must not load a Reach lang group');
        app()->setLocale('ar');
        $this->assertSame('تأكّد تحويلان.', trans_choice('reach_ui.admin.cleared', 2));
        $this->assertSame('تأكّد 11 تحويلًا.', trans_choice('reach_ui.admin.cleared', 11, ['count' => 11]));
    }
}
