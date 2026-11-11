<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Website P-25 /partners application (D-219). Reviewed by Rami in /admin › Partner applications; never emailed for marketing. */
class PartnerApplication extends Model
{
    public const CONSENT_TEXT_VERSION = 'partners-v1';

    /** @var list<string> */
    public const CLIENT_RANGES = ['1-5', '6-15', '16-40', '40+'];

    public const BADGES = ['certified' => 'Certified Partner', 'premier' => 'Premier Partner'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['consent_at' => 'datetime', 'is_published' => 'boolean', 'published_at' => 'datetime', 'invited_at' => 'datetime', 'magic_link_expires_at' => 'datetime'];
    }

    public function provisionedAgency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'provisioned_agency_id');
    }
}
