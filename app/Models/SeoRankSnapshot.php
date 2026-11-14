<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoRankSnapshot extends Model
{
    public const SOURCES = ['gsc', 'serp_provider', 'manual'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'position' => 'float',
            'serp_features' => 'array',
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

    public function trackedKeyword(): BelongsTo
    {
        return $this->belongsTo(SeoTrackedKeyword::class, 'tracked_keyword_id');
    }
}
