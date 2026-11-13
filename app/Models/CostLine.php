<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A-4: one platform cost for one month, typed by a super admin (API feeds later). */
class CostLine extends Model
{
    public const CATEGORIES = ['server', 'ai', 'storage', 'data_vendor', 'email', 'other'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount_usd' => 'float'];
    }
}
