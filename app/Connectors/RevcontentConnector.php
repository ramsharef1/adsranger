<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Revcontent Stats API v1.0 — the advertiser's "Stats API Credentials" (client credentials, 24-hour tokens). Sub-accounts are separate ad accounts. */
class RevcontentConnector extends KeyConnector
{
    protected const API = 'https://api.revcontent.io/stats/api/v1.0/';

    protected const TOKEN = 'https://api.revcontent.io/oauth/token';

    public function keyFields(): array
    {
        return [
            ['client_id', 'Client ID', false, 'Revcontent > Account Settings > Stats API Credentials.'],
            ['client_secret', 'Client secret', true, 'Same screen. Stored encrypted.'],
            ['currency', 'Account currency', false, 'Revcontent does not report the currency. Usually USD.', 'text', true],
        ];
    }

    public function keyHelp(): string
    {
        return 'Ask your Revcontent representative to enable API access, then copy the Stats API credentials from Account Settings.';
    }

    protected function fetchToken(array $creds): array
    {
        $res = Http::asForm()->timeout(20)->post(self::TOKEN, ['grant_type' => 'client_credentials', 'client_id' => $creds['client_id'] ?? '', 'client_secret' => $creds['client_secret'] ?? '']);
        $tok = $res->json('access_token') ?: throw $this->fail($res, 'rejected the credentials');

        return ['token' => $tok, 'expires_in' => (int) ($res->json('expires_in') ?: 86400)];
    }

    protected function get(Connection $c, string $path, array $q = []): array
    {
        $res = static::client()->withToken($this->token($c))->withHeaders(['Content-Type' => 'application/json'])->get(self::API.$path, $q);
        if ($res->failed() || $res->json('success') === false) {
            throw $this->fail($res, 'error');
        }

        return (array) $res->json('data');
    }

    /** All pages of a list endpoint. */
    protected function all(Connection $c, string $path, array $q): array
    {
        $out = [];
        for ($off = 0; $off < 50000; $off += 1000) {
            $page = $this->get($c, $path, $q + ['limit' => 1000, 'offset' => $off]);
            $out = array_merge($out, $page);
            if (count($page) < 1000) {
                break;
            }
        }

        return $out;
    }

    public function accounts(Connection $connection): array
    {
        $cur = strtoupper((string) (($connection->credentials['currency'] ?? '') ?: 'USD'));
        $out = [['id' => 'main', 'name' => ($connection->external_user_name ?: 'Revcontent').' (main account)', 'currency' => $cur, 'timezone' => null, 'status' => null]];
        try {
            foreach ($this->get($connection, 'sub_accounts/list_accounts') as $s) {
                $out[] = ['id' => (string) $s['user_id'], 'name' => $s['user_email'] ?? ('Sub-account '.$s['user_id']), 'currency' => $cur, 'timezone' => null, 'status' => $s['status'] ?? null];
            }
        } catch (RuntimeException) {
            // No sub-accounts on this login.
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Revcontent connection is missing. Reconnect it.');
        $sub = $account->external_id === 'main' ? [] : ['sub_account_id' => $account->external_id];

        $campaigns = [];
        foreach ($this->all($c, 'boosts', $sub) as $x) {
            $campaigns[(string) $x['id']] = ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'status' => $x['status'] ?? ($x['enabled'] ?? null),
                'daily_budget' => is_numeric($x['budget'] ?? null) ? (float) $x['budget'] : null,
                'start_time' => $x['start_date'] ?? null, 'end_time' => ($x['end_date'] ?? '') && ! str_starts_with((string) $x['end_date'], '0000') ? $x['end_date'] : null];
        }

        // Campaigns active in the range, then daily rows per campaign.
        $active = array_filter($this->all($c, 'boosts/performance', $sub + ['date_from' => $from, 'date_to' => $to, 'all_boosts' => 'yes']),
            fn ($r) => (float) ($r['cost'] ?? 0) > 0 || (int) ($r['impressions'] ?? 0) > 0);
        $rows = [];
        foreach (array_slice(array_values($active), 0, 300) as $sum) {
            $cid = (string) $sum['id'];
            $converts = (float) ($sum['conversions'] ?? 0) > 0;
            foreach ($this->get($c, 'boosts/performance', $sub + ['boost_id' => $cid, 'date_from' => $from, 'date_to' => $to, 'limit' => 1000]) as $d) {
                if (empty($d['date']) || ((float) ($d['cost'] ?? 0) == 0 && (int) ($d['impressions'] ?? 0) == 0)) {
                    continue;
                }
                $date = strlen((string) $d['date']) === 8 ? '20'.$d['date'] : (string) $d['date'];
                $rows[] = static::campaignRow($date, $cid, ['spend' => (float) ($d['cost'] ?? 0), 'impressions' => (int) ($d['impressions'] ?? 0),
                    'clicks' => (int) ($d['clicks'] ?? 0), 'revenue' => (float) ($d['return'] ?? 0)], $converts, (float) ($d['conversions'] ?? 0));
            }
            $campaigns[$cid] ??= ['external_id' => $cid, 'name' => $sum['name'] ?? null];
        }

        $ads = array_map(fn ($a) => ['external_id' => (string) $a['content_id'], 'name' => $a['headline'] ?? null, 'campaign' => (string) ($a['boost_id'] ?? ''),
            'status' => $a['admin_status'] ?? null, 'thumbnail_url' => $a['image_url'] ?? null, 'review_feedback' => $a['creative_denial_reason'] ?? null],
            array_filter($this->all($c, 'boosts/content', $sub), fn ($a) => ! empty($a['content_id']) && ($a['admin_status'] ?? '') !== 'deleted'));

        return ['campaigns' => array_values($campaigns), 'ad_sets' => [], 'ads' => array_values($ads), 'rows' => $rows];
    }
}
