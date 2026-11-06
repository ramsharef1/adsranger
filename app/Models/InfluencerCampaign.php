<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfluencerCampaign extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fixed_fee' => 'float',
        'commission_percent' => 'float',
        'orders_count' => 'integer',
        'attributed_revenue' => 'float',
        'total_payout' => 'float',
        'effective_roas' => 'float',
        'cac' => 'float',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}