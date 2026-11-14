<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoLocalLocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hours_json' => 'array',
            'nap_issues' => 'array',
            'reviews_json' => 'array',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
