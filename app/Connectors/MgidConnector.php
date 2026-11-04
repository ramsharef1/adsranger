<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Carbon\Carbon;
use RuntimeException;

/** MGID API v1 — the advertiser's (or agency's) API token + account ID from the dashboard (Settings > API). */
class MgidConnector extends KeyConnector
{
    protected const API = 'https://api.mgid.com/v1/';

    public function keyFields(): array
    {
        return [
            ['client_id', 'Account (client) ID', false, 'The number shown next to your account name in the MGID dashboard.'],
            ['token', 'API token', true, 'Dashboard > Settings > API. 32 characters. Stored encrypted.'],
            ['agency', 'Agency account?', false, 'Type yes if this is an MGID agency account: its clients appear as separate ad accounts.', 'text', true],
        ];
    }

    public function keyHelp(): string
    {
        return 'Copy the API token and your account ID from the MGID dashboard (Settings > API).';
    }

    protected function fetchToken(array $creds): array
    {
        if (! preg_match('/^\d+$/', (string) ($creds['client_id'] ?? ''))) {
            throw new RuntimeException('The MGID account ID is a number.');
        }
        $path = $this->isAgency($creds) ? 'agencies/'.$creds['client_id'].'/clients' : 'clients/'.$creds['client_id'];
        $res = static::client(20)->withToken((string) ($creds['token'] ?? ''))->get(self::API.$path);
        if ($res->failed() || $res->json('errors')) {
            throw $this->fail($res, 'rejected the token or account ID');
        }

        return ['token' => $creds['token'], 'expires_in' => null];
    }

    protected function isAgency(array $creds): bool
    {
        return in_array(strtolower(trim((string) ($creds['agency'] ?? ''))), ['yes', 'y', '1', 'true', 'نعم'], true);
    }

    protected function identify(array $creds, ?string $token): array
    {
        return [(string) $creds['client_id'], 'MGID '.($this->isAgency($creds) ? 'agency ' : '').$creds['client_id']];
    }

    protected function get(Connection $c, string $path, array $q = []): array
    {
        $res = static::client()->withToken($this->token($c))->get(self::API.$path, $q);
        if ($res->failed() || $res->json('errors')) {
            throw $this->fail($res, 'error');
        }

        return (array) $res->json();
    }

    public function accounts(Connection $connection): array
    {
        $cr = (array) $connection->credentials;
        if ($this->isAgency($cr)) {
            $out = [];
            foreach ($this->get($connection, 'agencies/'.$cr['client_id'].'/clients') as $k => $cl) {
                $cl = (array) $cl;
                $id = (string) ($cl['id'] ?? $k);
                $out[] = ['id' => $id, 'name' => $cl['email'] ?? ('Client '.$id), 'currency' => $cl['wallet']['currency'] ?? null, 'timezone' => null, 'status' => $cl['status'] ?? null];
            }

            return $out;
        }
        $me = $this->get($connection, 'clients/'.$cr['client_id']);
        $me = (array) ($me[$cr['client_id']] ?? $me);

        return [['id' => (string) $cr['client_id'], 'name' => $me['email'] ?? ('MGID '.$cr['client_id']), 'currency' => $me['wallet']['currency'] ?? $me['currency'] ?? null,
            'timezone' => $me['timezone'] ?? null, 'status' => $me['status'] ?? null]];
    }

    /** MGID returns lists as objects keyed by id. */
    protected static function items(array $r, string $key): array
    {
        $list = $r[$key] ?? $r;
        $out = [];
        foreach ((array) $list as $k => $v) {
            if (is_array($v)) {
                $v['id'] ??= $k;
                $out[] = $v;
            }
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The MGID connection is missing. Reconnect it.');
        $cid = rawurlencode((string) $account->external_id);

        $campaigns = [];
        for ($start = 0; $start < 20000; $start += 500) {
            $list = static::items($this->get($c, "goodhits/clients/$cid/campaigns", ['limit' => 500, 'start' => $start]), 'campaigns');
            foreach ($list as $x) {
                $lim = $x['limitsFilter'] ?? $x['limits'] ?? [];
                $campaigns[(string) $x['id']] = ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null,
                    'status' => is_array($x['status'] ?? null) ? ($x['status']['code'] ?? $x['status']['name'] ?? null) : ($x['status'] ?? null),
                    'daily_budget' => is_numeric($lim['dailyLimit'] ?? null) && (float) $lim['dailyLimit'] > 0 ? (float) $lim['dailyLimit'] : null,
                    'lifetime_budget' => is_numeric($lim['overallLimit'] ?? null) && (float) $lim['overallLimit'] > 0 ? (float) $lim['overallLimit'] : null];
            }
            if (count($list) < 500) {
                break;
            }
        }

        // campaigns-stat returns totals per interval, so ask one day at a time.
        $raw = [];
        for ($d = Carbon::parse($from); $d->lte(Carbon::parse($to)); $d->addDay()) {
            $day = $d->toDateString();
            $r = $this->get($c, "goodhits/clients/$cid/campaigns-stat", ['dateInterval' => 'interval', 'startDate' => $day, 'endDate' => $day]);
            foreach ((array) ($r['campaigns-stat'] ?? $r['campaigns'] ?? $r) as $k => $s) {
                if (! is_array($s) || ! is_numeric($k)) {
                    continue;
                }
                $raw[] = [$day, (string) $k, $s];
            }
        }
        $converts = [];
        foreach ($raw as [, $k, $s]) {
            $converts[$k] = ($converts[$k] ?? false) || (float) ($s['buy'] ?? 0) + (float) ($s['decision'] ?? 0) > 0;
            $campaigns[$k] ??= ['external_id' => $k, 'name' => null];
        }
        $rows = [];
        foreach ($raw as [$day, $k, $s]) {
            if (empty($s['imps']) && empty($s['spent'])) {
                continue;
            }
            $rows[] = static::campaignRow($day, $k, ['spend' => (float) ($s['spent'] ?? 0), 'impressions' => (int) ($s['imps'] ?? $s['shows'] ?? 0),
                'clicks' => (int) ($s['clicks'] ?? 0), 'purchases' => (float) ($s['buy'] ?? 0), 'revenue' => (float) ($s['revenue'] ?? 0)],
                $converts[$k], (float) ($s['buy'] ?? 0) + (float) ($s['decision'] ?? 0));
        }

        $ads = [];
        for ($start = 0; $start < 20000; $start += 500) {
            $list = static::items($this->get($c, "goodhits/clients/$cid/teasers", ['limit' => 500, 'start' => $start]), 'teasers');
            foreach ($list as $t) {
                $st = $t['status'] ?? null;
                $ads[] = ['external_id' => (string) $t['id'], 'name' => $t['title'] ?? null, 'campaign' => isset($t['campaignId']) ? (string) $t['campaignId'] : null,
                    'status' => is_array($st) ? ($st['code'] ?? $st['name'] ?? null) : $st, 'thumbnail_url' => $t['imageLink'] ?? null,
                    'review_feedback' => is_array($st) && ! empty($st['reason']) ? (string) $st['reason'] : null];
            }
            if (count($list) < 500) {
                break;
            }
        }

        return ['campaigns' => array_values($campaigns), 'ad_sets' => [], 'ads' => $ads, 'rows' => $rows];
    }
}
