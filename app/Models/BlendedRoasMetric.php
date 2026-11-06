<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlendedRoasMetric extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metric_date' => 'date',
        'total_spend' => 'float',
        'total_revenue' => 'float',
        'total_conversions' => 'float',
        'blended_roas' => 'float',
        'marketing_efficiency_ratio' => 'float',
        'channel_breakdown' => 'array',
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