<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialModerationRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
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
}
