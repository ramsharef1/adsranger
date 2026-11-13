<?php

namespace App\Support\Attribution;

use App\Models\ClientStoreConnection;
use App\Models\ClientStoreWebhookEvent;
use App\Models\SkuPerformanceSnapshot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * E2-063: verifies and maps signed store webhooks (Salla «سلة», Zid «زد», Shopify) into the profit hub.
 *  - order finished (completed / delivered / paid) → per-SKU orders and revenue on today's sku_performance_snapshots row,
 *    and the observed order counters on client_unit_economics (#193 COD POAS);
 *  - COD order cancelled / returned → the observed COD return counter;
 *  - stock update → stock_qty / stock_status on the SKU's latest snapshot (#197 out-of-stock ad bleed guard).
 * Nothing is sent back to the store; the hub never shows a sync it did not receive.
 *
 * @phpstan-type Normalised array{uid: string, type: string, event: string, order_ref: ?string, cod: bool, total: ?float, currency: ?string,
 *   items: list<array{sku: string, title: string, qty: int, total: float}>, stock: list<array{sku: string, title: string, qty: int}>, occurred_at: ?Carbon}
 */
class StoreWebhooks
{
    private const DONE = ['completed', 'delivered', 'paid', 'complete', 'fulfilled'];

    private const UNDONE = ['canceled', 'cancelled', 'returned', 'refunded', 'reversed', 'restored', 'voided'];

    /** Constant-time HMAC-SHA256 check of the raw body against the platform's signature header. */
    public static function verify(string $platform, string $rawBody, ?string $signature, string $secret): bool
    {
        $cfg = config("store_sync.platforms.$platform");
        if (! $cfg || $signature === null || $signature === '' || $secret === '') {
            return false;
        }
        $raw = hash_hmac('sha256', $rawBody, $secret, true);
        $expected = $cfg['encoding'] === 'base64' ? base64_encode($raw) : bin2hex($raw);
        $given = trim($signature);
        if ($cfg['encoding'] === 'hex') {
            $given = strtolower(preg_replace('/^sha256=/i', '', $given) ?? $given);
        }

        return hash_equals($expected, $given);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string|null>  $headers  lower-case header name => value
     * @return Normalised
     */
    public static function normalise(string $platform, array $payload, array $headers, string $rawBody): array
    {
        return match ($platform) {
            'salla' => self::salla($payload, $rawBody),
            'zid' => self::zid($payload, $headers, $rawBody),
            'shopify' => self::shopify($payload, $headers, $rawBody),
        };
    }

    /**
     * Records the event once and applies it. Returns false when the event was already recorded (replay / retry).
     *
     * @param  Normalised  $n
     */
    public static function apply(ClientStoreConnection $connection, array $n): bool
    {
        if (ClientStoreWebhookEvent::where('connection_id', $connection->id)->where('event_uid', mb_substr($n['uid'], 0, 191))->exists()) {
            return false;
        }
        try {
            return DB::transaction(function () use ($connection, $n): bool {
                $previous = $n['order_ref'] !== null
                    ? ClientStoreWebhookEvent::where('connection_id', $connection->id)->where('order_ref', $n['order_ref'])
                        ->whereIn('event_type', ['order_completed', 'cod_returned'])->latest('id')->value('event_type')
                    : null;

                ClientStoreWebhookEvent::create([
                    'connection_id' => $connection->id,
                    'event_uid' => mb_substr($n['uid'], 0, 191),
                    'event_type' => $n['type'],
                    'order_ref' => $n['order_ref'] !== null ? mb_substr($n['order_ref'], 0, 64) : null,
                    'summary' => self::summary($n),
                    'processed_at' => now(),
                ]);

                match ($n['type']) {
                    'order_completed' => self::orderFinished($connection, $n, $previous),
                    'cod_returned' => self::codReturned($connection, $previous),
                    'stock_update' => self::stock($connection, $n),
                    default => null,
                };

                $connection->forceFill(['last_event_at' => now(), 'status' => $connection->status === 'paused' ? 'paused' : 'active'])->save();

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /** Refuses events whose own time is outside the replay window (only when the platform sends a time). */
    public static function tooOld(array $n): bool
    {
        return $n['occurred_at'] !== null && $n['occurred_at']->lt(now()->subHours((int) config('store_sync.replay_window_hours', 72)));
    }

    /** @param  Normalised  $n */
    private static function orderFinished(ClientStoreConnection $c, array $n, ?string $previous): void
    {
        if ($previous !== null) {
            return; // the order was already counted as finished or returned
        }
        $econ = ProfitAndAttributionManager::ensureUnitEconomics($c->client);
        $econ->forceFill([
            'store_orders_observed' => $econ->store_orders_observed + 1,
            'store_cod_orders' => $econ->store_cod_orders + ($n['cod'] ? 1 : 0),
            'store_rates_at' => now(),
        ])->save();

        $cogsPct = $econ->costs_entered_at ? (float) $econ->cogs_pct : 0.0; // UX-255: no invented costs
        foreach ($n['items'] as $item) {
            $row = SkuPerformanceSnapshot::where('agency_id', $c->agency_id)->where('client_id', $c->client_id)
                ->where('sku_code', $item['sku'])->whereDate('period_date', today())->latest('id')->first();
            $row ??= new SkuPerformanceSnapshot([
                'agency_id' => $c->agency_id, 'client_id' => $c->client_id, 'sku_code' => $item['sku'], 'product_title' => $item['title'],
                'stock_status' => 'in_stock', 'stock_qty' => 0, 'ad_spend' => 0, 'gross_revenue' => 0, 'cogs_amount' => 0, 'net_profit' => 0,
                'orders_count' => 0, 'repeat_ltv_90d' => 0, 'period_date' => today()->toDateString(), 'co_purchased_skus_json' => [], 'promo_codes_json' => [],
            ]);
            $gross = (float) $row->gross_revenue + $item['total'];
            $cogs = round($gross * $cogsPct / 100, 2);
            $net = round($gross - $cogs - (float) $row->ad_spend, 2);
            $orders = (int) $row->orders_count + 1;
            $row->forceFill([
                'gross_revenue' => round($gross, 2),
                'cogs_amount' => $cogs,
                'net_profit' => $net,
                'orders_count' => $orders,
                'co_purchased_skus_json' => array_values(array_unique(array_merge((array) $row->co_purchased_skus_json, array_values(array_diff(array_column($n['items'], 'sku'), [$item['sku']]))))),
                'quadrant' => ProfitAndAttributionManager::classifySkuQuadrant((float) $row->ad_spend, $net, $orders),
            ])->save();
        }
    }

    private static function codReturned(ClientStoreConnection $c, ?string $previous): void
    {
        if ($previous === 'cod_returned') {
            return;
        }
        $econ = ProfitAndAttributionManager::ensureUnitEconomics($c->client);
        $econ->forceFill([
            // A COD order returned before we saw it finish still counts as one finished COD order that came back.
            'store_orders_observed' => $econ->store_orders_observed + ($previous === null ? 1 : 0),
            'store_cod_orders' => $econ->store_cod_orders + ($previous === null ? 1 : 0),
            'store_cod_returned' => $econ->store_cod_returned + 1,
            'store_rates_at' => now(),
        ])->save();
    }

    /** @param  Normalised  $n */
    private static function stock(ClientStoreConnection $c, array $n): void
    {
        foreach ($n['stock'] as $s) {
            $row = SkuPerformanceSnapshot::where('agency_id', $c->agency_id)->where('client_id', $c->client_id)
                ->where('sku_code', $s['sku'])->orderByDesc('period_date')->orderByDesc('id')->first();
            $status = $s['qty'] <= 0 ? 'out_of_stock' : 'in_stock';
            if ($row) {
                $row->forceFill(['stock_qty' => $s['qty'], 'stock_status' => $status])->save();

                continue;
            }
            SkuPerformanceSnapshot::create([
                'agency_id' => $c->agency_id, 'client_id' => $c->client_id, 'sku_code' => $s['sku'], 'product_title' => $s['title'] ?: $s['sku'],
                'stock_status' => $status, 'stock_qty' => $s['qty'], 'ad_spend' => 0, 'gross_revenue' => 0, 'cogs_amount' => 0, 'net_profit' => 0,
                'orders_count' => 0, 'repeat_ltv_90d' => 0, 'quadrant' => ProfitAndAttributionManager::classifySkuQuadrant(0, 0, 0),
                'period_date' => today()->toDateString(), 'co_purchased_skus_json' => [], 'promo_codes_json' => [],
            ]);
        }
    }

    /**
     * Salla: {"event": "order.status.updated", "merchant": 1, "created_at": "...", "data": {...}}.
     *
     * @return Normalised
     */
    private static function salla(array $p, string $raw): array
    {
        $event = (string) ($p['event'] ?? '');
        $d = (array) ($p['data'] ?? []);
        $n = self::blank('salla:'.($event !== '' && isset($d['id']) ? $event.':'.$d['id'].':'.($p['created_at'] ?? '') : hash('sha256', $raw)), $event, self::time($p['created_at'] ?? null));

        if (str_starts_with($event, 'product.')) {
            if (isset($d['sku']) || isset($d['quantity'])) {
                $n['type'] = 'stock_update';
                $n['stock'] = self::stockRows([['sku' => $d['sku'] ?? $d['id'] ?? '', 'title' => $d['name'] ?? '', 'qty' => $d['quantity'] ?? null]]);
            }

            return $n;
        }
        if (! str_starts_with($event, 'order.')) {
            return $n;
        }
        $status = strtolower((string) (data_get($d, 'status.slug') ?? data_get($d, 'status.customized.slug') ?? ''));
        if ($event === 'order.cancelled') {
            $status = 'canceled';
        } elseif ($event === 'order.refunded') {
            $status = 'refunded';
        }
        $n['order_ref'] = isset($d['id']) ? (string) $d['id'] : null;
        $n['cod'] = strtolower((string) ($d['payment_method'] ?? '')) === 'cod';
        $n['total'] = self::num(data_get($d, 'amounts.total.amount') ?? data_get($d, 'total.amount'));
        $n['currency'] = (string) (data_get($d, 'amounts.total.currency') ?? $d['currency'] ?? '') ?: null;
        $n['items'] = self::itemRows(array_map(fn ($i) => ['sku' => $i['sku'] ?? '', 'title' => $i['name'] ?? '', 'qty' => $i['quantity'] ?? 1, 'total' => data_get($i, 'amounts.total.amount') ?? data_get($i, 'amounts.price_without_tax.amount')], (array) ($d['items'] ?? [])));
        $n['type'] = self::orderType($status, $n['cod']);

        return $n;
    }

    /**
     * Zid: {"event": "order.status.update", "id": 1, "order_status": {"code": "delivered"}, "payment": {"method": {"code": "zid_cod"}}, ...}.
     *
     * @return Normalised
     */
    private static function zid(array $p, array $h, string $raw): array
    {
        $event = (string) ($p['event'] ?? $h['x-zid-event'] ?? '');
        $d = isset($p['data']) && is_array($p['data']) ? $p['data'] : $p;
        $uid = $h['x-zid-webhook-id'] ?? null;
        $n = self::blank('zid:'.($uid ?: hash('sha256', $raw)), $event, self::time($d['updated_at'] ?? $p['created_at'] ?? null));

        if (str_starts_with($event, 'product.')) {
            $n['type'] = 'stock_update';
            $n['stock'] = self::stockRows([['sku' => $d['sku'] ?? '', 'title' => is_array($d['name'] ?? null) ? ($d['name']['ar'] ?? $d['name']['en'] ?? '') : ($d['name'] ?? ''), 'qty' => $d['quantity'] ?? null]]);

            return $n;
        }
        if (! str_starts_with($event, 'order.')) {
            return $n;
        }
        $status = strtolower((string) (data_get($d, 'order_status.code') ?? data_get($d, 'status') ?? ''));
        $n['order_ref'] = isset($d['id']) ? (string) $d['id'] : null;
        $n['cod'] = (bool) preg_match('/cod|cash/i', (string) (data_get($d, 'payment.method.code') ?? data_get($d, 'payment.method.name') ?? ''));
        $n['total'] = self::num($d['order_total'] ?? $d['total'] ?? null);
        $n['currency'] = (string) ($d['currency_code'] ?? '') ?: null;
        $n['items'] = self::itemRows(array_map(fn ($i) => ['sku' => $i['sku'] ?? '', 'title' => $i['name'] ?? '', 'qty' => $i['quantity'] ?? 1, 'total' => $i['total'] ?? $i['price'] ?? null], (array) ($d['products'] ?? [])));
        $n['type'] = self::orderType($status, $n['cod']);

        return $n;
    }

    /**
     * Shopify: topic in X-Shopify-Topic (orders/paid, orders/cancelled, products/update), id in X-Shopify-Webhook-Id.
     *
     * @return Normalised
     */
    private static function shopify(array $p, array $h, string $raw): array
    {
        $topic = (string) ($h['x-shopify-topic'] ?? '');
        $n = self::blank('shopify:'.(($h['x-shopify-webhook-id'] ?? '') ?: hash('sha256', $raw)), $topic, self::time($h['x-shopify-triggered-at'] ?? null));

        if ($topic === 'products/update' || $topic === 'products/create') {
            $n['type'] = 'stock_update';
            $n['stock'] = self::stockRows(array_map(fn ($v) => ['sku' => $v['sku'] ?? '', 'title' => trim(($p['title'] ?? '').' '.(($v['title'] ?? '') === 'Default Title' ? '' : ($v['title'] ?? ''))), 'qty' => $v['inventory_quantity'] ?? null], (array) ($p['variants'] ?? [])));

            return $n;
        }
        if (! str_starts_with($topic, 'orders/')) {
            return $n;
        }
        $n['order_ref'] = isset($p['id']) ? (string) $p['id'] : null;
        $n['cod'] = (bool) preg_match('/cash on delivery|\bcod\b/i', implode(' ', (array) ($p['payment_gateway_names'] ?? [])).' '.($p['gateway'] ?? ''));
        $n['total'] = self::num($p['total_price'] ?? null);
        $n['currency'] = (string) ($p['currency'] ?? '') ?: null;
        $n['items'] = self::itemRows(array_map(fn ($i) => ['sku' => $i['sku'] ?? '', 'title' => $i['title'] ?? '', 'qty' => $i['quantity'] ?? 1, 'total' => is_numeric($i['price'] ?? null) ? (float) $i['price'] * (int) ($i['quantity'] ?? 1) : null], (array) ($p['line_items'] ?? [])));
        $status = match ($topic) {
            'orders/paid', 'orders/fulfilled' => 'paid',
            'orders/cancelled' => 'cancelled',
            default => '',
        };
        $n['type'] = self::orderType($status, $n['cod']);

        return $n;
    }

    private static function orderType(string $status, bool $cod): string
    {
        return match (true) {
            in_array($status, self::DONE, true) => 'order_completed',
            in_array($status, self::UNDONE, true) => $cod ? 'cod_returned' : 'order_cancelled',
            default => 'ignored',
        };
    }

    /** @return Normalised */
    private static function blank(string $uid, string $event, ?Carbon $at): array
    {
        return ['uid' => $uid, 'type' => 'ignored', 'event' => mb_substr($event, 0, 64), 'order_ref' => null, 'cod' => false, 'total' => null, 'currency' => null, 'items' => [], 'stock' => [], 'occurred_at' => $at];
    }

    /** @return list<array{sku: string, title: string, qty: int, total: float}> */
    private static function itemRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $sku = mb_substr(trim((string) $r['sku']), 0, 120);
            if ($sku === '') {
                continue;
            }
            $out[] = ['sku' => $sku, 'title' => mb_substr(trim((string) $r['title']), 0, 255) ?: $sku, 'qty' => max(1, (int) $r['qty']), 'total' => max(0.0, (float) (self::num($r['total']) ?? 0))];
        }

        return $out;
    }

    /** @return list<array{sku: string, title: string, qty: int}> */
    private static function stockRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $sku = mb_substr(trim((string) $r['sku']), 0, 120);
            if ($sku === '' || ! is_numeric($r['qty'])) {
                continue;
            }
            $out[] = ['sku' => $sku, 'title' => mb_substr(trim((string) $r['title']), 0, 255), 'qty' => (int) $r['qty']];
        }

        return $out;
    }

    private static function num(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private static function time(mixed $v): ?Carbon
    {
        if (! is_string($v) || trim($v) === '') {
            return null;
        }
        try {
            return Carbon::parse($v);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Only what the hub shows: no customer name, phone, e-mail or address is kept.
     *
     * @param  Normalised  $n
     * @return array<string, mixed>
     */
    private static function summary(array $n): array
    {
        return array_filter([
            'event' => $n['event'],
            'cod' => $n['type'] === 'stock_update' ? null : $n['cod'],
            'total' => $n['total'],
            'currency' => $n['currency'],
            'skus' => array_slice(array_column($n['items'] ?: $n['stock'], 'sku'), 0, 20),
            'stock' => $n['stock'] !== [] ? array_slice(array_map(fn ($s) => $s['sku'].'='.$s['qty'], $n['stock']), 0, 20) : null,
        ], fn ($v) => $v !== null && $v !== []);
    }
}
