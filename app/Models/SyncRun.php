<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['from' => 'date', 'to' => 'date', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function adAccount(): BelongsTo
    {
        return $this->belongsTo(AdAccount::class);
    }
}
