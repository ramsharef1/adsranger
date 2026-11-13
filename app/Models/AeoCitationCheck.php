<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** E2-065: a readiness check (our own fetches) or a probe run (the workspace's own AI key). */
class AeoCitationCheck extends Model
{
    public const SOURCES = ['static_readiness', 'byo_probe'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['signals' => 'array', 'citations' => 'array', 'checked_at' => 'datetime', 'readiness_score' => 'integer'];
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(AeoBrandMonitor::class, 'monitor_id');
    }
}
