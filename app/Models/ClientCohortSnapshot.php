<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCohortSnapshot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'customers_count' => 'integer',
            'cac_amount' => 'float',
            'd30_ltv' => 'float',
            'd60_ltv' => 'float',
            'd90_ltv' => 'float',
            'd180_ltv' => 'float',
            'payback_days' => 'integer',
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
