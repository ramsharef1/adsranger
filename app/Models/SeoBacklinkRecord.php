<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoBacklinkRecord extends Model
{
    public const STATUSES = ['active', 'lost'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_flagged_disavow' => 'boolean',
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
