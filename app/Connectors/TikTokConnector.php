<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** TikTok Business API v1.3 (advertiser OAuth; tokens do not expire). */
class TikTokConnector extends Connector
{
    protected const BASE = 'https://business-api.tiktok.com/open_api/v1.3/';

    // Ad group optimization_goal → result type. TikTok's own "result" metric already follows the goal.
    protected const GOALS = [
        'CONVERT' => 'conversion', 'VALUE' => 'purchase', 'LEAD_GENERATION' => 'lead', 'CLICK' => 'link_click', 'TRAFFIC_LANDING_PAGE_VIEW' => 'landing_page_view',
        'REACH' => 'reach', 'SHOW' => 'impressions', 'VIDEO_VIEW' => 'thruplay', 'ENGAGED_VIEW' => 'thruplay', 'INSTALL' => 'app_install',
        'FOLLOWERS' => 'engagement', 'PROFILE_VIEWS' => 'profile_visit', 'CONVERSATION' => 'message', 'MT_LIVE_ROOM' => 'engagement', 'PRODUCT_CLICK_IN_LIVE' => 'link_click',
    ];

    public function supportsConnect(): bool
    {
        return (bool) ($this->cred('app_id') && $this->cred('app_secret'));
    }

    public function supportsSync(): bool
    {
        return true;
    }

    public function authorizeUrl(string $state): string
    {
        return 'https://business-api.tiktok.com/portal/auth?'.http_build_query(['app_id' => $this->cred('app_id'), 'state' => $state, 'redirect_uri' => $this->redirectUri()]);
    }

    protected function call(string $method, string $path, array $data, ?string $token = null): array
    {
        $req = Http::timeout(60)->retry(2, 2000, throw: false)->withHeaders(array_filter(['Access-Token' => $token]));
        $res = $method === 'get' ? $req->get(self::BASE.$path, array_map(fn ($v) => is_array($v) ? json_encode($v) : $v, $data)) : $req->post(self::BASE.$path, $data);
        if ($res->failed() || (int) $res->json('code', -1) !== 0) {
            throw new RuntimeException('TikTok: '.($res->json('message') ?? 'HTTP '.$res->status()));
        }

        return (array) $res->json('data', []);
    }

    public function exchange(string $code): array
    {
        $d = $this->call('post', 'oauth2/access_token/', ['app_id' => $this->cred('app_id'), 'secret' => $this->cred('app_secret'), 'auth_code' => $code]);

        return ['access_token' => $d['access_token'] ?? throw new RuntimeException('TikTok did not return a token.'), 'refresh_token' => null,
            'expires_in' => null, 'user_id' => null, 'user_name' => 'TikTok advertiser access', 'scopes' => isset($d['scope']) ? json_encode($d['scope']) : null];
    }

    public function accounts(Connection $connection): array
    {
        $d = $this->call('get', 'oauth2/advertiser/get/', ['app_id' => $this->cred('app_id'), 'secret' => $this->cred('app_secret')], $connection->access_token);
        $ids = array_map(fn ($a) => (string) $a['advertiser_id'], $d['list'] ?? []);
        $out = [];
        foreach (array_chunk($ids, 100) as $chunk) {
            $info = $this->call('get', 'advertiser/info/', ['advertiser_ids' => $chunk, 'fields' => ['advertiser_id', 'name', 'currency', 'timezone', 'status']], $connection->access_token);
            foreach ($info['list'] ?? [] as $a) {
                $out[] = ['id' => (string) $a['advertiser_id'], 'name' => $a['name'] ?? null, 'currency' => $a['currency'] ?? null,
                    'timezone' => $a['timezone'] ?? null, 'status' => $a['status'] ?? null];
            }
        }

        return $out;
    }

    /** Every page of a list endpoint. */
    protected function pages(string $path, array $q, string $token): array
    {
        $out = [];
        for ($p = 1; $p <= 50; $p++) {
            $d = $this->call('get', $path, $q + ['page' => $p, 'page_size' => 1000], $token);
            array_push($out, ...($d['list'] ?? []));
            if ($p >= (int) ($d['page_info']['total_page'] ?? 1)) {
                break;
            }
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $token = $account->connection?->access_token ?: throw new RuntimeException('The TikTok connection has no token. Reconnect it.');
        $adv = $account->external_id;

        $campaigns = [];
        foreach ($this->pages('campaign/get/', ['advertiser_id' => $adv], $token) as $c) {
            $campaigns[] = ['external_id' => (string) $c['campaign_id'], 'name' => $c['campaign_name'] ?? null, 'status' => $c['operation_status'] ?? null,
                'objective' => $c['objective_type'] ?? null, 'daily_budget' => ($c['budget_mode'] ?? '') === 'BUDGET_MODE_DAY' ? (float) $c['budget'] : null];
        }
        $sets = [];
        $types = [];
        foreach ($this->pages('adgroup/get/', ['advertiser_id' => $adv], $token) as $g) {
            $sets[] = ['external_id' => (string) $g['adgroup_id'], 'name' => $g['adgroup_name'] ?? null, 'campaign' => (string) ($g['campaign_id'] ?? ''),
                'status' => $g['operation_status'] ?? null, 'optimization_goal' => $g['optimization_goal'] ?? null,
                'daily_budget' => ($g['budget_mode'] ?? '') === 'BUDGET_MODE_DAY' ? (float) $g['budget'] : null,
                'start_time' => $g['schedule_start_time'] ?? null, 'end_time' => $g['schedule_end_time'] ?? null];
            $types[(string) $g['adgroup_id']] = self::GOALS[strtoupper((string) ($g['optimization_goal'] ?? ''))] ?? 'conversion';
        }
        $ads = [];
        $adSet = [];
        foreach ($this->pages('ad/get/', ['advertiser_id' => $adv], $token) as $a) {
            $ads[] = ['external_id' => (string) $a['ad_id'], 'name' => $a['ad_name'] ?? null, 'campaign' => (string) ($a['campaign_id'] ?? ''),
                'ad_set' => (string) ($a['adgroup_id'] ?? ''), 'status' => $a['operation_status'] ?? null];
            $adSet[(string) $a['ad_id']] = [(string) ($a['campaign_id'] ?? ''), (string) ($a['adgroup_id'] ?? '')];
        }

        $rows = [];
        foreach (static::windows($from, $to, 30) as [$f, $t]) {
            $list = $this->pages('report/integrated/get/', [
                'advertiser_id' => $adv, 'report_type' => 'BASIC', 'data_level' => 'AUCTION_AD', 'start_date' => $f, 'end_date' => $t,
                'dimensions' => ['ad_id', 'stat_time_day'],
                'metrics' => ['spend', 'impressions', 'reach', 'clicks', 'result', 'conversion', 'complete_payment', 'complete_payment_roas', 'video_watched_6s'],
            ], $token);
            foreach ($list as $r) {
                $ad = (string) ($r['dimensions']['ad_id'] ?? '');
                $m = $r['metrics'] ?? [];
                [$camp, $set] = $adSet[$ad] ?? [null, null];
                $type = $types[$set] ?? 'conversion';
                $spend = (float) ($m['spend'] ?? 0);
                $purch = (float) ($m['complete_payment'] ?? 0);
                $rows[] = ['date' => substr((string) $r['dimensions']['stat_time_day'], 0, 10), 'level' => 'ad', 'campaign' => $camp, 'ad_set' => $set, 'ad' => $ad]
                    + static::metrics(['spend' => $spend, 'impressions' => (int) ($m['impressions'] ?? 0), 'reach' => (int) ($m['reach'] ?? 0),
                        'clicks' => (int) ($m['clicks'] ?? 0), 'link_clicks' => (int) ($m['clicks'] ?? 0), 'purchases' => $purch,
                        'thruplays' => (float) ($m['video_watched_6s'] ?? 0), 'revenue' => $spend * (float) ($m['complete_payment_roas'] ?? 0)],
                        $type, (float) ($m['result'] ?? $m['conversion'] ?? 0));
            }
        }

        return ['campaigns' => $campaigns, 'ad_sets' => $sets, 'ads' => $ads, 'rows' => $rows];
    }

    public function test(): array
    {
        if (! $this->supportsConnect()) {
            return [false, 'App ID or Secret is missing.'];
        }
        $res = Http::timeout(15)->post(self::BASE.'oauth2/access_token/', ['app_id' => $this->cred('app_id'), 'secret' => $this->cred('app_secret'), 'auth_code' => 'adsranger-key-test']);
        $msg = strtolower((string) $res->json('message'));
        if (str_contains($msg, 'app_id') || str_contains($msg, 'secret') || str_contains($msg, 'app does not exist')) {
            return [false, 'TikTok rejected the App ID or Secret: '.$res->json('message')];
        }

        return str_contains($msg, 'auth_code') || str_contains($msg, 'auth code')
            ? [true, 'Keys accepted (the test code was rejected, as expected).']
            : [null, 'Unclear answer from TikTok: '.mb_substr((string) $res->json('message'), 0, 160)];
    }
}
