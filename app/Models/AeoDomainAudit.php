<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AeoDomainAudit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'robots_bot_matrix_json' => 'array',
            'internal_link_orphans_json' => 'array',
            'snippet_rewrites_json' => 'array',
            'audited_at' => 'datetime',
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
