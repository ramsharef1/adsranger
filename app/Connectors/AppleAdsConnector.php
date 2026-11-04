<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Apple Ads Platform API 1.0 (api.ads.apple.com/v1; replaces Search Ads API v5, which shuts off 26 Jan 2027).
 * The agency creates an API user in Apple Ads (Account Settings > API) and pastes the client ID, team ID, key ID and its private key.
 * We sign an ES256 client secret, swap it for a 1-hour token, and send X-AP-Context: adAccountId=… on every call.
 */
class AppleAdsConnector extends KeyConnector
{
    protected const API = 'https://api.ads.apple.com/v1/';

    protected const TOKEN = 'https://appleid.apple.com/auth/oauth2/token';

    public function keyFields(): array
    {
        return [
            ['client_id', 'Client ID', false, 'Apple Ads > Account Settings > API, after uploading your public key. Starts with SEARCHADS.'],
            ['team_id', 'Team ID', false, 'Same screen. Starts with SEARCHADS.'],
            ['key_id', 'Key ID', false, 'Same screen.'],
            ['private_key', 'Private key (PEM)', true, 'The P-256 private key you generated (-----BEGIN EC PRIVATE KEY----- …). Stored encrypted, never shown again.', 'textarea'],
        ];
    }

    public function keyHelp(): string
    {
        return 'In Apple Ads, invite an API user (API Account Read Only is enough), sign in as that user, upload a P-256 public key under Account Settings > API, then copy the IDs here with the matching private key.';
    }

    /** ES256 JWT used as the OAuth client secret (valid 1 day; Apple allows up to 180). */
    public static function clientSecret(array $creds, int $ttl = 86400): string
    {
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();
        $input = $b64(json_encode(['alg' => 'ES256', 'kid' => $creds['key_id'] ?? ''])).'.'
            .$b64(json_encode(['iss' => $creds['team_id'] ?? '', 'sub' => $creds['client_id'] ?? '', 'aud' => 'https://appleid.apple.com', 'iat' => $now, 'exp' => $now + $ttl]));
        $key = openssl_pkey_get_private((string) ($creds['private_key'] ?? ''));
        if (! $key || (openssl_pkey_get_details($key)['ec']['curve_name'] ?? '') !== 'prime256v1') {
            throw new RuntimeException('The private key is not a valid P-256 (prime256v1) PEM key.');
        }
        openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256) ?: throw new RuntimeException('Could not sign with the private key.');

        return $input.'.'.$b64(static::derToRaw($der));
    }

    /** DER ECDSA signature → 64-byte r||s as JWS needs. */
    protected static function derToRaw(string $der): string
    {
        $pos = 2 + (ord($der[1]) & 0x80 ? (ord($der[1]) & 0x7f) : 0);
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $parts[] = str_pad(ltrim(substr($der, $pos + 2, $len), "\x00"), 32, "\x00", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $parts[0].$parts[1];
    }

    protected function fetchToken(array $creds): array
    {
        $res = Http::asForm()->timeout(20)->post(self::TOKEN, ['grant_type' => 'client_credentials', 'client_id' => $creds['client_id'] ?? '',
            'client_secret' => static::clientSecret($creds), 'scope' => 'searchadsorg']);
        $tok = $res->json('access_token') ?: throw $this->fail($res, 'rejected the API credentials');

        return ['token' => $tok, 'expires_in' => (int) ($res->json('expires_in') ?: 3600)];
    }

    protected function identify(array $creds, ?string $token): array
    {
        $me = Http::withToken((string) $token)->acceptJson()->timeout(20)->get(self::API.'me');

        return [$me->json('data.userId') ?? $me->json('userId') ? (string) ($me->json('data.userId') ?? $me->json('userId')) : null, 'Apple Ads API user'];
    }

    protected function call(Connection $c, string $method, string $path, array $body = [], ?string $adAccount = null): array
    {
        $req = static::client()->withToken($this->token($c));
        if ($adAccount) {
            $req = $req->withHeaders(['X-AP-Context' => 'adAccountId='.$adAccount]);
        }
        $res = $method === 'get' ? $req->get(self::API.$path) : $req->post(self::API.$path, $body ?: (object) []);
        if ($res->failed()) {
            throw $this->fail($res, 'error');
        }

        return (array) $res->json();
    }

    public function accounts(Connection $connection): array
    {
        $r = $this->call($connection, 'get', 'acls');
        $acls = $r['data']['acls'] ?? $r['result']['acls'] ?? $r['data'] ?? $r['result'] ?? [];
        $orgs = [];
        $out = [];
        foreach ((array) $acls as $acl) {
            $acc = $acl['adAccount'] ?? $acl;
            if (empty($acc['id'])) {
                continue;
            }
            $org = (string) ($acc['orgId'] ?? '');
            if ($org !== '' && ! isset($orgs[$org])) {
                try {
                    $o = $this->call($connection, 'get', 'orgs/'.rawurlencode($org));
                    $orgs[$org] = $o['data'] ?? $o['result'] ?? $o;
                } catch (RuntimeException) {
                    $orgs[$org] = [];
                }
            }
            $o = $orgs[$org] ?? [];
            $out[] = ['id' => (string) $acc['id'], 'name' => $acc['name'] ?? null, 'currency' => $o['currency'] ?? ($acl['currency'] ?? null),
                'timezone' => $o['timezone'] ?? ($o['timeZone'] ?? null), 'status' => null, 'meta' => ['org_id' => $org ?: null, 'roles' => $acl['roles'] ?? []]];
        }

        return $out;
    }

    /** Every page of a query endpoint. */
    protected function query(Connection $c, string $path, array $body, string $acc): array
    {
        $out = [];
        for ($off = 0; $off < 20000; $off += 1000) {
            $r = $this->call($c, 'post', $path, $body + ['pagination' => ['offset' => $off, 'pageSize' => 1000, 'fetchTotalCount' => true]], $acc);
            $page = $r['data'] ?? $r['result'] ?? [];
            $page = isset($page['rows']) ? $page['rows'] : $page;
            $out = array_merge($out, array_values((array) $page));
            if (count($page) < 1000 || $off + 1000 >= (int) ($r['pagination']['totalCount'] ?? 0)) {
                break;
            }
        }

        return $out;
    }

    public function pull(AdAccount $account, string $from, string $to): array
    {
        $c = $account->connection ?: throw new RuntimeException('The Apple Ads connection is missing. Reconnect it.');
        $acc = (string) $account->external_id;

        $campaigns = [];
        foreach ($this->query($c, 'campaigns/query', [], $acc) as $x) {
            if (! empty($x['deleted'])) {
                continue;
            }
            $campaigns[(string) $x['id']] = ['external_id' => (string) $x['id'], 'name' => $x['name'] ?? null, 'status' => $x['displayStatus'] ?? ($x['status'] ?? null),
                'objective' => $x['promotedObjectType'] ?? null, 'daily_budget' => isset($x['dailyBudget']['value']['amount']) ? (float) $x['dailyBudget']['value']['amount'] : (isset($x['dailyBudgetAmount']['amount']) ? (float) $x['dailyBudgetAmount']['amount'] : null),
                'start_time' => $x['startTime'] ?? null, 'end_time' => $x['endTime'] ?? null];
        }

        $rows = [];
        foreach (static::windows($from, $to, 90) as [$f, $t]) {
            $data = $this->query($c, 'reports/apps/campaigns/query', ['timeRange' => ['start' => $f, 'end' => $t, 'timeZone' => 'ORTZ', 'granularity' => 'DAILY']], $acc);
            foreach ($data as $row) {
                $cid = (string) ($row['metadata']['campaignId'] ?? $row['metadata']['id'] ?? '');
                if ($cid === '') {
                    continue;
                }
                $campaigns[$cid] ??= ['external_id' => $cid, 'name' => $row['metadata']['campaignName'] ?? null];
                foreach ($row['granularMetrics'] ?? $row['granularity'] ?? [] as $g) {
                    $spend = (float) ($g['localSpend']['amount'] ?? 0);
                    if ($spend == 0 && empty($g['impressions'])) {
                        continue;
                    }
                    $installs = (float) ($g['totalInstalls'] ?? $g['tapInstalls'] ?? $g['installs'] ?? 0);
                    $rows[] = ['date' => substr((string) $g['date'], 0, 10), 'level' => 'campaign', 'campaign' => $cid]
                        + static::metrics(['spend' => $spend, 'impressions' => (int) ($g['impressions'] ?? 0), 'clicks' => (int) ($g['taps'] ?? 0),
                            'link_clicks' => (int) ($g['taps'] ?? 0)], 'app_install', $installs);
                }
            }
        }

        return ['campaigns' => array_values($campaigns), 'ad_sets' => [], 'ads' => [], 'rows' => $rows];
    }
}
