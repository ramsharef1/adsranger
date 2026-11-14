<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'matched' => 'boolean',
            'evaluated_metrics' => 'array',
            'proposed_actions' => 'array',
            'ran_at' => 'datetime',
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

    public function strategy(): BelongsTo
    {
        return $this->belongsTo(AutomationStrategy::class, 'strategy_id');
    }
}
