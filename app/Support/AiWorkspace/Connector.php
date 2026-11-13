<?php

namespace App\Support\AiWorkspace;

use App\Models\AiConnection;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * AI Workspace M1 slice 2: the connection behind the current connector request, and the guard that watches it
 * (spec v1 §16.4). Everything a tool reads goes through `client()`, so a token can never reach a client or a workspace
 * the person didn't allow.
 */
class Connector
{
    public static function forToken(?User $user): ?AiConnection
    {
        $clientId = $user?->token()?->oauth_client_id;
        if (! $user || ! $clientId || AiKill::off('connector')) { // FS-49: paused for everyone from /admin
            return null;
        }

        return AiConnection::query()->where('user_id', $user->id)->where('oauth_client_id', $clientId)
            ->where('status', '!=', 'revoked')->latest('id')->first();
    }

    public static function current(): ?AiConnection
    {
        $c = request()->attributes->get('ai_connection');

        return $c instanceof AiConnection ? $c : null;
    }

    /** A client this connection may read, or null. A refused id counts as scope probing. */
    public static function client(AiConnection $connection, mixed $id): ?Client
    {
        $id = (int) $id;
        if (! $connection->allows($id)) {
            static::anomaly($connection, 'scope_probes', 'Asked for a client it is not allowed to read.');

            return null;
        }

        return Client::query()->where('agency_id', $connection->agency_id)->whereKey($id)->first();
    }

    /** Count a bad sign; past the limit in the window, pause the connection and tell the owner (audit log + screen). */
    public static function anomaly(AiConnection $connection, string $kind, string $reason): void
    {
        $window = (int) config('ai_workspace.anomaly.window_minutes', 10);
        $limit = (int) config("ai_workspace.anomaly.$kind", 3);
        $key = "ai-anomaly:{$connection->id}:$kind";
        Cache::add($key, 0, now()->addMinutes($window));
        if (Cache::increment($key) >= $limit) {
            $connection->pause($reason);
        }
    }

    /** First tool call that worked: the connection is now "Connected · verified" (v1 §14.9 #7). */
    public static function markVerified(AiConnection $connection): void
    {
        if ($connection->status === 'waiting') {
            $connection->update(['status' => 'verified', 'verified_at' => now()]);
        }
    }
}
