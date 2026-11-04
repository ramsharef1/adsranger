<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Taboola Backstage API 1.0 — the agency's own client ID + secret (client credentials, 12-hour tokens). */
class TaboolaConnector extends KeyConnector
{
    protected const API = 'https://backstage.taboola.com/backstage/api/1.0/';

    protected const TOKEN = 'https://backstage.taboola.com/backstage/oauth/token';

    public function keyFields(): array
    {
        return [
            ['client_id', 'Client ID', false, 'From your Taboola account manager (advertiser or network/agency credentials).'],
            ['client_secret', 'Client secret', true, 'Sent with the Client ID. Stored encrypted.'],
        ];
    }

    public function keyHelp(): string
    {
        return 'Ask your Taboola account manager for Backstage API credentials (client ID and secret). Network credentials give access to all your sub-accounts.';
    }

    protected function fetchToken(array $creds): array
    {
        $res = Http::asForm()->timeout(20)->post(self::TOKEN, ['client_id' => $creds['client_id'] ?? '', 'client_secret' => $creds['client_secret'] ?? '', 'grant_type' => 'client_credentials']);
        $tok = $res->json('access_token') ?: throw $this->fail($res, 'rejected the credentials');

        return ['token' => $tok, 'expires_in' => (int) ($res->json('expires_in') ?: 43200)];
    }

    protected function identify(array $creds, ?string $token): array
    {
        $res = Http::withToken((string) $token)->timeout(20)->get(self::API.'users/current/account');

        return [$res->json('account_id') ? (string) $res->json('account_id') : null, $res->json('name')];
    }

    protected function get(Connection $c, string $path, array $q = []): array
    {
        $res = static::client()->withToken($this->token($c))->get(self::API.$path, $q);
        if ($res->failed()) {
            throw $this->fail($res, 'error');
        }

        return (array) $res->json();
    }

    public function accounts(Connection $connection): array
    {
        return array_map(fn ($a) => ['id' => (string) $a['account_id'], 'name' => $a['name'] ?? null, 'currency' => $a['currency'] ?? null,
            'timezone' => $a['time_zone_name'] ?? null, 'status' => null, 'meta' => ['type' => $a['type'] ?? null, 'numeric_id' => $a['id'] ?? null]],
            array_values(array_filter($this->get($connection, 'users/current/allowed-accounts')['results'] ?? [], fn ($a) => ! empty($a['account_id']))));
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Taboola connection is missing. Reconnect it.');
        $acc = rawurlencode((string) $account->external_id);

        $campaigns = [];
        for ($page = 1; $page <= 40; $page++) {
            $r = $this->get($c, "$acc/campaigns/", ['fetch_level' => 'R', 'page' => $page, 'page_size' => 500]);
            foreach ($r['results'] ?? [] as $x) {
                $campaigns[(string) $x['id']] = ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null,
                    'status' => $x['status'] ?? (isset($x['is_active']) ? ($x['is_active'] ? 'RUNNING' : 'PAUSED') : null),
                    'objective' => $x['marketing_objective'] ?? null, 'daily_budget' => is_numeric($x['daily_cap'] ?? null) ? (float) $x['daily_cap'] : null,
                    'lifetime_budget' => ($x['spending_limit_model'] ?? '') === 'ENTIRE' && is_numeric($x['spending_limit'] ?? null) ? (float) $x['spending_limit'] : null,
                    'start_time' => $x['start_date'] ?? null, 'end_time' => $x['end_date'] ?? null];
            }
            if (count($r['results'] ?? []) < 500) {
                break;
            }
        }

        $raw = [];
        foreach (static::windows($from, $to, 31) as [$f, $t]) {
            $r = $this->get($c, "$acc/reports/campaign-summary/dimensions/campaign_day_breakdown", ['start_date' => $f, 'end_date' => $t, 'exclude_empty_campaigns' => 'true']);
            foreach ($r['results'] ?? [] as $x) {
                if (! empty($x['campaign']) && ! empty($x['date'])) {
                    $raw[] = $x;
                }
            }
        }
        $converts = [];
        foreach ($raw as $x) {
            $converts[(string) $x['campaign']] = ($converts[(string) $x['campaign']] ?? false) || (float) ($x['cpa_actions_num'] ?? 0) > 0;
            $campaigns[(string) $x['campaign']] ??= ['external_id' => (string) $x['campaign'], 'name' => $x['campaign_name'] ?? null];
        }
        $rows = array_map(fn ($x) => static::campaignRow((string) $x['date'], (string) $x['campaign'], ['spend' => (float) ($x['spent'] ?? 0),
            'impressions' => (int) ($x['impressions'] ?? 0), 'clicks' => (int) ($x['clicks'] ?? 0), 'revenue' => (float) ($x['conversions_value'] ?? 0)],
            $converts[(string) $x['campaign']], (float) ($x['cpa_actions_num'] ?? 0)), $raw);

        // Items (ads) for campaigns that spent in the range; Taboola has no per-item daily stats, so results stay at campaign level.
        $ads = [];
        foreach (array_slice(array_keys($converts), 0, 150) as $cid) {
            foreach ($this->get($c, "$acc/campaigns/$cid/items/")['results'] ?? [] as $it) {
                $ads[] = ['external_id' => (string) $it['id'], 'name' => $it['title'] ?? null, 'campaign' => (string) $cid,
                    'status' => $it['approval_state'] ?? ($it['status'] ?? null), 'thumbnail_url' => $it['thumbnail_url'] ?? null,
                    'review_feedback' => ($it['approval_state'] ?? '') === 'REJECTED' ? 'Rejected by Taboola' : null];
            }
        }

        return ['campaigns' => array_values($campaigns), 'ad_sets' => [], 'ads' => $ads, 'rows' => $rows];
    }
}
