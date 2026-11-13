<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** PS-21 / XS-19 / XS-20 (R5): one payday or school / exam period for a country, entered by the agency. */
class MarketDate extends Model
{
    public const KINDS = ['payday', 'school_holiday', 'exams'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'day_of_month' => 'integer'];
    }
}
