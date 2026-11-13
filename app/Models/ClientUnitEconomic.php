<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientUnitEconomic extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cogs_pct' => 'float',
            'shipping_fee' => 'float',
            'return_shipping_fee' => 'float',
            'cod_share_pct' => 'float',
            'cod_return_rate_pct' => 'float',
            'bnpl_share_pct' => 'float',
            'bnpl_fee_pct' => 'float',
            'gateway_fee_pct' => 'float',
            'costs_entered_at' => 'datetime',
            'store_orders_observed' => 'integer',
            'store_cod_orders' => 'integer',
            'store_cod_returned' => 'integer',
            'store_rates_at' => 'datetime',
        ];
    }

    /**
     * E2-063: COD share and COD return rate observed from the store's own webhooks (null until an order was seen).
     *
     * @return array{orders: int, cod_share_pct: ?float, cod_return_rate_pct: ?float}
     */
    public function storeRates(): array
    {
        $orders = (int) $this->store_orders_observed;
        $cod = (int) $this->store_cod_orders;

        return [
            'orders' => $orders,
            'cod_share_pct' => $orders > 0 ? round($cod / $orders * 100, 1) : null,
            'cod_return_rate_pct' => $cod > 0 ? round((int) $this->store_cod_returned / $cod * 100, 1) : null,
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
