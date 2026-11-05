<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use App\Support\ResultResolver;
use App\Sync\BreakdownStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GoogleAdsConnector extends Connector
{
    /** Each major Google Ads API version is supported for about a year (monthly releases since 2026). Override in /admin → Ad Networks. */
    public const DEFAULT_VERSION = 'v26';

    /** Oldest major version still accepted as a stored override (v24 sunsets ~May 2027, ADSR-050). */
    public const MIN_MAJOR = 24;

    protected string $scope = 'https://www.googleapis.com/auth/adwords';

    /** The stored override (like v26) if it is well-formed and not older than MIN_MAJOR; otherwise the default. */
    public function apiVersion(): string
    {
        $v = strtolower(trim((string) $this->cred('api_version', '')));

        return preg_match('/^v(\d+)$/', $v, $m) && (int) $m[1] >= self::MIN_MAJOR ? $v : self::DEFAULT_VERSION;
    }

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
        $v = $this->apiVersion();
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

    public function supportsSync(): bool
    {
        return true;
    }

    /** Run a GAQL query and return every result row. */
    protected function search(Connection $c, string $customerId, string $query): array
    {
        $token = $this->freshToken($c);
        $v = $this->apiVersion();
        $headers = array_filter(['login-customer-id' => preg_replace('/\D/', '', (string) $this->cred('login_customer_id')) ?: null]);
        $out = [];
        $body = ['query' => $query];
        for ($i = 0; $i < 50; $i++) {
            $res = Http::withToken($token)->withHeaders($headers)->timeout(60)->retry(2, 2000, throw: false)
                ->post("https://googleads.googleapis.com/{$v}/customers/{$customerId}/googleAds:search", $body);
            if ($res->failed()) {
                $msg = $res->json('error.details.0.errors.0.message') ?? $res->json('error.message') ?? 'HTTP '.$res->status();
                throw new RuntimeException('Google Ads: '.$msg);
            }
            array_push($out, ...$res->json('results', []));
            $next = $res->json('nextPageToken');
            if (! $next) {
                break;
            }
            $body['pageToken'] = $next;
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Google Ads connection is missing. Reconnect it.');
        $cid = preg_replace('/\D/', '', $account->external_id);
        $range = "segments.date BETWEEN '$from' AND '$to'";

        $campaigns = [];
        foreach ($this->search($c, $cid, "SELECT campaign.id, campaign.name, campaign.status, campaign.advertising_channel_type, campaign_budget.amount_micros FROM campaign WHERE campaign.status != 'REMOVED'") as $r) {
            $id = (string) $r['campaign']['id'];
            $campaigns[$id] = ['external_id' => $id, 'name' => $r['campaign']['name'] ?? null, 'status' => $r['campaign']['status'] ?? null,
                'objective' => null, 'channel' => $r['campaign']['advertisingChannelType'] ?? null,
                'daily_budget' => isset($r['campaignBudget']['amountMicros']) ? $r['campaignBudget']['amountMicros'] / 1_000_000 : null];
        }

        $actions = [];
        foreach ($this->search($c, $cid, "SELECT campaign.id, segments.date, segments.conversion_action_name, segments.conversion_action_category, metrics.conversions, metrics.conversions_value FROM campaign WHERE $range AND metrics.conversions > 0") as $r) {
            $actions[$r['campaign']['id'].'|'.$r['segments']['date']][] = ['name' => $r['segments']['conversionActionName'] ?? '?',
                'category' => $r['segments']['conversionActionCategory'] ?? null, 'conversions' => $r['metrics']['conversions'] ?? 0, 'value' => $r['metrics']['conversionsValue'] ?? 0];
        }

        $rows = [];
        foreach ($this->search($c, $cid, "SELECT campaign.id, segments.date, metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value FROM campaign WHERE $range") as $r) {
            $id = (string) $r['campaign']['id'];
            $d = $r['segments']['date'];
            $m = $r['metrics'] ?? [];
            $rows[] = ['date' => $d, 'level' => 'campaign', 'campaign' => $id, 'ad_set' => null, 'ad' => null]
                + ResultResolver::google(['cost_micros' => $m['costMicros'] ?? 0, 'impressions' => $m['impressions'] ?? 0, 'clicks' => $m['clicks'] ?? 0,
                    'conversions' => $m['conversions'] ?? 0, 'conversions_value' => $m['conversionsValue'] ?? 0], $actions[$id.'|'.$d] ?? []);
        }

        $breakdowns = config('insights.pull_breakdowns', true) ? $this->breakdowns($c, $cid, $range) : [];
        $google = config('insights.pull_google_extras', true) ? $this->extras($c, $cid, $range, $to) : [];

        return ['campaigns' => array_values($campaigns), 'ad_sets' => [], 'ads' => [], 'rows' => $rows, 'breakdowns' => $breakdowns, 'google' => $google];
    }

    /** Run one GAQL query; a failure is logged and returns null (never fails the sync). */
    protected function tryQuery(Connection $c, string $cid, string $what, string $q): ?array
    {
        try {
            return $this->search($c, $cid, $q);
        } catch (\Throwable $e) {
            Log::warning('Google '.$what.' skipped', ['customer' => $cid, 'error' => mb_substr($e->getMessage(), 0, 200)]);

            return null;
        }
    }

    /** Impression share (#47), search terms (#46), keyword Quality Score (#47), PMax asset groups (#48), landing URLs (#49). */
    protected function extras(Connection $c, string $cid, string $range, string $to): array
    {
        $out = [];
        $num = fn ($v) => is_numeric($v) ? (float) $v : null;
        if (($rows = $this->tryQuery($c, $cid, 'impression share', "SELECT campaign.id, segments.date, metrics.search_impression_share, metrics.search_rank_lost_impression_share, metrics.search_budget_lost_impression_share, metrics.search_top_impression_share, metrics.search_absolute_top_impression_share FROM campaign WHERE $range AND campaign.advertising_channel_type = 'SEARCH'")) !== null) {
            $out['campaign_daily'] = array_map(fn ($r) => ['campaign' => $r['campaign']['id'], 'date' => $r['segments']['date'],
                'search_is' => $num($r['metrics']['searchImpressionShare'] ?? null), 'lost_rank' => $num($r['metrics']['searchRankLostImpressionShare'] ?? null),
                'lost_budget' => $num($r['metrics']['searchBudgetLostImpressionShare'] ?? null), 'top_is' => $num($r['metrics']['searchTopImpressionShare'] ?? null),
                'abs_top_is' => $num($r['metrics']['searchAbsoluteTopImpressionShare'] ?? null)], $rows);
        }
        // Search terms: only for recent ranges (deep backfills skip them to save API quota).
        if ($to >= now()->subDays(120)->toDateString()
            && ($rows = $this->tryQuery($c, $cid, 'search terms', "SELECT campaign.id, ad_group.id, search_term_view.search_term, search_term_view.status, segments.date, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM search_term_view WHERE $range AND metrics.impressions > 0")) !== null) {
            $out['search_terms'] = array_map(fn ($r) => ['campaign' => $r['campaign']['id'], 'ad_group' => $r['adGroup']['id'] ?? null,
                'term' => $r['searchTermView']['searchTerm'] ?? '', 'status' => $r['searchTermView']['status'] ?? null, 'date' => $r['segments']['date'],
                'impressions' => (int) ($r['metrics']['impressions'] ?? 0), 'clicks' => (int) ($r['metrics']['clicks'] ?? 0),
                'cost' => ((float) ($r['metrics']['costMicros'] ?? 0)) / 1_000_000, 'conversions' => (float) ($r['metrics']['conversions'] ?? 0),
                'conv_value' => (float) ($r['metrics']['conversionsValue'] ?? 0)], $rows);
        }
        if ($to < now()->subDays(3)->toDateString()) {
            return $out; // snapshots below only on the nightly / recent pulls
        }
        if (($rows = $this->tryQuery($c, $cid, 'keywords', "SELECT campaign.id, ad_group.id, ad_group_criterion.criterion_id, ad_group_criterion.keyword.text, ad_group_criterion.keyword.match_type, ad_group_criterion.status, ad_group_criterion.quality_info.quality_score, ad_group_criterion.quality_info.creative_quality_score, ad_group_criterion.quality_info.post_click_quality_score, ad_group_criterion.quality_info.search_predicted_ctr, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions FROM keyword_view WHERE segments.date DURING LAST_30_DAYS AND ad_group_criterion.status != 'REMOVED'")) !== null) {
            $out['keywords'] = array_map(fn ($r) => ['campaign' => $r['campaign']['id'], 'ad_group' => $r['adGroup']['id'], 'id' => $r['adGroupCriterion']['criterionId'],
                'text' => $r['adGroupCriterion']['keyword']['text'] ?? '', 'match_type' => $r['adGroupCriterion']['keyword']['matchType'] ?? null,
                'status' => $r['adGroupCriterion']['status'] ?? null, 'qs' => $r['adGroupCriterion']['qualityInfo']['qualityScore'] ?? null,
                'creative_qs' => $r['adGroupCriterion']['qualityInfo']['creativeQualityScore'] ?? null, 'landing_qs' => $r['adGroupCriterion']['qualityInfo']['postClickQualityScore'] ?? null,
                'ctr_qs' => $r['adGroupCriterion']['qualityInfo']['searchPredictedCtr'] ?? null, 'impressions' => (int) ($r['metrics']['impressions'] ?? 0),
                'clicks' => (int) ($r['metrics']['clicks'] ?? 0), 'cost' => ((float) ($r['metrics']['costMicros'] ?? 0)) / 1_000_000,
                'conversions' => (float) ($r['metrics']['conversions'] ?? 0)], $rows);
        }
        if (($rows = $this->tryQuery($c, $cid, 'asset groups', "SELECT campaign.id, campaign.name, asset_group.id, asset_group.name, asset_group.status, asset_group.ad_strength, metrics.impressions, metrics.clicks, metrics.cost_micros, metrics.conversions, metrics.conversions_value FROM asset_group WHERE segments.date DURING LAST_30_DAYS")) !== null) {
            $groups = [];
            foreach ($rows as $r) {
                $id = (string) $r['assetGroup']['id'];
                $groups[$id] = ['campaign' => $r['campaign']['id'], 'campaign_name' => $r['campaign']['name'] ?? null, 'id' => $id, 'name' => $r['assetGroup']['name'] ?? null,
                    'status' => $r['assetGroup']['status'] ?? null, 'ad_strength' => $r['assetGroup']['adStrength'] ?? null,
                    'impressions' => (int) ($r['metrics']['impressions'] ?? 0), 'clicks' => (int) ($r['metrics']['clicks'] ?? 0),
                    'cost' => ((float) ($r['metrics']['costMicros'] ?? 0)) / 1_000_000, 'conversions' => (float) ($r['metrics']['conversions'] ?? 0),
                    'conv_value' => (float) ($r['metrics']['conversionsValue'] ?? 0), 'assets' => []];
            }
            if ($groups) {
                $q = "SELECT asset_group.id, asset_group_asset.field_type, asset_group_asset.performance_label, asset.type, asset.name, asset.text_asset.text FROM asset_group_asset WHERE asset_group_asset.status != 'REMOVED'";
                $assets = $this->tryQuery($c, $cid, 'asset labels', $q) ?? $this->tryQuery($c, $cid, 'assets', str_replace('asset_group_asset.performance_label, ', '', $q)) ?? [];
                foreach ($assets as $r) {
                    $gid = (string) ($r['assetGroup']['id'] ?? '');
                    if (isset($groups[$gid])) {
                        $groups[$gid]['assets'][] = ['field' => $r['assetGroupAsset']['fieldType'] ?? null, 'label' => $r['assetGroupAsset']['performanceLabel'] ?? null,
                            'type' => $r['asset']['type'] ?? null, 'text' => mb_substr((string) ($r['asset']['textAsset']['text'] ?? ($r['asset']['name'] ?? '')), 0, 120)];
                    }
                }
            }
            $out['asset_groups'] = array_values($groups);
        }
        if (($rows = $this->tryQuery($c, $cid, 'landing pages', 'SELECT landing_page_view.unexpanded_final_url, metrics.clicks FROM landing_page_view WHERE segments.date DURING LAST_30_DAYS AND metrics.clicks > 0')) !== null) {
            $urls = [];
            foreach ($rows as $r) {
                $u = (string) ($r['landingPageView']['unexpandedFinalUrl'] ?? '');
                if ($u !== '') {
                    $urls[$u] = ($urls[$u] ?? 0) + (int) ($r['metrics']['clicks'] ?? 0);
                }
            }
            $out['landing_urls'] = $urls;
        }

        return $out;
    }

    /**
     * Add negative keywords at campaign level (#46). $items: [['campaign' => id, 'text' => ..., 'match' => EXACT|PHRASE]].
     * All or nothing; throws with Google's message on failure.
     */
    public function addNegatives(AdAccount $account, array $items): int
    {
        $c = $account->connection ?: throw new RuntimeException('The Google Ads connection is missing. Reconnect it.');
        $cid = preg_replace('/\D/', '', $account->external_id);
        $v = $this->apiVersion();
        $headers = array_filter(['login-customer-id' => preg_replace('/\D/', '', (string) $this->cred('login_customer_id')) ?: null]);
        $ops = array_map(fn ($i) => ['create' => ['campaign' => "customers/$cid/campaigns/".preg_replace('/\D/', '', (string) $i['campaign']), 'negative' => true,
            'keyword' => ['text' => mb_substr((string) $i['text'], 0, 80), 'matchType' => $i['match'] === 'PHRASE' ? 'PHRASE' : 'EXACT']]], $items);
        $res = Http::withToken($this->freshToken($c))->withHeaders($headers)->timeout(60)
            ->post("https://googleads.googleapis.com/{$v}/customers/{$cid}/campaignCriteria:mutate", ['operations' => $ops]);
        if ($res->failed()) {
            throw new RuntimeException('Google Ads: '.($res->json('error.details.0.errors.0.message') ?? $res->json('error.message') ?? 'HTTP '.$res->status()));
        }

        return count($res->json('results', []));
    }

    /** Hour of day (#43), network + device (#44), city (#97). Primary conversions are the results. Failures are logged, not fatal. */
    protected function breakdowns(Connection $c, string $cid, string $range): array
    {
        $m = 'metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions';
        $specs = [
            'hour' => ["SELECT segments.date, segments.hour, $m FROM campaign WHERE $range", fn ($r) => (string) ($r['segments']['hour'] ?? 0)],
            'placement' => ["SELECT segments.date, segments.ad_network_type, $m FROM campaign WHERE $range", fn ($r) => 'google:'.strtolower((string) ($r['segments']['adNetworkType'] ?? 'unknown'))],
            'device' => ["SELECT segments.date, segments.device, $m FROM campaign WHERE $range", fn ($r) => strtolower((string) ($r['segments']['device'] ?? 'unknown'))],
            'region' => ["SELECT segments.date, segments.geo_target_city, $m FROM geographic_view WHERE $range AND segments.geo_target_city IS NOT NULL",
                fn ($r) => (string) ($r['segments']['geoTargetCity'] ?? 'unknown')],
        ];
        $out = [];
        foreach ($specs as $dim => [$q, $key]) {
            try {
                $rows = [];
                foreach ($this->search($c, $cid, $q) as $r) {
                    $x = $r['metrics'] ?? [];
                    $rows[] = ['date' => $r['segments']['date'], 'key' => $key($r), 'spend' => ((float) ($x['costMicros'] ?? 0)) / 1_000_000,
                        'impressions' => (int) ($x['impressions'] ?? 0), 'clicks' => (int) ($x['clicks'] ?? 0), 'link_clicks' => (int) ($x['clicks'] ?? 0),
                        'results' => (float) ($x['conversions'] ?? 0), 'result_type' => 'conversion'];
                }
                $out[$dim] = BreakdownStore::collapse($rows);
            } catch (\Throwable $e) {
                Log::warning('Google breakdown skipped', ['customer' => $cid, 'dimension' => $dim, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            }
        }
        // City resource names → readable names.
        if (! empty($out['region'])) {
            try {
                $ids = array_values(array_unique(array_filter(array_column($out['region'], 'key'), fn ($k) => str_starts_with($k, 'geoTargetConstants/'))));
                $names = [];
                foreach (array_chunk($ids, 200) as $chunk) {
                    $in = implode(',', array_map(fn ($i) => "'$i'", $chunk));
                    foreach ($this->search($c, $cid, "SELECT geo_target_constant.resource_name, geo_target_constant.canonical_name FROM geo_target_constant WHERE geo_target_constant.resource_name IN ($in)") as $g) {
                        $names[$g['geoTargetConstant']['resourceName']] = $g['geoTargetConstant']['canonicalName'] ?? null;
                    }
                }
                foreach ($out['region'] as &$r) {
                    $r['label'] = $names[$r['key']] ?? null;
                }
                unset($r);
            } catch (\Throwable $e) {
                Log::warning('Google city names skipped', ['customer' => $cid, 'error' => mb_substr($e->getMessage(), 0, 200)]);
            }
        }

        return $out;
    }

    public function health(AdAccount $account): array
    {
        $c = $account->connection;
        if (! $c) {
            return [];
        }
        $cid = preg_replace('/\D/', '', $account->external_id);
        $out = [];
        $st = $this->search($c, $cid, 'SELECT customer.status FROM customer LIMIT 1')[0]['customer']['status'] ?? null;
        if ($st && $st !== 'ENABLED') {
            $out[] = ['critical', 'Google Ads account status: '.strtolower($st).'.'];
        }
        $n = count($this->search($c, $cid, "SELECT ad_group_ad.ad.id FROM ad_group_ad WHERE ad_group_ad.policy_summary.approval_status = 'DISAPPROVED' AND ad_group_ad.status = 'ENABLED' LIMIT 200"));
        if ($n) {
            $out[] = ['warning', "$n enabled Google ad(s) disapproved by policy."];
        }

        return $out;
    }
}
