<?php

namespace App\Jobs;

use App\Support\AiWorkspace\NightShift;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Hourly: run the AI Workspace night shift for workspaces whose chosen hour it is (their time zone), once a day each. */
class RunNightShift implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;

    public function handle(): void
    {
        foreach (NightShift::due() as $shift) {
            try {
                NightShift::run($shift);
            } catch (Throwable $e) {
                Log::warning('Night shift failed', ['agency' => $shift->agency_id, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            }
        }
    }
}
