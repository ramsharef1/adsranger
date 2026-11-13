<?php

namespace Tests\Feature;

use App\Filament\Pages\ProfitAttributionHubPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientStoreConnection;
use App\Models\ClientUnitEconomic;
use App\Models\SkuPerformanceSnapshot;
use App\Models\User;
use App\Support\Attribution\ProfitAndAttributionManager;
use App\Support\Attribution\StoreWebhooks;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/** E2-063: signed Salla / Zid / Shopify webhooks → profit hub (module intelligence_hub, off). Recorded contract payloads. */
class MenaStoreWebhooksTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'store-signing-secret-0123456789';

    /** Salla order.status.updated, shape from the Salla webhook docs (trimmed: no customer fields). */
    private const SALLA_COMPLETED = [
        'event' => 'order.status.updated', 'merchant' => 1305146709, 'created_at' => 'NOW',
        'data' => ['id' => 2087321, 'reference_id' => 71003, 'payment_method' => 'cod', 'currency' => 'SAR',
            'status' => ['id' => 566146469, 'name' => 'تم التنفيذ', 'slug' => 'completed'],
            'amounts' => ['total' => ['amount' => 349.5, 'currency' => 'SAR']],
            'items' => [
                ['sku' => 'OUD-50', 'name' => 'عطر عود 50 مل', 'quantity' => 1, 'amounts' => ['total' => ['amount' => 299.5, 'currency' => 'SAR']]],
                ['sku' => 'BKH-01', 'name' => 'مبخرة', 'quantity' => 1, 'amounts' => ['total' => ['amount' => 50, 'currency' => 'SAR']]],
            ]],
    ];

    /** Zid order.status.update, shape from the Zid webhook docs (trimmed). */
    private const ZID_RETURNED = [
        'event' => 'order.status.update', 'id' => 55120, 'currency_code' => 'SAR', 'order_total' => '120.00',
        'order_status' => ['code' => 'reversed', 'name' => 'مسترجع'],
        'payment' => ['method' => ['code' => 'zid_cod', 'name' => 'الدفع عند الاستلام']],
        'products' => [['sku' => 'MSK-12', 'name' => 'مسك', 'quantity' => 2, 'total' => '120.00']],
    ];

    /** Shopify products/update, shape from the Shopify Admin webhook reference (trimmed). */
    private const SHOPIFY_PRODUCT = [
        'id' => 788032119674292922, 'title' => 'Oud Body Mist',
        'variants' => [
            ['id' => 642667041472713922, 'title' => 'Default Title', 'sku' => 'MIST-200', 'inventory_quantity' => 0],
        ],
    ];

    /** @return array{0: Agency, 1: User, 2: Client} */
    private function workspace(string $slug, string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $agency = Agency::create(['name' => 'Agency '.$slug, 'slug' => $slug, 'plan' => 'agency', 'status' => 'active', 'signup_source' => 'admin', 'owner_id' => $user->id, 'language' => 'ar']);
        DB::table('agency_user')->where('user_id', $user->id)->delete();
        $agency->members()->attach($user->id, ['role' => $role]);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Client '.$slug, 'currency' => 'SAR', 'status' => 'active']);

        return [$agency, $user, $client];
    }

    private function connect(Client $client, string $platform): ClientStoreConnection
    {
        return ClientStoreConnection::create(['agency_id' => $client->agency_id, 'client_id' => $client->id, 'platform' => $platform, 'store_identifier' => $platform.'-store', 'webhook_secret' => self::SECRET, 'webhook_token' => Str::random(48), 'status' => 'webhook_ready']);
    }

    /** @param  array<string, string>  $headers */
    private function send(ClientStoreConnection $c, array $payload, array $headers = [], ?string $secret = null): TestResponse
    {
        $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $mac = hash_hmac('sha256', $raw, $secret ?? self::SECRET, true);
        $sig = match ($c->platform) {
            'salla' => ['X-Salla-Signature' => bin2hex($mac)],
            'zid' => ['X-Zid-Webhook-Signature' => bin2hex($mac)],
            'shopify' => ['X-Shopify-Hmac-Sha256' => base64_encode($mac)],
        };
        $server = [];
        foreach ($headers + $sig + ['Content-Type' => 'application/json'] as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }
        $server['CONTENT_TYPE'] = 'application/json';

        return $this->call('POST', '/webhooks/stores/'.$c->webhook_token, [], [], [], $server, $raw);
    }

    private function salla(string $slug = 'completed', int $orderId = 2087321): array
    {
        $p = self::SALLA_COMPLETED;
        $p['created_at'] = now()->toIso8601String();
        $p['data']['status']['slug'] = $slug;
        $p['data']['id'] = $orderId;

        return $p;
    }

    public function test_receiver_is_404_while_the_module_is_off_and_csrf_exempt_when_on(): void
    {
        [$agency, , $client] = $this->workspace('store-off');
        $c = $this->connect($client, 'salla');
        $this->assertFalse(ProfitAndAttributionManager::moduleLiveFor($agency));
        $this->send($c, $this->salla())->assertNotFound();
        $this->post('/webhooks/stores/'.Str::random(48), [])->assertNotFound();
        $this->assertSame(0, $c->events()->count());

        config(['modules.preview_agencies' => ['store-off']]);
        $this->send($c, $this->salla())->assertOk()->assertJson(['ok' => true, 'duplicate' => false, 'type' => 'order_completed']);
    }

    public function test_bad_or_missing_signature_is_refused_and_nothing_is_stored(): void
    {
        config(['modules.enabled.intelligence_hub' => true]);
        [, , $client] = $this->workspace('store-sig');
        foreach (['salla', 'zid', 'shopify'] as $platform) {
            $c = $this->connect($client, $platform);
            $this->send($c, $this->salla(), [], 'wrong-secret-wrong-secret')->assertStatus(401);
            $this->call('POST', '/webhooks/stores/'.$c->webhook_token, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertStatus(401);
            $this->assertSame('webhook_ready', $c->fresh()->status, "$platform stays waiting: no fake live sync");
        }
        $this->assertSame(0, DB::table('client_store_webhook_events')->count());
        $this->assertFalse(StoreWebhooks::verify('salla', 'x', 'sha256='.str_repeat('0', 64), self::SECRET));
        $this->assertTrue(StoreWebhooks::verify('salla', 'x', 'sha256='.hash_hmac('sha256', 'x', self::SECRET), self::SECRET));
    }

    public function test_salla_completed_cod_order_feeds_skus_and_observed_rates_once(): void
    {
        config(['modules.enabled.intelligence_hub' => true]);
        [, , $client] = $this->workspace('store-salla');
        $c = $this->connect($client, 'salla');
        $payload = $this->salla();

        $this->send($c, $payload)->assertOk()->assertJson(['duplicate' => false]);
        $this->send($c, $payload)->assertOk()->assertJson(['duplicate' => true]);

        $c->refresh();
        $this->assertSame('active', $c->status);
        $this->assertNotNull($c->last_event_at);
        $this->assertSame(1, $c->events()->count());
        $oud = SkuPerformanceSnapshot::where('client_id', $client->id)->where('sku_code', 'OUD-50')->firstOrFail();
        $this->assertSame(1, (int) $oud->orders_count);
        $this->assertEqualsWithDelta(299.5, (float) $oud->gross_revenue, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $oud->cogs_amount, 0.001, 'UX-255: no invented COGS before the agency enters costs');
        $this->assertContains('BKH-01', $oud->co_purchased_skus_json);

        $econ = ClientUnitEconomic::where('client_id', $client->id)->firstOrFail();
        $this->assertSame(['orders' => 1, 'cod_share_pct' => 100.0, 'cod_return_rate_pct' => 0.0], $econ->storeRates());
        $this->assertSame(0.0, (float) $econ->cod_share_pct, 'the agency\'s own rate is never overwritten by the webhook');

        // Same order later refunded: counted as a COD return, not as a second order.
        $refund = $payload;
        $refund['event'] = 'order.refunded';
        $refund['created_at'] = now()->addMinute()->toIso8601String();
        $this->send($c, $refund)->assertOk()->assertJson(['type' => 'cod_returned']);
        $this->assertSame(['orders' => 1, 'cod_share_pct' => 100.0, 'cod_return_rate_pct' => 100.0], $econ->fresh()->storeRates());

        $stale = $this->salla('completed', 999);
        $stale['created_at'] = now()->subDays(5)->toIso8601String();
        $this->send($c, $stale)->assertStatus(409);
    }

    public function test_zid_returned_cod_and_shopify_out_of_stock_reach_cod_poas_and_the_bleed_guard(): void
    {
        config(['modules.enabled.intelligence_hub' => true]);
        [, , $client] = $this->workspace('store-zid');
        $zid = $this->connect($client, 'zid');
        $this->send($zid, self::ZID_RETURNED, ['X-Zid-Webhook-Id' => 'evt-1'])->assertOk()->assertJson(['type' => 'cod_returned']);
        $this->send($zid, self::ZID_RETURNED, ['X-Zid-Webhook-Id' => 'evt-1'])->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(['orders' => 1, 'cod_share_pct' => 100.0, 'cod_return_rate_pct' => 100.0], ClientUnitEconomic::where('client_id', $client->id)->firstOrFail()->storeRates());

        // #197: an advertised SKU goes out of stock in Shopify → the bleed guard flags its spend.
        ProfitAndAttributionManager::recordSkuSnapshot($client, ['sku_code' => 'MIST-200', 'product_title' => 'Oud Body Mist', 'stock_qty' => 40, 'ad_spend' => 210, 'gross_revenue' => 300, 'orders_count' => 3]);
        $this->assertSame([], ProfitAndAttributionManager::detectOutOfStockBleed($client)['flagged_skus']);
        $shop = $this->connect($client, 'shopify');
        $this->send($shop, self::SHOPIFY_PRODUCT, ['X-Shopify-Topic' => 'products/update', 'X-Shopify-Webhook-Id' => 'b54557e4-bdd9', 'X-Shopify-Triggered-At' => now()->toIso8601String()])
            ->assertOk()->assertJson(['type' => 'stock_update']);
        $bleed = ProfitAndAttributionManager::detectOutOfStockBleed($client);
        $this->assertSame('MIST-200', $bleed['flagged_skus'][0]['sku_code']);
        $this->assertEqualsWithDelta(210.0, $bleed['wasted_spend'], 0.001);

        // Shopify COD order paid.
        $order = ['id' => 450789469, 'currency' => 'SAR', 'total_price' => '89.00', 'payment_gateway_names' => ['Cash on Delivery (COD)'], 'line_items' => [['sku' => 'MIST-200', 'title' => 'Oud Body Mist', 'quantity' => 1, 'price' => '89.00']]];
        $this->send($shop, $order, ['X-Shopify-Topic' => 'orders/paid', 'X-Shopify-Webhook-Id' => 'c1'])->assertOk()->assertJson(['type' => 'order_completed']);
        $this->assertSame(2, ClientUnitEconomic::where('client_id', $client->id)->firstOrFail()->storeRates()['orders']);
        $summary = DB::table('client_store_webhook_events')->where('event_type', 'order_completed')->value('summary');
        $this->assertStringNotContainsString('customer', (string) $summary);
    }

    public function test_paused_connection_answers_202_and_only_managers_add_stores_in_the_hub(): void
    {
        config(['modules.enabled.intelligence_hub' => true]);
        [$agency, $owner, $client] = $this->workspace('store-ui');
        $c = $this->connect($client, 'salla');
        $c->update(['status' => 'paused']);
        $this->send($c, $this->salla())->assertStatus(202);
        $this->assertSame(0, $c->events()->count());

        $this->actingAs($owner)->get("/app/{$agency->slug}/profit-attribution-hub?lang=ar")->assertOk()
            ->assertSee('طلبات المتجر ومخزونه')->assertSee('متوقفة')->assertSee($c->receiverUrl())->assertDontSee(self::SECRET);

        Filament::setTenant($agency);
        Livewire::test(ProfitAttributionHubPage::class)
            ->set('storePlatform', 'zid')->set('storeIdentifier', 'my-zid-store')->set('storeSecret', 'short')
            ->call('addStoreConnection')->assertHasErrors(['storeSecret'])
            ->set('storeSecret', self::SECRET)->call('addStoreConnection')->assertHasNoErrors();
        $added = ClientStoreConnection::where('platform', 'zid')->firstOrFail();
        $this->assertSame('webhook_ready', $added->status);
        $this->assertNotSame(self::SECRET, DB::table('client_store_connections')->where('id', $added->id)->value('webhook_secret'), 'secret is encrypted at rest');

        $analyst = User::factory()->create();
        DB::table('agency_user')->where('user_id', $analyst->id)->delete();
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);
        $this->actingAs($analyst);
        Filament::setTenant($agency);
        Livewire::test(ProfitAttributionHubPage::class)
            ->set('storeIdentifier', 'x')->set('storeSecret', self::SECRET)->call('addStoreConnection')
            ->call('deleteStoreConnection', $added->id);
        $this->assertSame(2, ClientStoreConnection::count());
        $this->actingAs($analyst)->get("/app/{$agency->slug}/profit-attribution-hub?lang=en")->assertOk()->assertDontSee($added->receiverUrl());
    }
}
