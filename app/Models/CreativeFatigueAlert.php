<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** E2-066: an ad whose frequency or click-through rate says it is tiring (open until it recovers). */
class CreativeFatigueAlert extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'frequency' => 'float', 'ctr_recent' => 'float', 'ctr_baseline' => 'float', 'ctr_drop_pct' => 'float',
            'thruplay_drop_pct' => 'float', 'spend_recent' => 'float', 'thresholds' => 'array',
            'window_end' => 'date', 'detected_at' => 'datetime', 'resolved_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
