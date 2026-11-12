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
