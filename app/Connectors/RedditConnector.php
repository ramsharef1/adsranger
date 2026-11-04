<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Reddit Ads API v3 (OAuth2 with a permanent refresh token, scope adsread). Daily data per ad. Reddit requires a descriptive User-Agent. */
class RedditConnector extends Connector
{
    protected const API = 'https://ads-api.reddit.com/api/v3/';

    protected const TOKEN = 'https://www.reddit.com/api/v1/access_token';

    protected const GOALS = ['CONVERSIONS' => 'purchase', 'LEAD_GENERATION' => 'lead', 'LEADS' => 'lead', 'TRAFFIC' => 'link_click', 'CLICKS' => 'link_click',
        'IMPRESSIONS' => 'impressions', 'BRAND_AWARENESS_AND_REACH' => 'reach', 'VIDEO_VIEWABLE_IMPRESSIONS' => 'thruplay', 'VIDEO_VIEWS' => 'thruplay',
        'APP_INSTALLS' => 'app_install', 'CATALOG_SALES' => 'purchase', 'SALES' => 'purchase', 'ENGAGEMENT' => 'engagement'];

    protected function ua(): string
    {
        return 'web:adsranger:1.0 (by /u/'.($this->cred('reddit_username') ?: 'adsranger').')';
    }

    public function supportsConnect(): bool
    {
        return (bool) ($this->cred('client_id') && $this->cred('client_secret'));
    }

    public function supportsSync(): bool
    {
        return true;
    }

    public function authorizeUrl(string $state): string
    {
        return 'https://www.reddit.com/api/v1/authorize?'.http_build_query(['client_id' => $this->cred('client_id'), 'response_type' => 'code',
            'state' => $state, 'redirect_uri' => $this->redirectUri(), 'duration' => 'permanent', 'scope' => 'adsread']);
    }

    protected function tokenCall(array $body)
    {
        return Http::asForm()->withBasicAuth((string) $this->cred('client_id'), (string) $this->cred('client_secret'))
            ->withHeaders(['User-Agent' => $this->ua()])->timeout(20)->post(self::TOKEN, $body);
    }

    public function exchange(string $code): array
    {
        $res = $this->tokenCall(['grant_type' => 'authorization_code', 'code' => preg_replace('/#_$/', '', $code), 'redirect_uri' => $this->redirectUri()]);
        $tok = $res->json('access_token') ?: throw new RuntimeException('Reddit did not return a token: '.($res->json('error_description') ?? $res->json('error') ?? 'HTTP '.$res->status()));
        $me = Http::withToken($tok)->withHeaders(['User-Agent' => $this->ua()])->acceptJson()->timeout(15)->get(self::API.'me');

        return ['access_token' => $tok, 'refresh_token' => $res->json('refresh_token'), 'expires_in' => $res->json('expires_in'),
            'user_id' => $me->json('data.id'), 'user_name' => $me->json('data.reddit_username') ?? $me->json('data.email'), 'scopes' => $res->json('scope')];
    }

    protected function refresh(string $refreshToken): array
    {
        $res = $this->tokenCall(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
        $tok = $res->json('access_token') ?: throw new RuntimeException('Reddit refresh failed: '.($res->json('error') ?? 'HTTP '.$res->status()).'. Reconnect the account.');

        return ['access_token' => $tok, 'refresh_token' => $res->json('refresh_token') ?: $refreshToken, 'expires_in' => $res->json('expires_in')];
    }

    protected function req(Connection $c)
    {
        return Http::withToken($this->token($c))->withHeaders(['User-Agent' => $this->ua()])->acceptJson()->timeout(90)
            ->retry(3, fn (int $n) => 5000 * $n, fn ($e) => in_array(($e->response ?? null)?->status(), [429, 500, 502, 503], true), throw: false);
    }

    /** Follow pagination.next_url as given. */
    protected function list(Connection $c, string $path, array $q = []): array
    {
        $url = self::API.$path.($q ? '?'.http_build_query($q) : '');
        $out = [];
        for ($i = 0; $url && $i < 100; $i++) {
            $res = $this->req($c)->get($url);
            if ($res->failed()) {
                throw new RuntimeException('Reddit: '.($res->json('error.message') ?? $res->json('message') ?? 'HTTP '.$res->status()));
            }
            $out = array_merge($out, (array) $res->json('data'));
            $url = $res->json('pagination.next_url');
        }

        return $out;
    }

    public function accounts(Connection $connection): array
    {
        $out = [];
        foreach ($this->list($connection, 'me/businesses', ['page.size' => 100]) as $b) {
            foreach ($this->list($connection, 'businesses/'.rawurlencode((string) $b['id']).'/ad_accounts', ['page.size' => 100]) as $a) {
                $out[(string) $a['id']] = ['id' => (string) $a['id'], 'name' => $a['name'] ?? null, 'currency' => $a['currency'] ?? null,
                    'timezone' => $a['time_zone_id'] ?? null, 'status' => ! empty($a['suspension_reason']) ? 'SUSPENDED' : null,
                    'meta' => ['business_id' => (string) $b['id'], 'business' => $b['name'] ?? null]];
            }
        }

        return array_values($out);
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Reddit connection is missing. Reconnect it.');
        $id = rawurlencode((string) $account->external_id);
        $micro = fn ($v) => is_numeric($v) ? $v / 1_000_000 : null;

        $campaigns = array_map(fn ($x) => ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'status' => $x['effective_status'] ?? ($x['configured_status'] ?? null),
            'objective' => $x['objective'] ?? null, 'lifetime_budget' => $micro($x['spend_cap'] ?? null),
            'daily_budget' => ($x['goal_type'] ?? '') === 'DAILY_SPEND' ? $micro($x['goal_value'] ?? null) : null,
            'start_time' => $x['start_time'] ?? null, 'end_time' => $x['end_time'] ?? null], $this->list($c, "ad_accounts/$id/campaigns", ['page.size' => 1000]));
        $obj = array_column($campaigns, 'objective', 'external_id');
        $sets = array_map(fn ($x) => ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'campaign' => (string) ($x['campaign_id'] ?? ''),
            'status' => $x['effective_status'] ?? ($x['configured_status'] ?? null), 'optimization_goal' => $x['optimization_goal'] ?? ($x['bid_type'] ?? null),
            'daily_budget' => ($x['goal_type'] ?? '') === 'DAILY_SPEND' ? $micro($x['goal_value'] ?? null) : null,
            'start_time' => $x['start_time'] ?? null, 'end_time' => $x['end_time'] ?? null], $this->list($c, "ad_accounts/$id/ad_groups", ['page.size' => 1000]));
        $setCamp = array_column($sets, 'campaign', 'external_id');
        $adSet = [];
        $ads = array_map(function ($x) use (&$adSet) {
            $adSet[(string) $x['id']] = (string) ($x['ad_group_id'] ?? '');

            return ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'ad_set' => (string) ($x['ad_group_id'] ?? ''), 'campaign' => (string) ($x['campaign_id'] ?? ''),
                'status' => $x['effective_status'] ?? ($x['configured_status'] ?? null), 'review_feedback' => ! empty($x['rejection_reason']) ? (string) $x['rejection_reason'] : null];
        }, $this->list($c, "ad_accounts/$id/ads", ['page.size' => 1000]));

        $tz = $account->timezone ?: 'UTC';
        $fields = ['SPEND', 'IMPRESSIONS', 'CLICKS', 'REACH', 'KEY_CONVERSION_TOTAL_COUNT', 'CONVERSION_PURCHASE_CLICKS', 'CONVERSION_PURCHASE_VIEWS',
            'CONVERSION_PURCHASE_TOTAL_VALUE', 'CONVERSION_LEAD_CLICKS', 'CONVERSION_SIGN_UP_CLICKS', 'REDDIT_LEADS', 'CONVERSION_ADD_TO_CART_CLICKS',
            'CONVERSION_PAGE_VISIT_CLICKS', 'VIDEO_WATCHED_100_PERCENT'];
        $rows = [];
        foreach (static::windows($from, $to, 31) as [$f, $t]) {
            $url = self::API."ad_accounts/$id/reports?".http_build_query(['page.size' => 1000]);
            $body = ['data' => ['breakdowns' => ['DATE', 'AD_ID'], 'fields' => $fields, 'time_zone_id' => $tz,
                'starts_at' => Carbon::parse($f)->format('Y-m-d').'T00:00:00Z', 'ends_at' => Carbon::parse($t)->addDay()->format('Y-m-d').'T00:00:00Z']];
            for ($i = 0; $url && $i < 200; $i++) {
                $res = $this->req($c)->post($url, $body);
                if ($res->failed()) {
                    throw new RuntimeException('Reddit report: '.($res->json('error.message') ?? $res->json('message') ?? 'HTTP '.$res->status()));
                }
                foreach ((array) $res->json('data.metrics') as $r) {
                    $spend = (float) ($r['spend'] ?? 0) / 1_000_000;
                    if ($spend == 0 && empty($r['impressions'])) {
                        continue;
                    }
                    $ad = (string) ($r['ad_id'] ?? '');
                    $set = $adSet[$ad] ?? null;
                    $camp = $setCamp[$set ?? ''] ?? null;
                    $type = self::GOALS[strtoupper((string) ($obj[$camp ?? ''] ?? ''))] ?? 'link_click';
                    $m = ['spend' => $spend, 'impressions' => (int) ($r['impressions'] ?? 0), 'clicks' => (int) ($r['clicks'] ?? 0), 'link_clicks' => (int) ($r['clicks'] ?? 0),
                        'reach' => (int) ($r['reach'] ?? 0), 'purchases' => (float) ($r['conversion_purchase_clicks'] ?? 0) + (float) ($r['conversion_purchase_views'] ?? 0),
                        'revenue' => (float) ($r['conversion_purchase_total_value'] ?? 0) / 100,
                        'leads' => (float) ($r['conversion_lead_clicks'] ?? 0) + (float) ($r['conversion_sign_up_clicks'] ?? 0) + (float) ($r['reddit_leads'] ?? 0),
                        'add_to_cart' => (float) ($r['conversion_add_to_cart_clicks'] ?? 0), 'landing_views' => (float) ($r['conversion_page_visit_clicks'] ?? 0),
                        'thruplays' => (float) ($r['video_watched_100_percent'] ?? 0)];
                    $res2 = $type === 'purchase' && $m['purchases'] == 0 && ! empty($r['key_conversion_total_count']) ? (float) $r['key_conversion_total_count'] : null;
                    $rows[] = ['date' => substr((string) ($r['date'] ?? $f), 0, 10), 'level' => 'ad', 'campaign' => $camp, 'ad_set' => $set, 'ad' => $ad] + static::metrics($m, $type, $res2);
                }
                $url = $res->json('pagination.next_url');
            }
        }

        return ['campaigns' => $campaigns, 'ad_sets' => $sets, 'ads' => $ads, 'rows' => $rows];
    }

    public function test(): array
    {
        if (! $this->cred('client_id') || ! $this->cred('client_secret')) {
            return [false, 'App ID or secret is missing.'];
        }
        $res = $this->tokenCall(['grant_type' => 'authorization_code', 'code' => 'adsranger-key-test', 'redirect_uri' => $this->redirectUri()]);
        if ($res->status() === 401) {
            return [false, 'Reddit rejected the app ID or secret.'];
        }
        $err = (string) ($res->json('error') ?? '');

        return $err === 'invalid_grant' ? [true, 'Keys accepted (the test code was rejected, as expected).'] : [null, 'Unclear answer (HTTP '.$res->status().'): '.mb_substr($err, 0, 120)];
    }
}
