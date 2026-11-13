<?php

namespace App\Models;

use Database\Factories\ReachOfferFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** FR-REACH: an affiliate or direct offer imported by an admin (CSV or by hand). Payouts are 3-decimal amounts. */
class ReachOffer extends Model
{
    /** @use HasFactory<ReachOfferFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payout_amount' => 'float', 'publisher_payout_amount' => 'float', 'allow_incent' => 'boolean',
            'countries' => 'array', 'keywords' => 'array',
        ];
    }

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'advertiser_id');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ReachCouponCode::class, 'offer_id');
    }

    public function landingHost(): ?string
    {
        $host = parse_url((string) $this->landing_url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }
}
