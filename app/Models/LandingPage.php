<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A landing page URL used by ads, with its latest health check (#49). */
class LandingPage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'last_seen_at' => 'datetime', 'down_since' => 'datetime'];
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
