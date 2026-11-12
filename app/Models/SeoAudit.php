<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One site audit run (FR-089 A1): status, progress, score and counts; pages and issues hang off it. */
class SeoAudit extends Model
{
    public const STATUSES = ['queued', 'running', 'done', 'failed', 'blocked', 'site_down', 'stopped'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(SeoSite::class, 'seo_site_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(SeoAuditPage::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(SeoIssue::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['queued', 'running'], true);
    }
}
