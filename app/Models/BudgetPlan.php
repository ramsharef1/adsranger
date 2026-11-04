<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A client's planned budget / target for one month (#45). */
class BudgetPlan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['month' => 'date', 'budget' => 'float', 'target_results' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** The plan row for a client's month, if any. */
    public static function for(Client $c, string $month): ?self
    {
        return static::where('client_id', $c->id)->whereDate('month', substr($month, 0, 7).'-01')->first();
    }
}
