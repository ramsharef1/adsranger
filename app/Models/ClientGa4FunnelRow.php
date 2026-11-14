<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientGa4FunnelRow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sessions' => 'integer',
            'engaged_sessions' => 'integer',
            'add_to_carts' => 'integer',
            'key_events' => 'float',
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
