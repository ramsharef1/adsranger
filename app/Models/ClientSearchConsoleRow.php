<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSearchConsoleRow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'clicks' => 'integer',
            'impressions' => 'integer',
            'ctr' => 'float',
            'position' => 'float',
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
