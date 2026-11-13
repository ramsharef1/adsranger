<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** FS-16 / XS-14 (R4, D-304): one week's COD outcomes for a client (or one of its campaigns): delivered, returned, cancelled. */
class CodWeek extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['week_start' => 'date'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function placed(): int
    {
        return (int) $this->delivered + (int) $this->returned + (int) $this->cancelled;
    }
}
