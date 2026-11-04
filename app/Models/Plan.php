<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'price_usd_month' => 'decimal:2'];
    }

    public function networks(): BelongsToMany
    {
        return $this->belongsToMany(AdNetwork::class);
    }
}
