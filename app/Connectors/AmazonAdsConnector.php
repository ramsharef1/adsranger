<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use App\Sync\ReportPending;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Amazon Ads API (Login with Amazon OAuth). Each advertiser profile (one per marketplace) is an ad account.
 * Daily campaign data comes from asynchronous v3 reports (Sponsored Products, Brands, Display); unfinished reports retry every 10 minutes.
 * Reporting v3 shuts off on 30 Jun 2027: move to Reporting API v1 before then.
 */
class AmazonAdsConnector extends Connector
{
    protected const HOSTS = ['na' => 'https://advertising-api.amazon.com', 'eu' => 'https://advertising-api-eu.amazon.com', 'fe' => 'https://advertising-api-fe.amazon.com'];

    protected const AUTH = ['na' => 'https://www.amazon.com/ap/oa', 'eu' => 'https://eu.account.amazon.com/ap/oa', 'fe' => 'https://apac.account.amazon.com/ap/oa'];

    protected const TOKEN = ['na' => 'https://api.amazon.com/auth/o2/token', 'eu' => 'https://api.amazon.co.uk/auth/o2/token', 'fe' => 'https://api.amazon.co.jp/auth/o2/token'];

    /** Sales data is kept for 60 days for Sponsored Brands (SP 95, SD 65), so all types are pulled for the last 60 days. */
    public const HISTORY_DAYS = 60;

    protected const REPORTS = [
        'SP' => ['SPONSORED_PRODUCTS', 'spCampaigns', ['purchases7d', 'sales7d']],
        'SB' => ['SPONSORED_BRANDS', 'sbCampaigns', ['purchases', 'sales']],
        'SD' => ['SPONSORED_DISPLAY', 'sdCampaigns', ['purchases', 'sales']],
    ];

    protected function region(): string
    {
        $r = strtolower((string) $this->cred('region', 'eu'));

        return isset(self::HOSTS[$r]) ? $r : 'eu';
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
        return self::AUTH[$this->region()].'?'.http_build_query(['client_id' => $this->cred('client_id'), 'scope' => 'advertising::campaign_management',
            'response_type' => 'code', 'redirect_uri' => $this->redirectUri(), 'state' => $state]);
    }

    public function exchange(string $code): array
    {
        $res = Http::asForm()->timeout(20)->post(self::TOKEN[$this->region()], ['grant_type' => 'authorization_code', 'code' => $code,
            'redirect_uri' => $this->redirectUri(), 'client_id' => $this->cred('client_id'), 'client_secret' => $this->cred('client_secret')]);
        $tok = $res->json('access_token') ?: throw new RuntimeException('Amazon did not return a token: '.($res->json('error_description') ?? $res->json('error')));
        $me = Http::withToken($tok)->timeout(15)->get('https://api.amazon.com/user/profile');

        return ['access_token' => $tok, 'refresh_token' => $res->json('refresh_token'), 'expires_in' => $res->json('expires_in'),
            'user_id' => $me->json('user_id'), 'user_name' => $me->json('name') ?? $me->json('email'), 'scopes' => 'advertising::campaign_management'];
    }

    protected function refresh(string $refreshToken): array
    {
        return $this->oauthRefresh(self::TOKEN[$this->region()], $refreshToken);
    }

    protected function req(Connection $c, ?string $profile = null, array $headers = [])
    {
        return Http::withToken($this->token($c))->withHeaders(['Amazon-Advertising-API-ClientId' => (string) $this->cred('client_id')]
            + ($profile ? ['Amazon-Advertising-API-Scope' => $profile] : []) + $headers)->timeout(60)
            ->retry(3, fn (int $n, $e) => min(60, (int) (($e->response ?? null)?->header('Retry-After') ?: 5 * $n)) * 1000,
                fn ($e) => in_array(($e->response ?? null)?->status(), [429, 500, 502, 503], true), throw: false);
    }

    public function accounts(Connection $connection): array
    {
        $out = [];
        foreach (self::HOSTS as $region => $host) {
            $res = $this->req($connection)->get($host.'/v2/profiles');
            if ($res->failed()) {
                continue;
            }
            foreach ((array) $res->json() as $p) {
                $info = $p['accountInfo'] ?? [];
                if (($info['type'] ?? '') === 'agency') {
                    continue;
                }
                $out[] = ['id' => (string) $p['profileId'], 'name' => trim(($info['name'] ?? 'Amazon').' · '.($p['countryCode'] ?? '')), 'currency' => $p['currencyCode'] ?? null,
                    'timezone' => $p['timezone'] ?? null, 'status' => ! empty($info['validPaymentMethod']) || ! isset($info['validPaymentMethod']) ? null : 'PAYMENT_ISSUE',
                    'meta' => ['region' => $region, 'country' => $p['countryCode'] ?? null, 'type' => $info['type'] ?? null, 'marketplace' => $info['marketplaceStringId'] ?? null]];
            }
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Amazon connection is missing. Reconnect it.');
        $profile = (string) $account->external_id;
        $meta = (array) ($account->meta ?? []);
        $host = self::HOSTS[$meta['region'] ?? $this->region()] ?? self::HOSTS['eu'];
        $tz = $account->timezone ?: 'UTC';
        $f = max($from, now($tz)->subDays(self::HISTORY_DAYS - 1)->toDateString());
        $t = min($to, now($tz)->toDateString());
        if ($f > $t) {
            return ['campaigns' => [], 'ad_sets' => [], 'ads' => [], 'rows' => [], 'range' => null];
        }

        $pending = (array) ($meta['amazon_reports'] ?? []);
        $data = [];
        $waiting = false;
        foreach (static::windows($f, $t, 31) as [$wf, $wt]) {
            foreach (self::REPORTS as $type => [$product, $reportType, [$pCol, $sCol]]) {
                $key = "$type:$wf:$wt";
                if (($pending[$key] ?? null) === 'skip') {
                    continue;
                }
                if (empty($pending[$key])) {
                    $res = $this->req($c, $profile)->withBody(json_encode([
                        'name' => "AdsRanger $type $wf $wt", 'startDate' => $wf, 'endDate' => $wt,
                        'configuration' => ['adProduct' => $product, 'reportTypeId' => $reportType, 'groupBy' => ['campaign'], 'timeUnit' => 'DAILY', 'format' => 'GZIP_JSON',
                            'columns' => ['date', 'campaignId', 'campaignName', 'campaignStatus', 'campaignBudgetAmount', 'impressions', 'clicks', 'cost', $pCol, $sCol]],
                    ]), 'application/vnd.createasyncreportrequest.v3+json')->post($host.'/reporting/reports');
                    if ($res->status() === 425 && preg_match('/[0-9a-f-]{36}/', (string) $res->json('detail'), $m)) {
                        $pending[$key] = $m[0];          // duplicate request: reuse the report already queued
                    } elseif ($res->successful() && $res->json('reportId')) {
                        $pending[$key] = $res->json('reportId');
                    } elseif ($type !== 'SP' && in_array($res->status(), [400, 401, 403, 404], true)) {
                        $pending[$key] = 'skip';         // this profile doesn't run Brands / Display
                        continue;
                    } else {
                        throw new RuntimeException('Amazon report request: '.mb_substr((string) ($res->json('detail') ?? $res->json('message') ?? 'HTTP '.$res->status()), 0, 200));
                    }
                }
                $st = $this->req($c, $profile)->get($host.'/reporting/reports/'.rawurlencode($pending[$key]));
                $status = strtoupper((string) $st->json('status'));
                if ($status === 'COMPLETED' && $st->json('url')) {
                    $raw = Http::timeout(120)->get((string) $st->json('url'))->body();
                    $json = @gzdecode($raw);
                    foreach ((array) json_decode($json !== false ? $json : $raw, true) as $r) {
                        $data[] = [$type, $r, $pCol, $sCol];
                    }
                } elseif ($status === 'FAILED' || $st->status() === 404) {
                    unset($pending[$key]);
                    $waiting = true;
                } else {
                    $waiting = true;
                }
            }
        }
        if ($waiting) {
            $account->update(['meta' => ['amazon_reports' => $pending] + $meta]);
            throw new ReportPending('Amazon is preparing the reports (this can take up to a few hours).');
        }
        // All reports for this range collected: forget their ids so the next sync asks for fresh numbers.
        foreach (static::windows($f, $t, 31) as [$wf, $wt]) {
            foreach (array_keys(self::REPORTS) as $type) {
                unset($pending["$type:$wf:$wt"]);
            }
        }
        $account->update(['meta' => ['amazon_reports' => $pending] + $meta]);

        $campaigns = [];
        $rows = [];
        foreach ($data as [$type, $r, $pCol, $sCol]) {
            $cid = (string) ($r['campaignId'] ?? '');
            if ($cid === '') {
                continue;
            }
            $campaigns[$cid] = ['external_id' => $cid, 'name' => $r['campaignName'] ?? null, 'status' => $r['campaignStatus'] ?? null, 'channel' => $type,
                'objective' => $type === 'SP' ? 'Sponsored Products' : ($type === 'SB' ? 'Sponsored Brands' : 'Sponsored Display'),
                'daily_budget' => is_numeric($r['campaignBudgetAmount'] ?? null) ? (float) $r['campaignBudgetAmount'] : null];
            if (empty($r['impressions']) && empty($r['cost'])) {
                continue;
            }
            $m = ['spend' => (float) ($r['cost'] ?? 0), 'impressions' => (int) ($r['impressions'] ?? 0), 'clicks' => (int) ($r['clicks'] ?? 0),
                'link_clicks' => (int) ($r['clicks'] ?? 0), 'purchases' => (float) ($r[$pCol] ?? 0), 'revenue' => (float) ($r[$sCol] ?? 0)];
            $rows[] = ['date' => substr((string) $r['date'], 0, 10), 'level' => 'campaign', 'campaign' => $cid] + static::metrics($m, 'purchase');
        }

        return ['campaigns' => array_values($campaigns), 'ad_sets' => [], 'ads' => [], 'rows' => $rows, 'range' => [$f, $t]];
    }

    public function test(): array
    {
        return $this->oauthClientTest(self::TOKEN[$this->region()]);
    }
}
