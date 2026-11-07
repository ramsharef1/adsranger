<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** #173 One account-audit run for a client. */
class Audit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['ran_at' => 'datetime', 'window_from' => 'date', 'window_to' => 'date', 'money_at_risk' => 'float'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class)->orderBy('rank');
    }
}
