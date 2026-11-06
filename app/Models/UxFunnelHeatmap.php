<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UxFunnelHeatmap extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sessions_analyzed' => 'integer',
        'avg_scroll_depth' => 'float',
        'drop_off_rate' => 'float',
        'friction_Zones' => 'array',
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