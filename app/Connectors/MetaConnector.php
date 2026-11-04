<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use App\Support\ResultResolver;
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

    public function supportsSync(): bool
    {
        return true;
    }

    /** GET every page of a Graph edge. */
    protected function all(string $path, array $params, string $token, int $maxPages = 50): array
    {
        $out = [];
        $url = $this->graph($path);
        $params['access_token'] = $token;
        for ($i = 0; $url && $i < $maxPages; $i++) {
            $res = Http::timeout(60)->retry(2, 2000, throw: false)->get($url, $params);
            if ($res->failed()) {
                throw new RuntimeException('Meta: '.($res->json('error.message') ?? 'HTTP '.$res->status()));
            }
            array_push($out, ...$res->json('data', []));
            $url = $res->json('paging.next');
            $params = [];
        }

        return $out;
    }

    protected static function money($minor): ?float
    {
        return is_numeric($minor) ? ((float) $minor) / 100 : null;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $token = $account->connection?->access_token ?: throw new RuntimeException('The Meta connection has no token. Reconnect it.');
        $act = $account->external_id;

        $campaigns = [];
        foreach ($this->all("$act/campaigns", ['fields' => 'id,name,effective_status,objective,daily_budget,lifetime_budget,start_time,stop_time', 'limit' => 200], $token) as $c) {
            $campaigns[$c['id']] = ['external_id' => $c['id'], 'name' => $c['name'] ?? null, 'status' => $c['effective_status'] ?? null,
                'objective' => $c['objective'] ?? null, 'daily_budget' => static::money($c['daily_budget'] ?? null),
                'lifetime_budget' => static::money($c['lifetime_budget'] ?? null), 'start_time' => $c['start_time'] ?? null, 'end_time' => $c['stop_time'] ?? null];
        }
        $adSets = [];
        foreach ($this->all("$act/adsets", ['fields' => 'id,name,campaign_id,effective_status,optimization_goal,promoted_object,daily_budget,lifetime_budget,start_time,end_time', 'limit' => 200], $token) as $s) {
            $adSets[$s['id']] = ['external_id' => $s['id'], 'name' => $s['name'] ?? null, 'campaign' => $s['campaign_id'] ?? null,
                'status' => $s['effective_status'] ?? null, 'optimization_goal' => $s['optimization_goal'] ?? null,
                'custom_event' => $s['promoted_object']['custom_event_type'] ?? null,
                'daily_budget' => static::money($s['daily_budget'] ?? null), 'lifetime_budget' => static::money($s['lifetime_budget'] ?? null),
                'start_time' => $s['start_time'] ?? null, 'end_time' => $s['end_time'] ?? null];
        }
        $ads = [];
        foreach ($this->all("$act/ads", ['fields' => 'id,name,adset_id,campaign_id,effective_status,creative{thumbnail_url,image_url},ad_review_feedback', 'limit' => 200], $token) as $a) {
            $fb = $a['ad_review_feedback']['global'] ?? null;
            $ads[$a['id']] = ['external_id' => $a['id'], 'name' => $a['name'] ?? null, 'campaign' => $a['campaign_id'] ?? null, 'ad_set' => $a['adset_id'] ?? null,
                'status' => $a['effective_status'] ?? null, 'thumbnail_url' => $a['creative']['thumbnail_url'] ?? ($a['creative']['image_url'] ?? null),
                'review_feedback' => $fb ? json_encode($fb) : null];
        }

        $rows = [];
        $insights = $this->all("$act/insights", [
            'level' => 'ad', 'time_increment' => 1, 'limit' => 500,
            'time_range' => json_encode(['since' => $from, 'until' => $to]),
            'fields' => 'date_start,ad_id,adset_id,campaign_id,spend,impressions,reach,clicks,inline_link_clicks,actions,action_values,video_thruplay_watched_actions',
        ], $token, 200);
        foreach ($insights as $r) {
            $set = $adSets[$r['adset_id'] ?? ''] ?? [];
            $camp = $campaigns[$r['campaign_id'] ?? ''] ?? [];
            $type = ResultResolver::metaType($set['optimization_goal'] ?? null, $camp['objective'] ?? null, $set['custom_event'] ?? null);
            $rows[] = ['date' => $r['date_start'], 'level' => 'ad', 'campaign' => $r['campaign_id'] ?? null, 'ad_set' => $r['adset_id'] ?? null,
                'ad' => $r['ad_id'] ?? null] + ResultResolver::meta($r, $type);
        }

        return ['campaigns' => array_values($campaigns), 'ad_sets' => array_values($adSets), 'ads' => array_values($ads), 'rows' => $rows];
    }
}
