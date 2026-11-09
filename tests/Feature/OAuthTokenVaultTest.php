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
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OAuthTokenVaultTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(string $networkKey = 'meta', array $liveCredentials = []): AdAccount
    {
        $agency = Agency::firstOrCreate(
            ['slug' => 'amman-growth'],
            ['name' => 'Amman Growth', 'currency' => 'USD']
        );
        $client = Client::firstOrCreate(
            ['agency_id' => $agency->id, 'name' => 'Noor Retail'],
            ['currency' => 'USD']
        );
        $network = AdNetwork::updateOrCreate(
            ['key' => $networkKey],
            [
                'name' => strtoupper($networkKey).' Ads',
                'enabled' => true,
                'status' => 'live',
                'environment' => 'live',
                'credentials_live' => $liveCredentials,
            ]
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
            'external_id' => 'act_'.$networkKey.'_998877',
            'name' => 'Noor '.strtoupper($networkKey).' Account',
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

    public function test_stage_one_adapter_refreshes_meta_google_tiktok_and_snapchat_tokens_automatically(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['access_token' => 'meta_rotated_token', 'expires_in' => 5184000], 200),
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'google_rotated_token', 'expires_in' => 3600], 200),
            'business-api.tiktok.com/*' => Http::response(['code' => 0, 'message' => 'OK', 'data' => ['access_token' => 'tiktok_rotated_token', 'refresh_token' => 'tiktok_rt_2', 'expires_in' => 86400]], 200),
            'accounts.snapchat.com/*' => Http::response(['access_token' => 'snap_rotated_token', 'refresh_token' => 'snap_rt_2', 'expires_in' => 3600], 200),
        ]);

        $vault = app(OAuthTokenVaultService::class);

        $metaAcc = $this->makeAccount('meta', ['app_id' => '111', 'app_secret' => 'sec_meta', 'api_version' => 'v23.0']);
        $vault->storeCredentials($metaAcc, ['access_token' => 'old_meta', 'refresh_token' => 'rt_meta', 'expires_in' => 30]);
        $this->assertSame('meta_rotated_token', $vault->getValidAccessToken($metaAcc));

        $googleAcc = $this->makeAccount('google_ads', ['client_id' => 'gid.apps.googleusercontent.com', 'client_secret' => 'sec_google']);
        $vault->storeCredentials($googleAcc, ['access_token' => 'old_google', 'refresh_token' => 'rt_google', 'expires_in' => 30]);
        $this->assertSame('google_rotated_token', $vault->getValidAccessToken($googleAcc));

        $tiktokAcc = $this->makeAccount('tiktok', ['app_id' => 'tt_id', 'app_secret' => 'sec_tt']);
        $vault->storeCredentials($tiktokAcc, ['access_token' => 'old_tt', 'refresh_token' => 'rt_tt', 'expires_in' => 30]);
        $this->assertSame('tiktok_rotated_token', $vault->getValidAccessToken($tiktokAcc));

        $snapAcc = $this->makeAccount('snapchat', ['client_id' => 'snap_id', 'client_secret' => 'sec_snap']);
        $vault->storeCredentials($snapAcc, ['access_token' => 'old_snap', 'refresh_token' => 'rt_snap', 'expires_in' => 30]);
        $this->assertSame('snap_rotated_token', $vault->getValidAccessToken($snapAcc));
    }

    public function test_failed_refresh_trips_circuit_breaker_and_emits_ux121_bilingual_critical_alert(): void
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
        $alert = Alert::where('ad_account_id', $account->id)->where('rule_key', 'oauth_reauth_required')->where('status', 'open')->firstOrFail();

        // UX-121: Verify AlertText translates stored English title & detail into Arabic with Unicode isolates
        $this->assertStringContainsString('مشكلة في الربط:', $alert->localTitle('ar'));
        $this->assertStringContainsString('يحتاج الربط إلى مراجعة:', (string) $alert->localDetail('ar'));

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

    public function test_sync_service_short_circuits_when_circuit_breaker_is_open(): void
    {
        $account = $this->makeAccount('meta');
        $vault = app(OAuthTokenVaultService::class);
        $vault->tripCircuitBreaker($account, 'invalid_grant', 'Token revoked by user');

        $run = \App\Sync\SyncService::sync($account, '2026-11-01', '2026-11-07');
        $this->assertSame('error', $run->status);
        $this->assertStringContainsString('Sync paused: connection requires re-authentication.', (string) $run->error);
        $this->assertSame('error', $account->fresh()->sync_status);
    }
}
