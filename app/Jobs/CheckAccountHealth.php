<?php

namespace App\Jobs;

use App\Connectors\ConnectorRegistry;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Support\Trial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/** Nightly account health check (#91): status, payments, spending limit, rejected ads. Stored on the account; the alert rule reads it. */
class CheckAccountHealth implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $adAccountId) {}

    public function handle(): void
    {
        $a = AdAccount::with(['network', 'connection'])->find($this->adAccountId);
        if (! $a || ! $a->is_active || ! $a->network || Trial::jobsStopped($a->agency)) {
            return;
        }
        try {
            $issues = ConnectorRegistry::for($a->network)->health($a);
        } catch (\Throwable $e) {
            $issues = [['medium', 'Health check failed: '.Str::limit($e->getMessage(), 200)]];
        }
        $rejected = Ad::where('ad_account_id', $a->id)->whereIn('status', ['DISAPPROVED', 'REJECTED', 'WITH_ISSUES'])->count();
        if ($rejected && ! collect($issues)->contains(fn ($i) => str_contains($i[1], 'disapproved'))) {
            $issues[] = ['medium', "$rejected ad(s) rejected or with issues on ".$a->network->name.'.'];
        }
        $a->forceFill(['health' => $issues ?: null, 'health_checked_at' => now()])->saveQuietly();
    }
}
