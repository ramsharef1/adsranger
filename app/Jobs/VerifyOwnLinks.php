<?php

namespace App\Jobs;

use App\Support\Links\OwnLinks;
use App\Support\Ops\Lanes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** BX-07: the monthly recheck of every known link to adsranger.com (also queued from the /admin Links board). */
class VerifyOwnLinks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct()
    {
        $this->onQueue(Lanes::for(self::class));
    }

    public int $timeout = 1800;

    public int $tries = 1;

    public function handle(): void
    {
        OwnLinks::checkAll();
    }
}
