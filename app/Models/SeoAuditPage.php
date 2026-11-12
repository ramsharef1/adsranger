<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A crawled page inside an audit. */
class SeoAuditPage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['hreflang' => 'array', 'noindex' => 'boolean'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'seo_audit_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(SeoIssue::class);
    }
}
