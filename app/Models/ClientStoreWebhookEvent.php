<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** E2-063: one verified store webhook, kept once per (connection, event_uid) so a replay changes nothing. */
class ClientStoreWebhookEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['summary' => 'array', 'processed_at' => 'datetime'];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ClientStoreConnection::class, 'connection_id');
    }
}
