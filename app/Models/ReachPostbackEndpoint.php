<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** FR-REACH: one S2S postback URL per network account. Token stored as sha256; the HMAC secret is encrypted. */
class ReachPostbackEndpoint extends Model
{
    protected $guarded = [];

    protected $hidden = ['secret', 'token_hash'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'allowed_ips' => 'array', 'active' => 'boolean', 'last_received_at' => 'datetime'];
    }

    /** @return array{0: self, 1: string, 2: string} endpoint, plain token, plain secret (shown once) */
    public static function issue(string $network, string $label, array $allowedIps = [], ?User $by = null): array
    {
        $token = Str::random(40);
        $secret = Str::random(48);
        $endpoint = static::create([
            'network' => $network, 'label' => $label, 'token_hash' => hash('sha256', $token), 'secret' => $secret,
            'allowed_ips' => array_values(array_filter(array_map('trim', $allowedIps))) ?: null, 'created_by' => $by?->id,
        ]);

        return [$endpoint, $token, $secret];
    }

    public static function findActive(string $token): ?self
    {
        return strlen($token) === 40 ? static::where('active', true)->where('token_hash', hash('sha256', $token))->first() : null;
    }
}
