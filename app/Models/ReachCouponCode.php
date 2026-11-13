<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** FR-REACH coupon code: vip / ai_bot codes belong to one publisher; pool codes rotate and match by copy intent. */
class ReachCouponCode extends Model
{
    public const TIERS = ['vip', 'ai_bot', 'pool'];

    protected $guarded = [];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ReachOffer::class, 'offer_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(ReachPublisher::class, 'publisher_id');
    }
}
