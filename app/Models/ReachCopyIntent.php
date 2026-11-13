<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** FR-REACH tier 3: a visitor copied a pool code on a publisher's placement (no visitor data kept). */
class ReachCopyIntent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['copied_at' => 'datetime'];
    }
}
