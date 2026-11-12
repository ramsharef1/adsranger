<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One finding: a rule code (App\Support\Seo\Audit\Catalog) on one page, or site-wide when the page is null. */
class SeoIssue extends Model
{
    protected $guarded = [];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoAudit::class, 'seo_audit_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(SeoAuditPage::class, 'seo_audit_page_id');
    }
}
