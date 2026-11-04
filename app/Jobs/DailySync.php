<?php

namespace App\Jobs;

use App\Connectors\ConnectorRegistry;
use App\Models\AdAccount;
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
            ->filter(fn (AdAccount $a) => $a->network && ConnectorRegistry::for($a->network)->supportsSync())
            ->each(function (AdAccount $a) {
                $tz = $a->timezone ?: ($a->agency->timezone ?? 'UTC');
                $today = now($tz);
                SyncAdAccount::dispatch($a->id, $today->copy()->subDays(3)->toDateString(), $today->toDateString(), 'daily');
                if (! $a->backfilled_at) {
                    \App\Sync\SyncService::queueBackfill($a);
                }
            });
    }
}
