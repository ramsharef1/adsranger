<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionHealthCheck extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pixel_active' => 'boolean',
            'capi_active' => 'boolean',
            'event_match_quality' => 'decimal:1',
            'deduplication_rate' => 'decimal:2',
            'checked_at' => 'datetime',
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
