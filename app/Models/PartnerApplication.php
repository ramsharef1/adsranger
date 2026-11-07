<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Website P-25 /partners application (D-219). Reviewed by Rami in /admin › Partner applications; never emailed for marketing. */
class PartnerApplication extends Model
{
    public const CONSENT_TEXT_VERSION = 'partners-v1';

    /** @var list<string> */
    public const CLIENT_RANGES = ['1-5', '6-15', '16-40', '40+'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['consent_at' => 'datetime'];
    }
}
