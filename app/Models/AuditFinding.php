<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** #173 One ranked finding of an account audit. Text is built at view time from check_key + params, in the reader's language. */
class AuditFinding extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['params' => 'array', 'dismissed_at' => 'datetime', 'money_at_risk' => 'float', 'results_at_risk' => 'float'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }
}
