<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkuPerformanceSnapshot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stock_qty' => 'integer',
            'ad_spend' => 'float',
            'gross_revenue' => 'float',
            'cogs_amount' => 'float',
            'net_profit' => 'float',
            'orders_count' => 'integer',
            'repeat_ltv_90d' => 'float',
            'co_purchased_skus_json' => 'array',
            'promo_codes_json' => 'array',
            'period_date' => 'date',
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
