<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\Connection;
use App\Services\AdNetworks\OAuthTokenVaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class OAuthTokenVaultTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(): AdAccount
    {
        $agency = Agency::create(['name' => 'Amman Growth', 'slug' => 'amman-growth', 'currency' => 'USD']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Noor Retail', 'currency' => 'USD']);
        $network = AdNetwork::firstOrCreate(
            ['key' => 'meta'],
            ['name' => 'Meta Ads', 'tier' => 'starter', 'enabled' => true, 'status' => 'live', 'environment' => 'live']
        );
        $connection = Connection::create([
            'agency_id' => $agency->id,
            'ad_network_id' => $network->id,
            'status' => 'active',
            'environment' => 'live',
        ]);

        return AdAccount::create([
            'agency_id' => $agency->id,
            'connection_id' => $connection->id,
            'ad_network_id' => $network->id,
            'client_id' => $client->id,
            'external_id' => 'act_998877',
            'name' => 'Noor Meta Account',
            'currency' => 'USD',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);
    }

    public function test_stores_encrypted_tokens_on_connection_and_returns_valid_access_token(): void
    {
        $account = $this->makeAccount();
        $vault = app(OAuthTokenVaultService::class);

        $vault->storeCredentials($account, [
            'access_token' => 'meta_live_secret_token_123',
            'refresh_token' => 'meta_refresh_token_456',
            'expires_in' => 7200,
            'scopes' => ['ads_read'],
        ]);

        $this->assertFalse($vault->isCircuitOpen($account));
        $this->assertSame('meta_live_secret_token_123', $vault->getValidAccessToken($account));

        // Verify raw DB column is encrypted at rest, never plaintext
        $rawToken = DB::table('connections')->where('id', $account->connection_id)->value('access_token');
        $this->assertNotSame('meta_live_secret_token_123', $rawToken);
    }

    public function test_refreshes_expiring_token_atomically_via_callback(): void
    {
        $account = $this->makeAccount();
        $vault = app(OAuthTokenVaultService::class);

        // Store token expiring in 60 seconds (inside the 900s buffer)
        $vault->storeCredentials($account, [
            'access_token' => 'expiring_soon_token',
            'refresh_token' => 'valid_refresh_token',
            'expires_in' => 60,
        ]);

        $refreshed = $vault->getValidAccessToken($account, function (string $refreshToken) {
            $this->assertSame('valid_refresh_token', $refreshToken);

            return [
                'access_token' => 'rotated_fresh_token_999',
                'refresh_token' => 'rotated_refresh_token_999',
                'expires_in' => 7200,
            ];
        });

        $this->assertSame('rotated_fresh_token_999', $refreshed);
        $this->assertSame('rotated_fresh_token_999', $vault->getValidAccessToken($account));
    }

    public function test_failed_refresh_trips_circuit_breaker_and_emits_deduplicated_critical_alert(): void
    {
        $account = $this->makeAccount();
        $vault = app(OAuthTokenVaultService::class);

        $vault->storeCredentials($account, [
            'access_token' => 'expired_token',
            'refresh_token' => 'revoked_refresh_token',
            'expires_in' => 10,
        ]);

        try {
            $vault->getValidAccessToken($account, function () {
                throw new RuntimeException('190 OAuthException: User revoked access');
            });
            $this->fail('Expected RuntimeException when refresh fails.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('190 OAuthException', $e->getMessage());
        }

        $this->assertTrue($vault->isCircuitOpen($account));
        $this->assertSame(1, Alert::where('ad_account_id', $account->id)->where('rule_key', 'oauth_reauth_required')->where('status', 'open')->count());

        // Subsequent trip calls refresh last_seen_at without creating duplicate open alerts
        $vault->tripCircuitBreaker($account, 'invalid_grant', 'Duplicate trip test');
        $this->assertSame(1, Alert::where('ad_account_id', $account->id)->where('rule_key', 'oauth_reauth_required')->where('status', 'open')->count());

        // Re-storing valid credentials resolves the open alert automatically
        $vault->storeCredentials($account, [
            'access_token' => 'reconnected_fresh_token',
            'expires_in' => 7200,
        ]);
        $this->assertFalse($vault->isCircuitOpen($account));
        $this->assertSame(0, Alert::where('ad_account_id', $account->id)->where('rule_key', 'oauth_reauth_required')->where('status', 'open')->count());
    }
}
