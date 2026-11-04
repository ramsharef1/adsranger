<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Outbrain Amplify API v0.1. The agency signs in with its Outbrain user (or pastes a token from the Amplify UI).
 * Tokens last 30 days; with a username + password AdsRanger renews them by itself (login is limited to 2 calls an hour).
 */
class OutbrainConnector extends KeyConnector
{
    protected const API = 'https://api.outbrain.com/amplify/v0.1/';

    public function keyFields(): array
    {
        return [
            ['username', 'Outbrain username (email)', false, 'The user that signs in to my.outbrain.com. API access must be enabled for it.', 'text', true],
            ['password', 'Password', true, 'Stored encrypted and used only to renew the 30-day token.', 'text', true],
            ['token', 'Or: API token', true, 'Instead of the username and password, paste a token generated in Amplify. It expires after 30 days.', 'text', true],
        ];
    }

    public function keyHelp(): string
    {
        return 'Ask your Outbrain account manager to enable Amplify API access. Enter the username and password (renews automatically), or a 30-day token.';
    }

    protected function fetchToken(array $creds): array
    {
        if (! empty($creds['username']) && ! empty($creds['password'])) {
            $res = Http::withBasicAuth($creds['username'], $creds['password'])->acceptJson()->timeout(20)->get(self::API.'login');
            $tok = $res->json('OB-TOKEN-V1') ?: throw $this->fail($res, 'sign-in failed');

            return ['token' => $tok, 'expires_in' => 29 * 86400];
        }
        if (! empty($creds['token'])) {
            $res = Http::withHeaders(['OB-TOKEN-V1' => $creds['token']])->acceptJson()->timeout(20)->get(self::API.'marketers');
            if ($res->failed()) {
                throw $this->fail($res, 'rejected the token');
            }

            return ['token' => $creds['token'], 'expires_in' => null];
        }
        throw new RuntimeException('Enter the Outbrain username and password, or an API token.');
    }

    protected function identify(array $creds, ?string $token): array
    {
        return [null, $creds['username'] ?? 'API token'];
    }

    protected function get(Connection $c, string $path, array $q = []): array
    {
        $res = static::client()->withHeaders(['OB-TOKEN-V1' => $this->token($c)])->get(self::API.$path, $q);
        if ($res->status() === 401 && ! empty($c->credentials['password'])) {
            $c->update(['token_expires_at' => now()->subMinute()]);
            $res = static::client()->withHeaders(['OB-TOKEN-V1' => $this->token($c)])->get(self::API.$path, $q);
        }
        if ($res->failed()) {
            throw $this->fail($res, $res->status() === 401 ? 'token expired or revoked' : 'error');
        }

        return (array) $res->json();
    }

    public function accounts(Connection $connection): array
    {
        return array_map(fn ($m) => ['id' => (string) $m['id'], 'name' => $m['name'] ?? null, 'currency' => $m['currency'] ?? null, 'timezone' => null,
            'status' => array_key_exists('enabled', $m) ? ($m['enabled'] ? 'ACTIVE' : 'DISABLED') : null],
            $this->get($connection, 'marketers')['marketers'] ?? []);
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Outbrain connection is missing. Reconnect it.');
        $mid = rawurlencode((string) $account->external_id);

        $campaigns = [];
        for ($off = 0; $off < 5000; $off += 50) {
            $r = $this->get($c, "marketers/$mid/campaigns", ['limit' => 50, 'offset' => $off, 'includeArchived' => 'true']);
            foreach ($r['campaigns'] ?? [] as $x) {
                $b = $x['budget'] ?? [];
                $campaigns[(string) $x['id']] = ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null,
                    'status' => ! empty($x['enabled']) ? (! empty($x['liveStatus']['campaignOnAir']) ? 'ON_AIR' : 'ENABLED') : (! empty($x['autoArchived']) ? 'ARCHIVED' : 'PAUSED'),
                    'objective' => $x['objective'] ?? null,
                    'daily_budget' => ($b['type'] ?? '') === 'DAILY' ? (float) ($b['amount'] ?? 0) : (is_numeric($b['dailyTarget'] ?? null) ? (float) $b['dailyTarget'] : null),
                    'lifetime_budget' => ($b['type'] ?? '') === 'CAMPAIGN' ? (float) ($b['amount'] ?? 0) : null,
                    'start_time' => $b['startDate'] ?? null, 'end_time' => ! empty($b['runForever']) ? null : ($b['endDate'] ?? null)];
                if (! $account->currency && ! empty($x['currency'])) {
                    $account->update(['currency' => $x['currency']]);
                }
            }
            if (count($r['campaigns'] ?? []) < 50) {
                break;
            }
        }

        $rows = [];
        $spent = [];
        for ($off = 0; $off < 5000; $off += 50) {
            $r = $this->get($c, "reports/marketers/$mid/campaigns/periodic", ['from' => $from, 'to' => $to, 'breakdown' => 'daily',
                'limit' => 50, 'offset' => $off, 'enabledCampaignsOnly' => 'false', 'includeArchivedCampaigns' => 'true']);
            foreach ($r['campaignResults'] ?? [] as $cr) {
                $cid = (string) $cr['campaignId'];
                $res = $cr['results'] ?? [];
                $conv = array_sum(array_map(fn ($d) => (float) ($d['metrics']['conversions'] ?? 0), $res)) > 0;
                foreach ($res as $d) {
                    $m = $d['metrics'] ?? [];
                    if (empty($m['impressions']) && empty($m['spend'])) {
                        continue;
                    }
                    $spent[$cid] = true;
                    $rows[] = static::campaignRow((string) ($d['metadata']['fromDate'] ?? $d['metadata']['id']), $cid, ['spend' => (float) ($m['spend'] ?? 0),
                        'impressions' => (int) ($m['impressions'] ?? 0), 'clicks' => (int) ($m['clicks'] ?? 0), 'revenue' => (float) ($m['sumValue'] ?? 0)],
                        $conv, (float) ($m['conversions'] ?? 0));
                }
                $campaigns[$cid] ??= ['external_id' => $cid, 'name' => null];
            }
            if (count($r['campaignResults'] ?? []) < 50 || $off + 50 >= (int) ($r['totalCampaigns'] ?? 0)) {
                break;
            }
        }

        $ads = [];
        foreach (array_slice(array_keys($spent), 0, 100) as $cid) {
            foreach ($this->get($c, 'campaigns/'.rawurlencode($cid).'/promotedLinks', ['limit' => 500])['promotedLinks'] ?? [] as $pl) {
                $ap = $pl['approvalStatus'] ?? null;
                $ap = is_array($ap) ? ($ap['status'] ?? null) : $ap;
                $ads[] = ['external_id' => (string) $pl['id'], 'name' => $pl['text'] ?? null, 'campaign' => $cid,
                    'status' => ! empty($pl['archived']) ? 'ARCHIVED' : (is_string($ap) && $ap !== '' ? $ap : (! empty($pl['enabled']) ? 'ENABLED' : 'PAUSED')),
                    'thumbnail_url' => $pl['imageMetadata']['url'] ?? null,
                    'review_feedback' => is_array($pl['approvalStatus'] ?? null) && ! empty($pl['approvalStatus']['reasons']) ? json_encode($pl['approvalStatus']['reasons']) : null];
            }
        }

        return ['campaigns' => array_values($campaigns), 'ad_sets' => [], 'ads' => $ads, 'rows' => $rows];
    }
}
