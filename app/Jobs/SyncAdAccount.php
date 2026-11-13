<?php

namespace App\Jobs;

use App\Models\AdAccount;
use App\Support\Ops\Lanes;
use App\Support\Trial;
use App\Sync\SyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncAdAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public int $adAccountId, public string $from, public string $to, public string $kind = 'daily')
    {
        $this->onQueue(Lanes::for(self::class));
    }

    public function backoff(): array
    {
        return [120];
    }

    public function handle(): void
    {
        $a = AdAccount::with(['network', 'connection'])->find($this->adAccountId);
        if (! $a || ! $a->is_active || $a->connection?->status === 'disconnected' || Trial::jobsStopped($a->agency)) {
            return;
        }
        SyncService::sync($a, $this->from, $this->to, $this->kind);
    }
}
