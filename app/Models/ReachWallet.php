<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** FR-REACH dual wallet: pending → cleared, plus the early-cashout reserve and ad credit. Estimates only (D-262). */
class ReachWallet extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'pending_balance' => 'float', 'cleared_balance' => 'float', 'reserve_balance' => 'float',
            'advanced_balance' => 'float', 'ad_credit_balance' => 'float',
        ];
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(ReachPublisher::class, 'publisher_id');
    }
}
