<?php

namespace App\Connectors;

use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaConnector extends Connector
{
    protected function v(): string
    {
        return $this->cred('api_version', 'v23.0');
    }

    protected function graph(string $path): string
    {
        return 'https://graph.facebook.com/'.$this->v().'/'.ltrim($path, '/');
    }

    public function supportsConnect(): bool
    {
        return (bool) ($this->cred('app_id') && $this->cred('app_secret'));
    }

    public function authorizeUrl(string $state): string
    {
        $q = ['client_id' => $this->cred('app_id'), 'redirect_uri' => $this->redirectUri(), 'state' => $state, 'response_type' => 'code'];
        $this->cred('config_id') ? $q['config_id'] = $this->cred('config_id') : $q['scope'] = 'ads_read,ads_management,business_management';

        return 'https://www.facebook.com/'.$this->v().'/dialog/oauth?'.http_build_query($q);
    }

    public function exchange(string $code): array
    {
        $short = Http::timeout(20)->get($this->graph('oauth/access_token'), [
            'client_id' => $this->cred('app_id'), 'client_secret' => $this->cred('app_secret'),
            'redirect_uri' => $this->redirectUri(), 'code' => $code,
        ]);
        $token = $short->json('access_token') ?: throw new RuntimeException('Meta did not return a token: '.$short->json('error.message'));
        $long = Http::timeout(20)->get($this->graph('oauth/access_token'), [
            'grant_type' => 'fb_exchange_token', 'client_id' => $this->cred('app_id'),
            'client_secret' => $this->cred('app_secret'), 'fb_exchange_token' => $token,
        ]);
        $token = $long->json('access_token') ?: $token;
        $me = Http::timeout(20)->get($this->graph('me'), ['fields' => 'id,name', 'access_token' => $token]);

        return [
            'access_token' => $token, 'refresh_token' => null,
            'expires_in' => $long->json('expires_in') ?? $short->json('expires_in'),
            'user_id' => $me->json('id'), 'user_name' => $me->json('name'), 'scopes' => null,
        ];
    }

    public function accounts(Connection $connection): array
    {
        $out = [];
        $url = $this->graph('me/adaccounts');
        $params = ['fields' => 'name,account_id,currency,timezone_name,account_status', 'limit' => 200, 'access_token' => $connection->access_token];
        for ($page = 0; $url && $page < 20; $page++) {
            $res = Http::timeout(30)->get($url, $params);
            if ($res->failed()) {
                throw new RuntimeException('Meta: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
            }
            foreach ($res->json('data', []) as $a) {
                $out[] = ['id' => 'act_'.$a['account_id'], 'name' => $a['name'] ?? null, 'currency' => $a['currency'] ?? null,
                    'timezone' => $a['timezone_name'] ?? null, 'status' => (string) ($a['account_status'] ?? '')];
            }
            $url = $res->json('paging.next');
            $params = [];
        }

        return $out;
    }

    public function test(): array
    {
        if (! $this->cred('app_id') || ! $this->cred('app_secret')) {
            return [false, 'App ID or App Secret is missing.'];
        }
        $res = Http::timeout(15)->get($this->graph('oauth/access_token'), [
            'client_id' => $this->cred('app_id'), 'client_secret' => $this->cred('app_secret'), 'grant_type' => 'client_credentials',
        ]);

        return $res->json('access_token')
            ? [true, 'Meta accepted the App ID and Secret (Graph '.$this->v().').']
            : [false, 'Meta rejected the keys: '.mb_substr((string) $res->json('error.message'), 0, 160)];
    }
}
