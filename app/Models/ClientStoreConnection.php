<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** E2-063: one store (Salla / Zid / Shopify) sending signed webhooks for a client. The secret is encrypted at rest. */
class ClientStoreConnection extends Model
{
    public const PLATFORMS = ['salla', 'zid', 'shopify'];

    public const STATUSES = ['webhook_ready', 'active', 'paused'];

    protected $guarded = ['id'];

    protected $hidden = ['webhook_secret'];

    protected function casts(): array
    {
        return ['webhook_secret' => 'encrypted', 'last_event_at' => 'datetime'];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ClientStoreWebhookEvent::class, 'connection_id');
    }

    public function receiverUrl(): string
    {
        return route('stores.webhook', ['token' => $this->webhook_token]);
    }
}
