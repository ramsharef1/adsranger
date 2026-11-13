<?php

namespace App\Console\Commands;

use App\Support\PlatformHealth;
use Illuminate\Console\Command;

/** A-3: run the platform health check now (also scheduled every 10 minutes). */
class PlatformHealthCommand extends Command
{
    protected $signature = 'platform:health';

    protected $description = 'Check the server log, queue, syncs and uptime; push the super admins when something is wrong';

    public function handle(): int
    {
        $problems = PlatformHealth::check();
        if ($problems === []) {
            $this->info('health ok');

            return self::SUCCESS;
        }
        foreach ($problems as $p) {
            $this->warn($p['kind'].': '.$p['text']);
        }

        return self::SUCCESS;
    }
}
