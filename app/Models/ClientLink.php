<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** #56: a signed, revocable link to one client's read-only report. Looked up by sha256 of the token. */
class ClientLink extends Model
{
    protected $guarded = [];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_viewed_at' => 'datetime', 'views' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public static function issue(Client $client, ?User $by = null, ?string $label = null, ?int $days = null): self
    {
        $token = Str::random(40);

        return static::create([
            'agency_id' => $client->agency_id, 'client_id' => $client->id, 'label' => $label ?: null,
            'token' => $token, 'token_hash' => hash('sha256', $token),
            'expires_at' => $days ? now()->addDays($days) : null, 'created_by' => $by?->id,
        ]);
    }

    public static function findActive(string $token): ?self
    {
        if (strlen($token) !== 40) {
            return null;
        }

        return static::active()->where('token_hash', hash('sha256', $token))->first();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('revoked_at')->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function url(): string
    {
        return url('/r/'.$this->token);
    }
}
