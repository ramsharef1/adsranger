<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** FR-REACH ledger row. `status` is always "estimate" before launch (D-262). */
class ReachLedgerEntry extends Model
{
    protected $table = 'reach_ledger';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'float', 'net_commission' => 'float', 'meta' => 'array',
            'converted_at' => 'datetime', 'cleared_at' => 'datetime',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(ReachPublisher::class, 'publisher_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ReachOffer::class, 'offer_id');
    }
}
