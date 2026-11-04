<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** X Ads API v12 with OAuth 1.0a (user token in access_token, token secret in refresh_token). */
class XConnector extends Connector
{
    protected const ADS = 'https://ads-api.x.com/12/';

    protected const OBJECTIVES = [
        'WEBSITE_CLICKS' => 'link_click', 'ENGAGEMENTS' => 'engagement', 'VIDEO_VIEWS' => 'thruplay', 'PREROLL_VIEWS' => 'thruplay',
        'WEBSITE_CONVERSIONS' => 'conversion', 'REACH' => 'impressions', 'AWARENESS' => 'impressions', 'FOLLOWERS' => 'engagement',
        'APP_INSTALLS' => 'app_install', 'APP_ENGAGEMENTS' => 'engagement',
    ];

    public function supportsConnect(): bool
    {
        return (bool) ($this->cred('api_key') && $this->cred('api_secret'));
    }

    public function supportsSync(): bool
    {
        return true;
    }

    /** OAuth 1.0a HMAC-SHA1 Authorization header. */
    protected function oauthHeader(string $method, string $url, array $params, ?string $token = null, ?string $secret = null, array $extra = []): string
    {
        $oauth = $extra + ['oauth_consumer_key' => $this->cred('api_key'), 'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1', 'oauth_timestamp' => (string) time(), 'oauth_version' => '1.0'];
        if ($token) {
            $oauth['oauth_token'] = $token;
        }
        $all = $oauth + $params;
        ksort($all);
        $pairs = [];
        foreach ($all as $k => $v) {
            $pairs[] = rawurlencode((string) $k).'='.rawurlencode((string) $v);
        }
        $base = strtoupper($method).'&'.rawurlencode($url).'&'.rawurlencode(implode('&', $pairs));
        $oauth['oauth_signature'] = base64_encode(hash_hmac('sha1', $base, rawurlencode((string) $this->cred('api_secret')).'&'.rawurlencode((string) $secret), true));
        ksort($oauth);

        return 'OAuth '.implode(', ', array_map(fn ($k, $v) => rawurlencode($k).'="'.rawurlencode((string) $v).'"', array_keys($oauth), $oauth));
    }

    /** Step 1: get a request token, keep its secret in the session, send the user to X. */
    public function authorizeUrl(string $state): string
    {
        $url = 'https://api.x.com/oauth/request_token';
        $callback = $this->redirectUri().'?state='.$state;
        $res = Http::withHeaders(['Authorization' => $this->oauthHeader('POST', $url, [], null, null, ['oauth_callback' => $callback])])->timeout(20)->post($url);
        parse_str($res->body(), $out);
        if (empty($out['oauth_token'])) {
            throw new RuntimeException('X did not return a request token: '.mb_substr($res->body(), 0, 160));
        }
        session()->put('x_oauth_secret', $out['oauth_token_secret'] ?? '');

        return 'https://api.x.com/oauth/authorize?oauth_token='.urlencode($out['oauth_token']);
    }

    /** Step 2: $code is the oauth_verifier; the request token comes back as ?oauth_token=. */
    public function exchange(string $code): array
    {
        $url = 'https://api.x.com/oauth/access_token';
        $reqToken = (string) request()->query('oauth_token');
        $res = Http::withHeaders(['Authorization' => $this->oauthHeader('POST', $url, ['oauth_verifier' => $code], $reqToken, (string) session()->pull('x_oauth_secret'))])
            ->asForm()->timeout(20)->post($url, ['oauth_verifier' => $code]);
        parse_str($res->body(), $out);
        if (empty($out['oauth_token'])) {
            throw new RuntimeException('X did not return an access token: '.mb_substr($res->body(), 0, 160));
        }

        return ['access_token' => $out['oauth_token'], 'refresh_token' => $out['oauth_token_secret'] ?? null, 'expires_in' => null,
            'user_id' => $out['user_id'] ?? null, 'user_name' => isset($out['screen_name']) ? '@'.$out['screen_name'] : null, 'scopes' => null];
    }

    protected function get(Connection $c, string $path, array $q = []): array
    {
        $url = self::ADS.$path;
        $res = Http::withHeaders(['Authorization' => $this->oauthHeader('GET', $url, $q, $c->access_token, $c->refresh_token)])
            ->timeout(60)->retry(2, 3000, throw: false)->get($url, $q);
        if ($res->failed()) {
            throw new RuntimeException('X Ads: '.($res->json('errors.0.message') ?? 'HTTP '.$res->status()));
        }

        return (array) $res->json();
    }

    protected function all(Connection $c, string $path, array $q = []): array
    {
        $out = [];
        $cursor = null;
        for ($i = 0; $i < 50; $i++) {
            $d = $this->get($c, $path, $q + ['count' => 1000] + ($cursor ? ['cursor' => $cursor] : []));
            array_push($out, ...($d['data'] ?? []));
            $cursor = $d['next_cursor'] ?? null;
            if (! $cursor) {
                break;
            }
        }

        return $out;
    }

    public function accounts(Connection $connection): array
    {
        $out = [];
        foreach ($this->all($connection, 'accounts') as $a) {
            $fi = $this->get($connection, "accounts/{$a['id']}/funding_instruments")['data'][0] ?? [];
            $out[] = ['id' => $a['id'], 'name' => $a['name'] ?? null, 'currency' => $fi['currency'] ?? null, 'timezone' => $a['timezone'] ?? null,
                'status' => $a['approval_status'] ?? null];
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The X connection is missing. Reconnect it.');
        $id = $account->external_id;
        $micro = fn ($v) => is_numeric($v) ? $v / 1_000_000 : null;
        $campaigns = array_map(fn ($x) => ['external_id' => $x['id'], 'name' => $x['name'] ?? null, 'status' => $x['entity_status'] ?? null,
            'daily_budget' => $micro($x['daily_budget_amount_local_micro'] ?? null), 'start_time' => $x['start_time'] ?? null, 'end_time' => $x['end_time'] ?? null],
            $this->all($c, "accounts/$id/campaigns"));
        $types = [];
        $sets = array_map(function ($x) use (&$types) {
            $types[$x['id']] = [self::OBJECTIVES[strtoupper((string) ($x['objective'] ?? ''))] ?? 'link_click', $x['campaign_id'] ?? null];

            return ['external_id' => $x['id'], 'name' => $x['name'] ?? null, 'campaign' => $x['campaign_id'] ?? null, 'status' => $x['entity_status'] ?? null,
                'optimization_goal' => $x['objective'] ?? null, 'start_time' => $x['start_time'] ?? null, 'end_time' => $x['end_time'] ?? null];
        }, $this->all($c, "accounts/$id/line_items"));

        $tz = $account->timezone ?: 'UTC';
        $rows = [];
        $sum = fn ($v) => is_array($v) ? array_sum(array_map('floatval', $v)) : (float) $v;
        foreach (array_chunk(array_keys($types), 20) as $ids) {
            foreach (static::windows($from, $to, 7) as [$f, $t]) {
                $d = $this->get($c, "stats/accounts/$id", ['entity' => 'LINE_ITEM', 'entity_ids' => implode(',', $ids), 'granularity' => 'DAY',
                    'placement' => 'ALL_ON_TWITTER', 'metric_groups' => 'ENGAGEMENT,BILLING,VIDEO,WEB_CONVERSION',
                    'start_time' => Carbon::parse($f, $tz)->startOfDay()->toIso8601String(), 'end_time' => Carbon::parse($t, $tz)->addDay()->startOfDay()->toIso8601String()]);
                foreach ($d['data'] ?? [] as $e) {
                    [$type, $camp] = $types[$e['id']] ?? ['link_click', null];
                    $m = $e['id_data'][0]['metrics'] ?? [];
                    $days = count((array) ($m['impressions'] ?? []));
                    for ($i = 0; $i < $days; $i++) {
                        $at = fn ($k) => (float) (($m[$k] ?? [])[$i] ?? 0);
                        $conv = fn ($k) => (float) (($m[$k]['metric'] ?? [])[$i] ?? 0);
                        $row = ['spend' => $at('billed_charge_local_micro') / 1_000_000, 'impressions' => (int) $at('impressions'), 'clicks' => (int) $at('clicks'),
                            'link_clicks' => (int) $at('url_clicks'), 'thruplays' => $at('video_views_100'), 'purchases' => $conv('conversion_purchases'),
                            'leads' => $conv('conversion_sign_ups')];
                        if ($row['impressions'] === 0 && $row['spend'] == 0) {
                            continue;
                        }
                        $res = match ($type) { 'engagement' => $at('engagements'), 'app_install' => 0.0, default => null };
                        $rows[] = ['date' => Carbon::parse($f)->addDays($i)->toDateString(), 'level' => 'ad_set', 'campaign' => $camp, 'ad_set' => $e['id'], 'ad' => null]
                            + static::metrics($row, $type, $res);
                    }
                }
            }
        }
        unset($sum);

        return ['campaigns' => $campaigns, 'ad_sets' => $sets, 'ads' => [], 'rows' => $rows];
    }

    public function test(): array
    {
        if (! $this->supportsConnect()) {
            return [false, 'API Key or API Key Secret is missing.'];
        }
        $res = Http::asForm()->withBasicAuth((string) $this->cred('api_key'), (string) $this->cred('api_secret'))->timeout(15)
            ->post('https://api.x.com/oauth2/token', ['grant_type' => 'client_credentials']);

        return $res->json('access_token')
            ? [true, 'X accepted the API Key and Secret.']
            : [false, 'X rejected the keys: '.mb_substr((string) ($res->json('errors.0.message') ?? $res->body()), 0, 160)];
    }
}
