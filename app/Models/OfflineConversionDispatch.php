<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineConversionDispatch extends Model
{
    public const STAGES = ['qualified', 'won'];

    public const NETWORKS = ['meta_capi', 'google_offline'];

    public const STATUSES = ['queued_until_credentials', 'sent', 'failed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'deal_value' => 'float',
            'provider_response' => 'array',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
