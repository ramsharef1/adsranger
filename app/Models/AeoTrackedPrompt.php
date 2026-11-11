<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AeoTrackedPrompt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'competitors_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function citationRuns(): HasMany
    {
        return $this->hasMany(AeoCitationRun::class, 'aeo_tracked_prompt_id');
    }
}
