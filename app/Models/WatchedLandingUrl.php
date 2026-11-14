<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WatchedLandingUrl extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expected_pixels' => 'array',
            'last_http_status' => 'integer',
            'redirect_hops' => 'integer',
            'has_noindex' => 'boolean',
            'missing_pixels' => 'array',
            'broken_whatsapp_link' => 'boolean',
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

    public function checks(): HasMany
    {
        return $this->hasMany(WatchedUrlCheck::class, 'watched_url_id');
    }
}
