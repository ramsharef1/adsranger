<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** BX-07: one page on another site that links to adsranger.com (from the Backlinks Link Tracker), rechecked monthly. */
class OwnLink extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime', 'lost_at' => 'datetime'];
    }
}
