<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetReallocationLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'shifted_amount' => 'float',
        'monthly_cap' => 'float',
        'projected_spend' => 'float',
        'decision_meta' => 'array',
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