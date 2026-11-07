<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitlistEntry extends Model
{
    /** P-1 consent wording version stored with every new signup (D-172). */
    public const CONSENT_TEXT_VERSION = 'waitlist-v1';

    /** @var list<string> ISO 3166-1 alpha-2 values offered on the form; XX = other country. */
    public const COUNTRIES = ['JO', 'SA', 'AE', 'KW', 'QA', 'BH', 'OM', 'EG', 'IQ', 'LB', 'PS', 'SY', 'MA', 'DZ', 'TN', 'LY', 'SD', 'XX'];

    /** @var list<string> */
    public const SPEND_RANGES = ['under_10k', '10k_50k', '50k_200k', 'over_200k'];

    /** @var list<string> */
    public const NETWORKS = ['meta', 'google', 'tiktok', 'snapchat', 'microsoft', 'linkedin', 'x', 'other'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['consent_at' => 'datetime'];
    }

    /** Only rows with recorded consent may ever be emailed (D-152). */
    public function mayBeEmailed(): bool
    {
        return $this->consent_at !== null;
    }
}
