<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** XS-24 / NB-36 (R4, D-305): a licence the client's ads must show (type, number, expiry). We never judge compliance. */
class ClientLicence extends Model
{
    public const KINDS = ['real_estate', 'health', 'influencer', 'advertiser', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return array<string, string> */
    public static function kindLabels(): array
    {
        return ['real_estate' => __('Real-estate ad licence'), 'health' => __('Health ad permit'), 'influencer' => __('Influencer / advertiser licence'),
            'advertiser' => __('Advertiser licence'), 'other' => __('Other licence')];
    }
}
