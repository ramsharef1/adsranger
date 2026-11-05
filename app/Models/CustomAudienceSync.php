<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomAudienceSync extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'target_networks' => 'array',
            'match_rate' => 'decimal:2',
            'last_synced_at' => 'datetime',
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
