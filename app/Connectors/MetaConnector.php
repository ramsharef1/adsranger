<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use App\Support\ResultResolver;
use App\Sync\BreakdownStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        $urls = [];
        foreach ($this->all("$act/ads", ['fields' => 'id,name,adset_id,campaign_id,effective_status,creative{thumbnail_url,image_url,object_story_spec,asset_feed_spec{link_urls}},ad_review_feedback', 'limit' => 200], $token) as $a) {
            $fb = $a['ad_review_feedback']['global'] ?? null;
            // Landing URLs for the landing page checker (#49), only from active ads.
            if (($a['effective_status'] ?? '') === 'ACTIVE') {
                $spec = $a['creative']['object_story_spec'] ?? [];
                foreach ([$spec['link_data']['link'] ?? null, $spec['video_data']['call_to_action']['value']['link'] ?? null,
                    $spec['link_data']['call_to_action']['value']['link'] ?? null] as $u) {
                    if ($u) {
                        $urls[$u] = true;
                    }
                }
                foreach ($a['creative']['asset_feed_spec']['link_urls'] ?? [] as $lu) {
                    if (! empty($lu['website_url'])) {
                        $urls[$lu['website_url']] = true;
                    }
                }
            }
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

        $breakdowns = config('insights.pull_breakdowns', true) ? $this->breakdowns($act, $token, $from, $to, $campaigns, $adSets) : [];

        return ['campaigns' => array_values($campaigns), 'ad_sets' => array_values($adSets), 'ads' => array_values($ads), 'rows' => $rows, 'breakdowns' => $breakdowns, 'landing_urls' => array_keys($urls)];
    }

    /**
     * Campaign-day breakdowns (#43 hours, #44 placements + age × gender, #97 regions) and 30-day reach for overlap (#98).
     * A failed breakdown never fails the sync: it is logged and skipped.
     */
    protected function breakdowns(string $act, string $token, string $from, string $to, array $campaigns, array $adSets): array
    {
        $type = [];
        foreach ($adSets as $s) {
            $c = (string) ($s['campaign'] ?? '');
            $type[$c] ??= ResultResolver::metaType($s['optimization_goal'] ?? null, $campaigns[$c]['objective'] ?? null, $s['custom_event'] ?? null);
        }
        $range = json_encode(['since' => $from, 'until' => $to]);
        $fields = 'date_start,campaign_id,spend,impressions,clicks,inline_link_clicks,actions,action_values,video_thruplay_watched_actions';
        // Hourly stats can't be combined with reach.
        $specs = ['hour' => ['hourly_stats_aggregated_by_advertiser_time_zone', false], 'placement' => ['publisher_platform,platform_position', true],
            'region' => ['region', true], 'age_gender' => ['age,gender', true]];
        $out = [];
        foreach ($specs as $dim => [$bd, $withReach]) {
            try {
                $rows = [];
                foreach ($this->all("$act/insights", ['level' => 'campaign', 'time_increment' => 1, 'limit' => 500, 'time_range' => $range,
                    'breakdowns' => $bd, 'fields' => $fields.($withReach ? ',reach' : '')], $token, 100) as $r) {
                    $cid = (string) ($r['campaign_id'] ?? '');
                    $t = $type[$cid] ?? ResultResolver::metaType(null, $campaigns[$cid]['objective'] ?? null);
                    $m = ResultResolver::meta($r, $t);
                    $key = match ($dim) {
                        'hour' => (string) (int) substr((string) ($r['hourly_stats_aggregated_by_advertiser_time_zone'] ?? '0'), 0, 2),
                        'placement' => ($r['publisher_platform'] ?? 'unknown').':'.($r['platform_position'] ?? 'unknown'),
                        'region' => (string) ($r['region'] ?? 'Unknown'),
                        'age_gender' => ($r['age'] ?? 'unknown').'|'.($r['gender'] ?? 'unknown'),
                    };
                    $rows[] = ['date' => $r['date_start'], 'key' => $key, 'spend' => $m['spend'], 'impressions' => $m['impressions'], 'reach' => $m['reach'],
                        'clicks' => $m['clicks'], 'link_clicks' => $m['link_clicks'], 'results' => $m['results'], 'result_type' => $t];
                }
                $out[$dim] = BreakdownStore::collapse($rows);
            } catch (\Throwable $e) {
                Log::warning('Meta breakdown skipped', ['account' => $act, 'dimension' => $dim, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            }
        }

        // Rolling 30-day reach per ad set and for the whole account (only on recent pulls).
        if ($to >= now()->subDays(3)->toDateString()) {
            try {
                $r30 = json_encode(['since' => \Carbon\Carbon::parse($to)->subDays(29)->toDateString(), 'until' => $to]);
                $rows = [];
                foreach ($this->all("$act/insights", ['level' => 'adset', 'limit' => 500, 'time_range' => $r30, 'fields' => 'adset_id,impressions,reach,clicks,inline_link_clicks,spend'], $token, 20) as $r) {
                    $rows[] = ['date' => $to, 'key' => 'adset:'.$r['adset_id'], 'impressions' => (int) $r['impressions'], 'reach' => (int) ($r['reach'] ?? 0),
                        'clicks' => (int) ($r['clicks'] ?? 0), 'link_clicks' => (int) ($r['inline_link_clicks'] ?? 0), 'spend' => (float) ($r['spend'] ?? 0)];
                }
                $acc = $this->all("$act/insights", ['level' => 'account', 'time_range' => $r30, 'fields' => 'impressions,reach,clicks,spend'], $token, 1)[0] ?? null;
                if ($acc) {
                    $rows[] = ['date' => $to, 'key' => 'account', 'impressions' => (int) $acc['impressions'], 'reach' => (int) ($acc['reach'] ?? 0),
                        'clicks' => (int) ($acc['clicks'] ?? 0), 'spend' => (float) ($acc['spend'] ?? 0)];
                }
                $out['reach30'] = $rows;
            } catch (\Throwable $e) {
                Log::warning('Meta 30-day reach skipped', ['account' => $act, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            }
        }

        return $out;
    }

    public function health(AdAccount $account): array
    {
        $token = $account->connection?->access_token;
        if (! $token) {
            return [['high', 'The Meta connection has no token. Reconnect it.']];
        }
        $res = Http::timeout(30)->get($this->graph($account->external_id), ['fields' => 'account_status,disable_reason,amount_spent,spend_cap', 'access_token' => $token]);
        if ($res->failed()) {
            return [['high', 'Meta: '.($res->json('error.message') ?? 'the account could not be read')]];
        }
        $out = [];
        $st = (int) $res->json('account_status');
        $names = [2 => 'disabled', 3 => 'unsettled (a payment failed)', 7 => 'pending risk review', 8 => 'pending settlement', 9 => 'in grace period (payment due)', 100 => 'pending closure', 101 => 'closed'];
        if (isset($names[$st])) {
            $out[] = [in_array($st, [9, 8], true) ? 'medium' : 'high', 'Meta account is '.$names[$st].'.'.((int) $res->json('disable_reason') ? ' Disable reason code '.$res->json('disable_reason').'.' : '')];
        }
        $cap = (float) $res->json('spend_cap');
        $spent = (float) $res->json('amount_spent');
        if ($cap > 0 && $spent / $cap >= 0.9) {
            $out[] = [$spent >= $cap ? 'high' : 'medium', 'Account spending limit '.round($spent / $cap * 100).'% used ('.number_format($spent / 100, 2).' of '.number_format($cap / 100, 2).' '.$account->currency.').'];
        }

        return $out;
    }
}
