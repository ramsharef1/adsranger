<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntentKeywordMap extends Model
{
    protected $guarded = [];

    protected $casts = [
        'intent_buckets' => 'array',
        'ad_group_structures' => 'array',
        'negative_keywords' => 'array',
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