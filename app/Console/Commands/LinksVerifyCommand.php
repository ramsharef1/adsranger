<?php

namespace App\Console\Commands;

use App\Support\Links\OwnLinks;
use Illuminate\Console\Command;

/** BX-07: `php artisan links:verify` (was earned_links.py). Exit 1 when any known link is lost. */
class LinksVerifyCommand extends Command
{
    protected $signature = 'links:verify';

    protected $description = 'Recheck every known link to adsranger.com: still live, rel, anchor, anchor mix';

    public function handle(): int
    {
        $r = OwnLinks::checkAll();
        $mix = collect(OwnLinks::mix())->map(fn ($m, $k) => "$k {$m['count']} ({$m['pct']}%)")->implode(' · ');
        $this->line("# Known links: {$r['checked']} checked · {$r['live']} live · ".count($r['lost']).' lost · anchor mix: '.($mix ?: 'n/a'));
        foreach ($r['lost'] as $u) {
            $this->line('- LOST · '.$u.(in_array($u, $r['newly_lost'], true) ? ' (new)' : ''));
        }

        return $r['lost'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
