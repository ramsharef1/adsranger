<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkLaunch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['networks' => 'array', 'published_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(CreativeBrief::class, 'creative_brief_id');
    }
}
