<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** E2-067: how the agency charges one client: fixed fee, a share of ad spend, or both, with a minimum. */
class ClientRetainerTerm extends Model
{
    public const FEE_MODELS = ['fixed', 'percent_spend', 'hybrid'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['fixed_amount' => 'float', 'spend_percent' => 'float', 'min_fee' => 'float', 'tax_rate_percent' => 'float'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
