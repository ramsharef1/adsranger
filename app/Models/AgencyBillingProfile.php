<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** E2-067: the agency's legal name, tax number, currency and country, as printed on invoice drafts. */
class AgencyBillingProfile extends Model
{
    protected $guarded = ['id'];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
