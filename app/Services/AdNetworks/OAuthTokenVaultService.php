<?php

namespace App\Services\AdNetworks;

use App\Models\AdAccount;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Connection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class OAuthTokenVaultService
{
    public const REFRESH_BUFFER_SECONDS = 900; // 15 minutes before expiry

    public function __construct(
        private readonly StageOneOAuthAdapter $adapter = new StageOneOAuthAdapter,
    ) {}

    /**
     * Store encrypted OAuth credentials directly on the AdAccount's Connection record and reset any error state.
     *
     * @param  array{access_token: string, refresh_token?: ?string, expires_in?: int, scopes?: list<string>|string}  $payload
     */
    public function storeCredentials(AdAccount $account, array $payload): Connection
    {
        if (empty($payload['access_token'])) {
            throw new RuntimeException('Cannot store empty OAuth access_token.');
        }

        $connection = $this->resolveConnection($account);
        $expiresIn = (int) ($payload['expires_in'] ?? 3600);
        $scopes = $payload['scopes'] ?? $connection->scopes;
        if (is_array($scopes)) {
            $scopes = implode(',', $scopes);
        }

        $connection->update([
            'status' => 'active',
            'access_token' => (string) $payload['access_token'],
            'refresh_token' => array_key_exists('refresh_token', $payload) && filled($payload['refresh_token'])
                ? (string) $payload['refresh_token']
                : $connection->refresh_token,
            'token_expires_at' => CarbonImmutable::now()->addSeconds($expiresIn),
            'scopes' => $scopes,
            'last_error' => null,
        ]);

        // Resolve any open oauth_reauth_required alert for this account once reconnected
        Alert::where('agency_id', $account->agency_id)
            ->where('fingerprint', self::alertFingerprint($account))
            ->whereIn('status', ['open', 'snoozed'])
            ->update(['status' => 'resolved', 'resolved_at' => now()]);

        return $connection->refresh();
    }

    /**
     * Retrieve a valid decrypted access token, refreshing atomically under a lock if within the 15-minute expiry buffer.
     * When $refreshCallback is null, uses StageOneOAuthAdapter automatically.
     *
     * @param  null|callable(string $refreshToken, AdAccount $account, Connection $connection): array{access_token: string, refresh_token?: ?string, expires_in?: int}  $refreshCallback
     */
    public function getValidAccessToken(AdAccount $account, ?callable $refreshCallback = null): string
    {
        if ($this->isCircuitOpen($account)) {
            throw new RuntimeException("Sync circuit breaker is open for AdAccount [{$account->id}]. Re-authentication required.");
        }

        $connection = $this->resolveConnection($account);
        if (empty($connection->access_token)) {
            throw new RuntimeException("No OAuth access_token stored on Connection [{$connection->id}] for AdAccount [{$account->id}].");
        }

        if (! $this->needsRefresh($connection)) {
            return (string) $connection->access_token;
        }

        if (empty($connection->refresh_token)) {
            $this->tripCircuitBreaker($account, 'token_expired', 'OAuth access token expired and no refresh token is available.');
            throw new RuntimeException("OAuth token expired for AdAccount [{$account->id}].");
        }

        $callback = $refreshCallback ?? fn (string $rt, AdAccount $acc, Connection $conn): array => $this->adapter->refresh($rt, $acc, $conn);

        $lock = Cache::lock("oauth:refresh:connection:{$connection->id}", 30);
        if (! $lock->get()) {
            $fresh = $connection->fresh();

            return (string) $fresh->access_token;
        }

        try {
            $fresh = $connection->fresh();
            if (! $this->needsRefresh($fresh)) {
                return (string) $fresh->access_token;
            }

            $newTokens = $callback((string) $fresh->refresh_token, $account, $fresh);
            $updated = $this->storeCredentials($account, [
                'access_token' => $newTokens['access_token'],
                'refresh_token' => $newTokens['refresh_token'] ?? $fresh->refresh_token,
                'expires_in' => $newTokens['expires_in'] ?? 3600,
            ]);

            AuditLog::record('connection.oauth_refreshed', $account->agency, [
                'connection_id' => $connection->id,
                'ad_account_id' => $account->id,
            ]);

            return (string) $updated->access_token;
        } catch (Throwable $e) {
            $this->tripCircuitBreaker($account, 'refresh_rejected', $e->getMessage());
            throw new RuntimeException("OAuth token refresh failed for AdAccount [{$account->id}]: ".$e->getMessage(), 0, $e);
        } finally {
            optional($lock)->release();
        }
    }

    public function isCircuitOpen(AdAccount $account): bool
    {
        $connection = $account->connection()->first();

        return $connection !== null && in_array($connection->status, ['error', 'disconnected'], true);
    }

    /**
     * Trip the circuit breaker on permanent OAuth failure (e.g., 190 OAuthException / invalid_grant),
     * mark the Connection as 'error', and open/refresh one deduplicated critical Alert using UX-121 AlertText patterns.
     */
    public function tripCircuitBreaker(AdAccount $account, string $errorCode, string $reason): void
    {
        $connection = $this->resolveConnection($account);
        $connection->update([
            'status' => 'error',
            'last_error' => "[{$errorCode}] {$reason}",
        ]);

        AuditLog::record('connection.oauth_error', $account->agency, [
            'connection_id' => $connection->id,
            'ad_account_id' => $account->id,
            'error_code' => $errorCode,
        ]);

        $fp = self::alertFingerprint($account);
        $entityName = $account->name ?: ('#'.$account->id);
        // Stored in canonical English per UX-121 so AlertText translates dynamically with Unicode isolates
        $title = 'Connection problem: '.$entityName;
        $detail = 'The connection needs attention: '.$errorCode;

        $existing = Alert::where('agency_id', $account->agency_id)
            ->where('fingerprint', $fp)
            ->whereIn('status', ['open', 'snoozed'])
            ->first();

        if ($existing) {
            $existing->update([
                'title' => $title,
                'detail' => $detail,
                'last_seen_at' => now(),
                'payload' => ['error_code' => $errorCode, 'reason' => $reason],
            ]);

            return;
        }

        Alert::create([
            'agency_id' => $account->agency_id,
            'client_id' => $account->client_id,
            'ad_account_id' => $account->id,
            'rule_key' => 'oauth_reauth_required',
            'severity' => 'critical',
            'entity_type' => 'ad_account',
            'entity_id' => $account->id,
            'entity_name' => $entityName,
            'title' => $title,
            'detail' => $detail,
            'payload' => ['error_code' => $errorCode, 'reason' => $reason],
            'fingerprint' => $fp,
            'status' => 'open',
            'last_seen_at' => now(),
        ]);
    }

    public static function alertFingerprint(AdAccount $account): string
    {
        return "oauth_reauth_required:ad_account:{$account->id}";
    }

    private function resolveConnection(AdAccount $account): Connection
    {
        $connection = $account->connection()->first();
        if (! $connection) {
            throw new RuntimeException("AdAccount [{$account->id}] has no associated Connection.");
        }
        if ((int) $connection->agency_id !== (int) $account->agency_id) {
            throw new RuntimeException("Tenant boundary violation accessing Connection [{$connection->id}] for AdAccount [{$account->id}].");
        }

        return $connection;
    }

    private function needsRefresh(Connection $connection): bool
    {
        if (! $connection->token_expires_at) {
            return false;
        }

        return CarbonImmutable::instance($connection->token_expires_at)
            ->subSeconds(self::REFRESH_BUFFER_SECONDS)
            ->isPast();
    }
}
