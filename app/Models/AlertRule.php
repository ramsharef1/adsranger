<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-agency override of an alert rule's defaults. */
class AlertRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'thresholds' => 'array'];
    }
}
