<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapiBridgeEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'click_ids_json' => 'array',
            'free_window_expires_at' => 'datetime',
            'value' => 'float',
            'occurred_at' => 'datetime',
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
