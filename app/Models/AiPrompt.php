<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** WS-32 (D-301): a saved AI box prompt shared by one agency's team; owner and admins edit, everyone uses. */
class AiPrompt extends Model
{
    protected $guarded = [];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
