<?php

namespace App\Jobs;

use App\Support\Ops\Lanes;
use App\Support\TrialLifecycle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Daily: D-61 trial lifecycle for self-signup agencies (App\Support\TrialLifecycle). Invited/admin agencies are never touched. */
class RunTrialLifecycle implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue(Lanes::for(self::class));
    }

    public function handle(): void
    {
        Log::info('trial lifecycle', TrialLifecycle::run());
    }
}
