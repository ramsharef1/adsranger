<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row of normalised performance data (network, date, campaign). */
class DailyMetric extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
