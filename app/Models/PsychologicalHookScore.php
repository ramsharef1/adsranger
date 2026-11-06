<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsychologicalHookScore extends Model
{
    protected $guarded = [];

    protected $casts = [
        'overall_score' => 'integer',
        'trigger_scores' => 'array',
        'dialect_variants' => 'array',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}