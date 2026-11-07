<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * #77 / #115 Outbound webhook + read-only JSON feed. Two separate secrets: the feed token (in the feed URL, stored
 * encrypted + sha256 hash for lookup) and the HMAC signing secret (never shown after creation, never in a URL).
 */
class AgencyWebhook extends Model
{
    protected $fillable = [
        'agency_id', 'client_id', 'name', 'target_url', 'events', 'is_active', 'last_triggered_at',
        'feed_token_hash', 'feed_token', 'signing_secret', 'last_status', 'last_error', 'failures',
    ];

    protected $hidden = ['feed_token', 'signing_secret', 'feed_token_hash', 'secret_token'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'feed_token' => 'encrypted',
            'signing_secret' => 'encrypted',
        ];
    }

    /**
     * Create a webhook with a fresh feed token and signing secret. Returns the plain values once, for the creator.
     *
     * @return array{0: self, 1: string, 2: string} webhook, feed token, signing secret
     */
    public static function issue(array $attributes): array
    {
        $token = Str::random(40);
        $secret = 'whsec_'.Str::random(40);
        $hook = static::create($attributes + [
            'feed_token_hash' => hash('sha256', $token), 'feed_token' => $token, 'signing_secret' => $secret,
            'is_active' => true,
        ]);

        return [$hook, $token, $secret];
    }

    public static function findByFeedToken(string $token): ?self
    {
        if (strlen($token) !== 40) {
            return null;
        }

        return static::where('feed_token_hash', hash('sha256', $token))->where('is_active', true)->first();
    }

    public function feedUrl(): ?string
    {
        return $this->feed_token ? route('api.export.feed', ['token' => $this->feed_token]) : null;
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
