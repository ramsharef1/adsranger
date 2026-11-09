<?php

namespace App\Services\AdNetworks;

use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StageOneOAuthAdapter
{
    /**
     * Refresh an OAuth token for Stage-1 platforms (meta, google_ads, ga4, google_merchant, tiktok, snapchat)
     * using the encrypted credentials stored on AdNetwork.
     *
     * @return array{access_token: string, refresh_token?: string, expires_in: int}
     */
    public function refresh(string $refreshToken, AdAccount $account, Connection $connection): array
    {
        /** @var ?AdNetwork $network */
        $network = $account->network()->first() ?? $connection->network()->first();
        if (! $network) {
            throw new RuntimeException("AdAccount [{$account->id}] has no associated AdNetwork.");
        }

        $key = (string) $network->key;
        $creds = $network->credentials($connection->environment ?: null);

        return match ($key) {
            'meta' => $this->refreshMeta($refreshToken, $creds),
            'google_ads', 'ga4', 'google_merchant' => $this->refreshGoogle($refreshToken, $creds, $key),
            'tiktok' => $this->refreshTikTok($refreshToken, $creds),
            'snapchat' => $this->refreshSnapchat($refreshToken, $creds),
            default => throw new RuntimeException("Unsupported Stage-1 OAuth refresh network [{$key}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $creds
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function refreshMeta(string $exchangeToken, array $creds): array
    {
        $appId = (string) ($creds['app_id'] ?? '');
        $appSecret = (string) ($creds['app_secret'] ?? '');
        $version = (string) ($creds['api_version'] ?? 'v23.0');

        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('Missing Meta OAuth app_id or app_secret.');
        }

        $response = Http::timeout(15)->get("https://graph.facebook.com/{$version}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $exchangeToken,
        ]);

        $body = $response->json() ?? [];
        if (! $response->successful() || empty($body['access_token'])) {
            $errCode = $body['error']['code'] ?? $response->status();
            $errMsg = $body['error']['message'] ?? 'Meta token exchange failed';
            throw new RuntimeException("{$errCode} OAuthException: {$errMsg}");
        }

        return [
            'access_token' => (string) $body['access_token'],
            'refresh_token' => (string) $body['access_token'],
            'expires_in' => (int) ($body['expires_in'] ?? 5184000),
        ];
    }

    /**
     * @param  array<string, mixed>  $creds
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function refreshGoogle(string $refreshToken, array $creds, string $networkKey): array
    {
        $clientId = (string) ($creds['client_id'] ?? '');
        $clientSecret = (string) ($creds['client_secret'] ?? '');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException("Missing Google OAuth client_id or client_secret for [{$networkKey}].");
        }

        $response = Http::timeout(15)->asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        $body = $response->json() ?? [];
        if (! $response->successful() || empty($body['access_token'])) {
            $err = $body['error'] ?? 'invalid_grant';
            $desc = $body['error_description'] ?? 'Google OAuth refresh rejected';
            throw new RuntimeException("{$err}: {$desc}");
        }

        return [
            'access_token' => (string) $body['access_token'],
            'refresh_token' => (string) ($body['refresh_token'] ?? $refreshToken),
            'expires_in' => (int) ($body['expires_in'] ?? 3600),
        ];
    }

    /**
     * @param  array<string, mixed>  $creds
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function refreshTikTok(string $refreshToken, array $creds): array
    {
        $appId = (string) ($creds['app_id'] ?? '');
        $appSecret = (string) ($creds['app_secret'] ?? '');

        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('Missing TikTok OAuth app_id or app_secret.');
        }

        $response = Http::timeout(15)->post('https://business-api.tiktok.com/open_api/v1.3/oauth2/refresh_token/', [
            'app_id' => $appId,
            'secret' => $appSecret,
            'refresh_token' => $refreshToken,
        ]);

        $body = $response->json() ?? [];
        $data = $body['data'] ?? [];
        if (! $response->successful() || (int) ($body['code'] ?? -1) !== 0 || empty($data['access_token'])) {
            $code = $body['code'] ?? $response->status();
            $msg = $body['message'] ?? 'TikTok OAuth refresh failed';
            throw new RuntimeException("tiktok_{$code}: {$msg}");
        }

        return [
            'access_token' => (string) $data['access_token'],
            'refresh_token' => (string) ($data['refresh_token'] ?? $refreshToken),
            'expires_in' => (int) ($data['expires_in'] ?? 86400),
        ];
    }

    /**
     * @param  array<string, mixed>  $creds
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    private function refreshSnapchat(string $refreshToken, array $creds): array
    {
        $clientId = (string) ($creds['client_id'] ?? '');
        $clientSecret = (string) ($creds['client_secret'] ?? '');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Missing Snapchat OAuth client_id or client_secret.');
        }

        $response = Http::timeout(15)->asForm()->post('https://accounts.snapchat.com/login/oauth2/access_token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        $body = $response->json() ?? [];
        if (! $response->successful() || empty($body['access_token'])) {
            $err = $body['error'] ?? 'invalid_grant';
            $desc = $body['error_description'] ?? 'Snapchat OAuth refresh rejected';
            throw new RuntimeException("{$err}: {$desc}");
        }

        return [
            'access_token' => (string) $body['access_token'],
            'refresh_token' => (string) ($body['refresh_token'] ?? $refreshToken),
            'expires_in' => (int) ($body['expires_in'] ?? 3600),
        ];
    }
}
