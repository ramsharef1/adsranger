<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchedUrlCheck extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'http_status' => 'integer',
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

    public function watchedUrl(): BelongsTo
    {
        return $this->belongsTo(WatchedLandingUrl::class, 'watched_url_id');
    }
}
