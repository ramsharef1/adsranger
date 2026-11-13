<?php

namespace App\Jobs;

use App\Connectors\ConnectorRegistry;
use App\Models\AdAccount;
use App\Support\AiWorkspace\FreePlan;
use App\Support\Trial;
use App\Sync\SyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Nightly: re-pull the last 3 days (late attribution) for every tracked account. */
class DailySync implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        AdAccount::with(['network', 'connection'])->where('is_active', true)
            ->whereHas('connection', fn ($q) => $q->where('status', '!=', 'disconnected'))
            ->get()
            // D-61: no syncs for read-only / closed / archived trial workspaces (self-signup only).
            ->filter(fn (AdAccount $a) => ! Trial::jobsStopped($a->agency))
            // Free plan: sync pauses after 30 days with no sign-in and no AI call; it catches up on the next sign-in.
            ->filter(fn (AdAccount $a) => ! (FreePlan::isFree($a->agency) && FreePlan::inactive($a->agency)))
            ->filter(fn (AdAccount $a) => $a->network && ConnectorRegistry::for($a->network)->supportsSync())
            ->each(function (AdAccount $a) {
                $tz = $a->timezone ?: ($a->agency->timezone ?? 'UTC');
                $today = now($tz);
                SyncAdAccount::dispatch($a->id, $today->copy()->subDays(3)->toDateString(), $today->toDateString(), 'daily');
                CheckAccountHealth::dispatch($a->id);
                if (! $a->backfilled_at) {
                    SyncService::queueBackfill($a);
                }
            });
    }
}
