<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AeoCitationRun extends Model
{
    public const ENGINES = ['chatgpt', 'perplexity', 'gemini', 'claude', 'ai_overviews'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'client_mentioned' => 'boolean',
            'competitor_mentions_json' => 'array',
            'cited_urls_json' => 'array',
            'checked_at' => 'datetime',
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

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AeoTrackedPrompt::class, 'aeo_tracked_prompt_id');
    }
}
