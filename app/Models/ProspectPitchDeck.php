<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectPitchDeck extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'audit_snapshot' => 'array',
            'tier_quotes' => 'array',
            'revoked_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
