<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A client's website for the SEO suite (PR-014 §1.1). The consent tick is stored with user + time before the first crawl. */
class SeoSite extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['consent_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(SeoAudit::class);
    }

    public function latestAudit(): HasOne
    {
        return $this->hasOne(SeoAudit::class)->latestOfMany();
    }

    public function hasConsent(): bool
    {
        return $this->consent_at !== null;
    }
}
