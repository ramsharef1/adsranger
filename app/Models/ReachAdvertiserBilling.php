<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** FR-REACH: how a direct advertiser (a client) pays for its offers. Default escrow_prepaid; balances are estimates. */
class ReachAdvertiserBilling extends Model
{
    protected $table = 'reach_advertiser_billing';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['escrow_balance' => 'float', 'credit_cap' => 'float'];
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'advertiser_id');
    }
}
