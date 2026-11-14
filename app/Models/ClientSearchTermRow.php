<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSearchTermRow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'impressions' => 'integer',
            'clicks' => 'integer',
            'spend' => 'float',
            'conversions' => 'float',
            'revenue' => 'float',
            'stat_date' => 'date',
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
