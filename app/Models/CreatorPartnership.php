<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreatorPartnership extends Model
{
    public const PLATFORMS = ['snapchat', 'tiktok', 'instagram', 'youtube', 'x'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'flat_fee' => 'float',
            'gifted_cogs' => 'float',
            'boosted_spend' => 'float',
            'attributed_orders' => 'integer',
            'gross_revenue' => 'float',
            'net_poas' => 'float',
        ];
    }

    public function totalInvestment(): float
    {
        return round((float) $this->flat_fee + (float) $this->gifted_cogs + (float) $this->boosted_spend, 3);
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
