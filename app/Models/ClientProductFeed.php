<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProductFeed extends Model
{
    public const CHANNELS = ['google_merchant', 'meta_catalog', 'tiktok_catalog', 'snap_catalog'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'include_out_of_stock' => 'boolean',
            'rules' => 'array',
            'last_generated_at' => 'datetime',
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
