<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** AI Workspace: night shift settings for one workspace «المناوب الليلي» (M1 v0: read + morning list). */
class AiNightShift extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'client_ids' => 'array', 'last_run_on' => 'date'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
