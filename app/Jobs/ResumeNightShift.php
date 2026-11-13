<?php

namespace App\Jobs;

use App\Models\AiNightRun;
use App\Support\AiWorkspace\NightShift;
use App\Support\Ops\Lanes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** FS-09 (D-297): finish a night shift run after the agency's AI provider was down or rate-limited (every 30 min, up to 6 h). */
class ResumeNightShift implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;

    /** @param list<int> $clientIds */
    public function __construct(public int $runId, public array $clientIds)
    {
        $this->onQueue(Lanes::for(self::class));
    }

    public function handle(): void
    {
        $run = AiNightRun::find($this->runId);
        if ($run && $run->status === 'waiting') {
            NightShift::resume($run, $this->clientIds);
        }
    }
}
