<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Snapchat Marketing API v1 (OAuth2, 30-minute tokens with refresh). */
class SnapchatConnector extends Connector
{
    protected const API = 'https://adsapi.snapchat.com/v1/';

    protected const TOKEN = 'https://accounts.snapchat.com/login/oauth2/access_token';

    protected const GOALS = [
        'SWIPES' => 'link_click', 'IMPRESSIONS' => 'impressions', 'VIDEO_VIEWS' => 'thruplay', 'VIDEO_VIEWS_15_SEC' => 'thruplay',
        'PIXEL_PURCHASE' => 'purchase', 'PIXEL_SIGNUP' => 'registration', 'PIXEL_ADD_TO_CART' => 'add_to_cart', 'PIXEL_PAGE_VIEW' => 'landing_page_view',
        'LEAD_FORM_SUBMISSIONS' => 'lead', 'APP_INSTALLS' => 'app_install', 'USES' => 'engagement', 'STORY_OPENS' => 'engagement', 'LANDING_PAGE_VIEW' => 'landing_page_view',
    ];

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
        return 'https://accounts.snapchat.com/login/oauth2/authorize?'.http_build_query(['client_id' => $this->cred('client_id'),
            'redirect_uri' => $this->redirectUri(), 'response_type' => 'code', 'scope' => 'snapchat-marketing-api', 'state' => $state]);
    }

    public function exchange(string $code): array
    {
        $res = Http::asForm()->timeout(20)->post(self::TOKEN, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri(),
            'client_id' => $this->cred('client_id'), 'client_secret' => $this->cred('client_secret')]);
        $token = $res->json('access_token') ?: throw new RuntimeException('Snapchat did not return a token: '.($res->json('error_description') ?? $res->json('error')));
        $me = Http::withToken($token)->timeout(15)->get(self::API.'me');

        return ['access_token' => $token, 'refresh_token' => $res->json('refresh_token'), 'expires_in' => $res->json('expires_in'),
            'user_id' => $me->json('me.id'), 'user_name' => $me->json('me.display_name') ?? $me->json('me.email'), 'scopes' => $res->json('scope')];
    }

    protected function refresh(string $refreshToken): array
    {
        return $this->oauthRefresh(self::TOKEN, $refreshToken);
    }

    protected function get(Connection $c, string $path, array $q = []): array
    {
        $url = self::API.$path;
        $out = [];
        for ($i = 0; $url && $i < 50; $i++) {
            $res = Http::withToken($this->token($c))->timeout(60)->retry(2, 2000, throw: false)->get($url, $q);
            if ($res->failed() || $res->json('request_status') === 'ERROR') {
                throw new RuntimeException('Snapchat: '.($res->json('debug_message') ?? $res->json('display_message') ?? 'HTTP '.$res->status()));
            }
            $out[] = $res->json();
            $url = $res->json('paging.next_link');
            $q = [];
        }

        return $out;
    }

    /** Flatten e.g. campaigns[].campaign over every page. */
    protected function list(Connection $c, string $path, string $plural, string $single): array
    {
        $out = [];
        foreach ($this->get($c, $path, ['limit' => 1000]) as $page) {
            foreach ($page[$plural] ?? [] as $w) {
                if (isset($w[$single])) {
                    $out[] = $w[$single];
                }
            }
        }

        return $out;
    }

    public function accounts(Connection $connection): array
    {
        $out = [];
        foreach ($this->get($connection, 'me/organizations', ['with_ad_accounts' => 'true']) as $page) {
            foreach ($page['organizations'] ?? [] as $o) {
                foreach ($o['organization']['ad_accounts'] ?? [] as $a) {
                    $out[] = ['id' => $a['id'], 'name' => $a['name'] ?? null, 'currency' => $a['currency'] ?? null, 'timezone' => $a['timezone'] ?? null, 'status' => $a['status'] ?? null];
                }
            }
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Snapchat connection is missing. Reconnect it.');
        $id = $account->external_id;
        $micro = fn ($v) => is_numeric($v) ? $v / 1_000_000 : null;

        $campaigns = array_map(fn ($x) => ['external_id' => $x['id'], 'name' => $x['name'] ?? null, 'status' => $x['status'] ?? null, 'objective' => $x['objective'] ?? null,
            'daily_budget' => $micro($x['daily_budget_micro'] ?? null), 'start_time' => $x['start_time'] ?? null, 'end_time' => $x['end_time'] ?? null],
            $this->list($c, "adaccounts/$id/campaigns", 'campaigns', 'campaign'));
        $types = [];
        $sets = array_map(function ($x) use (&$types, $micro) {
            $types[$x['id']] = self::GOALS[strtoupper((string) ($x['optimization_goal'] ?? ''))] ?? 'link_click';

            return ['external_id' => $x['id'], 'name' => $x['name'] ?? null, 'campaign' => $x['campaign_id'] ?? null, 'status' => $x['status'] ?? null,
                'optimization_goal' => $x['optimization_goal'] ?? null, 'daily_budget' => $micro($x['daily_budget_micro'] ?? null),
                'start_time' => $x['start_time'] ?? null, 'end_time' => $x['end_time'] ?? null];
        }, $this->list($c, "adaccounts/$id/adsquads", 'adsquads', 'adsquad'));
        $setCamp = array_column($sets, 'campaign', 'external_id');
        $adMap = [];
        $ads = array_map(function ($x) use (&$adMap, $setCamp) {
            $adMap[$x['id']] = $x['ad_squad_id'] ?? null;

            return ['external_id' => $x['id'], 'name' => $x['name'] ?? null, 'ad_set' => $x['ad_squad_id'] ?? null,
                'campaign' => $setCamp[$x['ad_squad_id'] ?? ''] ?? null, 'status' => $x['review_status'] ?? ($x['status'] ?? null),
                'review_feedback' => isset($x['review_status_reasons']) ? json_encode($x['review_status_reasons']) : null];
        }, $this->list($c, "adaccounts/$id/ads", 'ads', 'ad'));

        $tz = $account->timezone ?: 'UTC';
        $fields = 'impressions,swipes,spend,video_views,conversion_purchases,conversion_purchases_value,conversion_sign_ups,conversion_add_cart,conversion_page_views';
        $rows = [];
        foreach (static::windows($from, $to, 31) as [$f, $t]) {
            $pages = $this->get($c, "adaccounts/$id/stats", ['granularity' => 'DAY', 'breakdown' => 'ad', 'fields' => $fields,
                'start_time' => Carbon::parse($f, $tz)->startOfDay()->toIso8601String(), 'end_time' => Carbon::parse($t, $tz)->addDay()->startOfDay()->toIso8601String()]);
            foreach ($pages as $page) {
                foreach ($page['timeseries_stats'] ?? [] as $ts) {
                    foreach ($ts['timeseries_stat']['breakdown_stats']['ad'] ?? [] as $adStat) {
                        $ad = $adStat['id'];
                        $set = $adMap[$ad] ?? null;
                        $type = $types[$set] ?? 'link_click';
                        foreach ($adStat['timeseries'] ?? [] as $day) {
                            $s = $day['stats'] ?? [];
                            if (empty($s['impressions']) && empty($s['spend'])) {
                                continue;
                            }
                            $m = ['spend' => ($s['spend'] ?? 0) / 1_000_000, 'impressions' => (int) ($s['impressions'] ?? 0), 'clicks' => (int) ($s['swipes'] ?? 0),
                                'link_clicks' => (int) ($s['swipes'] ?? 0), 'thruplays' => (float) ($s['video_views'] ?? 0), 'purchases' => (float) ($s['conversion_purchases'] ?? 0),
                                'leads' => (float) ($s['conversion_sign_ups'] ?? 0), 'add_to_cart' => (float) ($s['conversion_add_cart'] ?? 0),
                                'landing_views' => (float) ($s['conversion_page_views'] ?? 0), 'revenue' => ($s['conversion_purchases_value'] ?? 0) / 1_000_000];
                            $res = match ($type) { 'registration' => $m['leads'], 'add_to_cart' => $m['add_to_cart'], default => null };
                            $rows[] = ['date' => Carbon::parse($day['start_time'])->setTimezone($tz)->toDateString(), 'level' => 'ad',
                                'campaign' => $setCamp[$set] ?? null, 'ad_set' => $set, 'ad' => $ad] + static::metrics($m, $type, $res);
                        }
                    }
                }
            }
        }

        return ['campaigns' => $campaigns, 'ad_sets' => $sets, 'ads' => $ads, 'rows' => $rows];
    }

    public function test(): array
    {
        return $this->oauthClientTest(self::TOKEN);
    }
}
