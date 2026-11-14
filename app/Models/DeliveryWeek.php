<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** DS-23 (Step 1a, D-308): one week's delivery-app order counts for a client (no order IDs or customer data). */
class DeliveryWeek extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'orders_count' => 'integer',
            'orders_value' => 'float',
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
