<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** AI Workspace: one night shift run (run log: time, clients checked, items found, key cost, why it failed). */
class AiNightRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime', 'cost_usd' => 'decimal:4'];
    }
}
