<?php

namespace App\Jobs;

use App\Models\SeoAudit;
use App\Support\Seo\Audit\SiteAuditor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Site audit A1 (FR-089): crawls one site (≤ 200 pages at 1 request / second) and stores the findings. */
class RunSeoAudit implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** 200 pages × 1 s + up to 50 link checks + fetch time. */
    public int $timeout = 900;

    public function __construct(public int $auditId, public ?string $keyword = null) {}

    public function handle(): void
    {
        $audit = SeoAudit::with('site')->find($this->auditId);
        if (! $audit || ! $audit->site || $audit->status !== 'queued') {
            return;
        }
        (new SiteAuditor)->run($audit, $this->keyword);
    }
}
