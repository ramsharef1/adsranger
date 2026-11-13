<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** XS-13 / XS-23 (R4, D-306): the private no-login link a client's front desk uses to log leads. Looked up by sha256 of the token. */
class LeadDeskLink extends Model
{
    protected $guarded = [];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'revoked_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** A new link for the client; any older link stops working. */
    public static function issue(Client $client, ?User $by): self
    {
        static::where('client_id', $client->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $token = Str::random(40);

        return static::create(['agency_id' => $client->agency_id, 'client_id' => $client->id, 'token' => $token, 'token_hash' => hash('sha256', $token), 'created_by' => $by?->id]);
    }

    public static function findActive(string $token): ?self
    {
        return strlen($token) === 40 ? static::whereNull('revoked_at')->where('token_hash', hash('sha256', $token))->first() : null;
    }

    public function url(): string
    {
        return url('/l/'.$this->token);
    }
}
