<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitorCreativeRadar extends Model
{
    protected $guarded = [];

    protected $casts = [
        'active_ads_count' => 'integer',
        'top_hooks' => 'array',
        'trend_signals' => 'array',
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