<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Pinterest API v5 (OAuth2, 30-day access tokens, continuous 60-day refresh). Daily data per ad group; the sync API keeps 90 days. */
class PinterestConnector extends Connector
{
    protected const API = 'https://api.pinterest.com/v5/';

    protected const COLUMNS = 'CAMPAIGN_ID,AD_GROUP_ID,SPEND_IN_MICRO_DOLLAR,PAID_IMPRESSION,CLICKTHROUGH_1,OUTBOUND_CLICK_1,TOTAL_CONVERSIONS,TOTAL_CHECKOUT,TOTAL_CHECKOUT_VALUE_IN_MICRO_DOLLAR,TOTAL_LEAD,TOTAL_SIGNUP,TOTAL_ADD_TO_CART,TOTAL_PAGE_VISIT,VIDEO_3SEC_VIEWS_2';

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
        return 'https://www.pinterest.com/oauth/?'.http_build_query(['client_id' => $this->cred('client_id'), 'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code', 'scope' => 'ads:read,user_accounts:read', 'state' => $state]);
    }

    protected function tokenCall(array $body)
    {
        return Http::asForm()->withBasicAuth((string) $this->cred('client_id'), (string) $this->cred('client_secret'))->timeout(20)->post(self::API.'oauth/token', $body);
    }

    public function exchange(string $code): array
    {
        $res = $this->tokenCall(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri(), 'continuous_refresh' => 'true']);
        $tok = $res->json('access_token') ?: throw new RuntimeException('Pinterest did not return a token: '.($res->json('message') ?? $res->json('error_description') ?? $res->json('error')));
        $me = Http::withToken($tok)->timeout(15)->get(self::API.'user_account');

        return ['access_token' => $tok, 'refresh_token' => $res->json('refresh_token'), 'expires_in' => $res->json('expires_in'),
            'user_id' => $me->json('id') ? (string) $me->json('id') : null, 'user_name' => $me->json('username') ?? $me->json('business_name'), 'scopes' => $res->json('scope')];
    }

    protected function refresh(string $refreshToken): array
    {
        $res = $this->tokenCall(['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken, 'continuous_refresh' => 'true']);
        $tok = $res->json('access_token') ?: throw new RuntimeException('Pinterest refresh failed: '.($res->json('message') ?? 'HTTP '.$res->status()).'. Reconnect the account.');

        return ['access_token' => $tok, 'refresh_token' => $res->json('refresh_token'), 'expires_in' => $res->json('expires_in')];
    }

    protected function get(Connection $c, string $path, array $q = []): mixed
    {
        $res = Http::withToken($this->token($c))->acceptJson()->timeout(90)
            ->retry(3, fn (int $n, $e) => min(60, (int) (($e->response ?? null)?->header('x-ratelimit-reset') ?: 5 * $n)) * 1000,
                fn ($e) => in_array(($e->response ?? null)?->status(), [429, 500, 503], true), throw: false)
            ->get(self::API.$path, $q);
        if ($res->failed()) {
            throw new RuntimeException('Pinterest: '.($res->json('message') ?? 'HTTP '.$res->status()));
        }

        return $res->json();
    }

    protected function list(Connection $c, string $path, array $q = []): array
    {
        $out = [];
        $bm = null;
        for ($i = 0; $i < 100; $i++) {
            $r = $this->get($c, $path, $q + ['page_size' => 250] + ($bm ? ['bookmark' => $bm] : []));
            $out = array_merge($out, $r['items'] ?? []);
            $bm = $r['bookmark'] ?? null;
            if (! $bm) {
                break;
            }
        }

        return $out;
    }

    public function accounts(Connection $connection): array
    {
        return array_map(fn ($a) => ['id' => (string) $a['id'], 'name' => $a['name'] ?? null, 'currency' => $a['currency'] ?? null,
            'timezone' => $a['time_zone'] ?? null, 'status' => null, 'meta' => ['country' => $a['country'] ?? null]], $this->list($connection, 'ad_accounts'));
    }

    protected static function ts($v): ?string
    {
        return is_numeric($v) && $v > 0 ? Carbon::createFromTimestamp((int) $v)->toIso8601String() : null;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Pinterest connection is missing. Reconnect it.');
        $id = rawurlencode((string) $account->external_id);
        $all = ['entity_statuses' => 'ACTIVE,PAUSED,ARCHIVED'];
        $micro = fn ($v) => is_numeric($v) ? $v / 1_000_000 : null;

        $objective = [];
        $campaigns = array_map(function ($x) use (&$objective, $micro) {
            $objective[(string) $x['id']] = strtoupper((string) ($x['objective_type'] ?? ''));

            return ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'status' => $x['status'] ?? null, 'objective' => $x['objective_type'] ?? null,
                'daily_budget' => $micro($x['daily_spend_cap'] ?? null), 'lifetime_budget' => $micro($x['lifetime_spend_cap'] ?? null),
                'start_time' => static::ts($x['start_time'] ?? null), 'end_time' => static::ts($x['end_time'] ?? null)];
        }, $this->list($c, "ad_accounts/$id/campaigns", $all));
        $sets = array_map(fn ($x) => ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'campaign' => (string) ($x['campaign_id'] ?? ''),
            'status' => $x['status'] ?? null, 'optimization_goal' => $x['billable_event'] ?? null,
            'daily_budget' => ($x['budget_type'] ?? '') === 'DAILY' ? $micro($x['budget_in_micro_currency'] ?? null) : null,
            'lifetime_budget' => ($x['budget_type'] ?? '') === 'LIFETIME' ? $micro($x['budget_in_micro_currency'] ?? null) : null,
            'start_time' => static::ts($x['start_time'] ?? null), 'end_time' => static::ts($x['end_time'] ?? null)], $this->list($c, "ad_accounts/$id/ad_groups", $all));
        $setCamp = array_column($sets, 'campaign', 'external_id');
        $ads = array_map(fn ($x) => ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'ad_set' => (string) ($x['ad_group_id'] ?? ''),
            'campaign' => (string) ($x['campaign_id'] ?? ''), 'status' => $x['review_status'] ?? ($x['status'] ?? null),
            'review_feedback' => ! empty($x['rejected_reasons']) ? json_encode($x['rejected_reasons']) : null], $this->list($c, "ad_accounts/$id/ads", $all));

        // The analytics API covers the last 90 days.
        $tz = $account->timezone ?: 'UTC';
        $min = now($tz)->subDays(89)->toDateString();
        $f = max($from, $min);
        if ($f > $to) {
            return ['campaigns' => $campaigns, 'ad_sets' => $sets, 'ads' => $ads, 'rows' => [], 'range' => null];
        }
        $rows = [];
        foreach (array_chunk(array_keys($setCamp), 250) as $chunk) {
            $data = $this->get($c, "ad_accounts/$id/ad_groups/analytics", ['start_date' => $f, 'end_date' => $to, 'granularity' => 'DAY',
                'ad_group_ids' => implode(',', $chunk), 'columns' => self::COLUMNS]);
            foreach ((array) $data as $r) {
                $spend = (float) ($r['SPEND_IN_MICRO_DOLLAR'] ?? 0) / 1_000_000;
                if ($spend == 0 && empty($r['PAID_IMPRESSION'])) {
                    continue;
                }
                $set = (string) ($r['AD_GROUP_ID'] ?? '');
                $camp = (string) ($r['CAMPAIGN_ID'] ?? ($setCamp[$set] ?? ''));
                $obj = $objective[$camp] ?? '';
                $m = ['spend' => $spend, 'impressions' => (int) ($r['PAID_IMPRESSION'] ?? 0), 'clicks' => (int) ($r['CLICKTHROUGH_1'] ?? 0),
                    'link_clicks' => (int) ($r['OUTBOUND_CLICK_1'] ?? 0), 'purchases' => (float) ($r['TOTAL_CHECKOUT'] ?? 0),
                    'revenue' => (float) ($r['TOTAL_CHECKOUT_VALUE_IN_MICRO_DOLLAR'] ?? 0) / 1_000_000, 'leads' => (float) ($r['TOTAL_LEAD'] ?? 0) + (float) ($r['TOTAL_SIGNUP'] ?? 0),
                    'add_to_cart' => (float) ($r['TOTAL_ADD_TO_CART'] ?? 0), 'landing_views' => (float) ($r['TOTAL_PAGE_VISIT'] ?? 0), 'thruplays' => (float) ($r['VIDEO_3SEC_VIEWS_2'] ?? 0)];
                $type = match (true) {
                    in_array($obj, ['CONVERSION', 'WEB_CONVERSION', 'CATALOG_SALES', 'SALES'], true) => $m['purchases'] > 0 || $m['leads'] == 0 ? 'purchase' : 'lead',
                    in_array($obj, ['LEAD', 'LEADS'], true) => 'lead',
                    in_array($obj, ['AWARENESS'], true) => 'impressions',
                    in_array($obj, ['VIDEO_VIEW', 'VIDEO_COMPLETION'], true) => 'thruplay',
                    default => 'link_click',
                };
                $rows[] = ['date' => substr((string) ($r['DATE'] ?? ''), 0, 10), 'level' => 'ad_set', 'campaign' => $camp ?: null, 'ad_set' => $set] + static::metrics($m, $type);
            }
        }

        return ['campaigns' => $campaigns, 'ad_sets' => $sets, 'ads' => $ads, 'rows' => $rows, 'range' => [$f, $to]];
    }

    public function test(): array
    {
        if (! $this->cred('client_id') || ! $this->cred('client_secret')) {
            return [false, 'App ID or secret is missing.'];
        }
        $res = $this->tokenCall(['grant_type' => 'authorization_code', 'code' => 'adsranger-key-test', 'redirect_uri' => $this->redirectUri()]);
        $msg = strtolower((string) ($res->json('message') ?? $res->json('error') ?? ''));
        if ($res->status() === 401 || str_contains($msg, 'client') && str_contains($msg, 'invalid')) {
            return [false, 'Pinterest rejected the app ID or secret.'];
        }

        return [$res->status() === 400 ? true : null, $res->status() === 400 ? 'Keys accepted (the test code was rejected, as expected).' : 'Unclear answer (HTTP '.$res->status().'): '.mb_substr($msg, 0, 120)];
    }
}
