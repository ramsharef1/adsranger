<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One agency's OAuth connection to an ad network. Tokens are encrypted at rest. */
class Connection extends Model
{
    protected $guarded = [];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(AdNetwork::class, 'ad_network_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adAccounts(): HasMany
    {
        return $this->hasMany(AdAccount::class);
    }

    /** Remove tokens and stop syncing. Collected data is deleted later per the privacy policy. */
    public function disconnect(): void
    {
        $this->update(['status' => 'disconnected', 'access_token' => null, 'refresh_token' => null, 'token_expires_at' => null]);
        $this->adAccounts()->update(['is_active' => false]);
        AuditLog::record('connection.disconnected', $this->agency, ['network' => $this->network?->key, 'connection_id' => $this->id]);
    }
}
