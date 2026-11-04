<?php

namespace App\Connectors;

use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAdsConnector extends Connector
{
    protected string $scope = 'https://www.googleapis.com/auth/adwords';

    public function supportsConnect(): bool
    {
        return (bool) ($this->cred('client_id') && $this->cred('client_secret'));
    }

    public function authorizeUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $this->cred('client_id'), 'redirect_uri' => $this->redirectUri(), 'response_type' => 'code',
            'scope' => 'openid email '.$this->scope, 'access_type' => 'offline', 'prompt' => 'consent',
            'include_granted_scopes' => 'true', 'state' => $state,
        ]);
    }

    public function exchange(string $code): array
    {
        $res = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri(),
            'client_id' => $this->cred('client_id'), 'client_secret' => $this->cred('client_secret'),
        ]);
        $token = $res->json('access_token') ?: throw new RuntimeException('Google did not return a token: '.$res->json('error_description'));
        $me = Http::withToken($token)->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo');

        return [
            'access_token' => $token, 'refresh_token' => $res->json('refresh_token'), 'expires_in' => $res->json('expires_in'),
            'user_id' => $me->json('sub'), 'user_name' => $me->json('email'), 'scopes' => $res->json('scope'),
        ];
    }

    /** Fresh access token (Google tokens last about an hour). */
    public function freshToken(Connection $c): string
    {
        if ($c->access_token && $c->token_expires_at && $c->token_expires_at->isFuture()) {
            return $c->access_token;
        }
        $res = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token', 'refresh_token' => $c->refresh_token,
            'client_id' => $this->cred('client_id'), 'client_secret' => $this->cred('client_secret'),
        ]);
        $token = $res->json('access_token') ?: throw new RuntimeException('Google refresh failed: '.$res->json('error_description'));
        $c->update(['access_token' => $token, 'token_expires_at' => now()->addSeconds((int) $res->json('expires_in', 3500) - 60)]);

        return $token;
    }

    public function accounts(Connection $connection): array
    {
        $token = $this->freshToken($connection);
        $v = $this->cred('api_version', 'v22');
        $headers = array_filter(['login-customer-id' => preg_replace('/\D/', '', (string) $this->cred('login_customer_id')) ?: null]);
        $res = Http::withToken($token)->withHeaders($headers)->timeout(30)->get("https://googleads.googleapis.com/{$v}/customers:listAccessibleCustomers");
        if ($res->failed()) {
            throw new RuntimeException('Google Ads: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
        }
        $out = [];
        foreach ($res->json('resourceNames', []) as $rn) {
            $id = str_replace('customers/', '', $rn);
            $row = ['id' => $id, 'name' => 'Customer '.preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1-$2-$3', $id), 'currency' => null, 'timezone' => null, 'status' => null];
            $q = Http::withToken($token)->withHeaders($headers)->timeout(20)->post("https://googleads.googleapis.com/{$v}/customers/{$id}/googleAds:search", [
                'query' => 'SELECT customer.descriptive_name, customer.currency_code, customer.time_zone, customer.status FROM customer LIMIT 1',
            ]);
            if ($q->successful() && ($c = $q->json('results.0.customer'))) {
                $row = ['id' => $id, 'name' => $c['descriptiveName'] ?? $row['name'], 'currency' => $c['currencyCode'] ?? null,
                    'timezone' => $c['timeZone'] ?? null, 'status' => $c['status'] ?? null];
            }
            $out[] = $row;
        }

        return $out;
    }

    public function test(): array
    {
        return $this->oauthClientTest('https://oauth2.googleapis.com/token');
    }
}
