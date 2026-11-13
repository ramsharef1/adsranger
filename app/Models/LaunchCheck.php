<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A-8: one go / no-go line for launch, with an owner and a date. */
class LaunchCheck extends Model
{
    public const STATUSES = ['open', 'done', 'blocked'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_on' => 'date'];
    }
}
