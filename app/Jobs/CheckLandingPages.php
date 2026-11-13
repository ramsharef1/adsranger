<?php

namespace App\Jobs;

use App\Models\LandingPage;
use App\Support\LandingPages;
use App\Support\Ops\Lanes;
use App\Support\Trial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Nightly (and "Check all now"): check landing pages seen in ads in the last 30 days, plus pages added by hand (#49). */
class CheckLandingPages implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public ?int $agencyId = null)
    {
        $this->onQueue(Lanes::for(self::class));
    }

    public function handle(): void
    {
        LandingPage::query()->when($this->agencyId, fn ($q) => $q->where('agency_id', $this->agencyId))
            ->where(fn ($q) => $q->where('source', 'manual')->orWhere('last_seen_at', '>=', now()->subDays(30)))
            ->whereHas('agency') // archived agencies excluded
            ->with('agency')->orderBy('checked_at')->limit(2000)->get()
            ->reject(fn (LandingPage $lp) => Trial::jobsStopped($lp->agency))
            ->each(function (LandingPage $lp) {
                try {
                    LandingPages::check($lp);
                } catch (Throwable $e) {
                    Log::warning('Landing page check failed', ['id' => $lp->id, 'error' => mb_substr($e->getMessage(), 0, 200)]);
                }
                usleep(200000); // be gentle with client sites
            });
    }
}
