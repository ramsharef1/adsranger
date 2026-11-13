<?php

namespace App\Jobs;

use App\Audit\AccountAudit;
use App\Models\Audit;
use App\Models\Client;
use App\Support\Ops\Lanes;
use App\Support\Trial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** #173 Runs the account audit for one client. Queued after a client's first successful sync, so it is ready within minutes. */
class RunAccountAudit implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $clientId, public string $trigger = 'first_sync')
    {
        $this->onQueue(Lanes::for(self::class));
    }

    public function handle(): void
    {
        $client = Client::with('agency')->find($this->clientId);
        if (! $client || ! $client->agency || Trial::jobsStopped($client->agency)) {
            return;
        }
        if ($this->trigger === 'first_sync' && Audit::where('client_id', $client->id)->exists()) {
            return;
        }
        AccountAudit::run($client, $this->trigger);
    }
}
