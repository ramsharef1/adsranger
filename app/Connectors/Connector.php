<?php

namespace App\Connectors;

use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Connection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Base class for an ad network connector (OAuth connect, account list, key test). */
abstract class Connector
{
    public function __construct(public AdNetwork $network) {}

    protected function creds(): array
    {
        return $this->network->credentials();
    }

    protected function cred(string $key, ?string $default = null): ?string
    {
        $v = $this->creds()[$key] ?? null;

        return filled($v) ? (string) $v : $default;
    }

    public function redirectUri(): string
    {
        return url('/app/connect/'.$this->network->key.'/callback');
    }

    /** 'oauth' = the user signs in on the network (platform app keys); 'keys' = the agency pastes its own API credentials. */
    public function connectMode(): string
    {
        return 'oauth';
    }

    /**
     * Fields an agency fills in to connect with its own API credentials ('keys' mode).
     * @return array<int, array{0:string,1:string,2:bool,3:string,4?:string,5?:bool}> [key, label, secret, help, type text|textarea, optional]
     */
    public function keyFields(): array
    {
        return [];
    }

    /** One line shown above the key form: where the agency finds its credentials. */
    public function keyHelp(): string
    {
        return '';
    }

    /** Row for networks that report at campaign-day level (native, retail). Conversions count as the result when the campaign has any. */
    protected static function campaignRow(string $date, string $campaign, array $m, bool $converts, ?float $conversions = null): array
    {
        $m['link_clicks'] ??= $m['clicks'] ?? 0;

        return ['date' => substr($date, 0, 10), 'level' => 'campaign', 'campaign' => $campaign]
            + static::metrics($m, $converts ? 'conversion' : 'link_click', $converts ? (float) $conversions : null);
    }

    /** True when "Connect" works for this network today. */
    public function supportsConnect(): bool
    {
        return false;
    }

    public function authorizeUrl(string $state): string
    {
        throw new RuntimeException('Connect is not available for this network yet.');
    }

    /** @return array{access_token:string,refresh_token:?string,expires_in:?int,user_id:?string,user_name:?string,scopes:?string} */
    public function exchange(string $code): array
    {
        throw new RuntimeException('Connect is not available for this network yet.');
    }

    /** @return array<int, array{id:string,name:?string,currency:?string,timezone:?string,status:?string}> */
    public function accounts(Connection $connection): array
    {
        return [];
    }

    /** True when this connector can pull structure + daily metrics. */
    public function supportsSync(): bool
    {
        return false;
    }

    /**
     * Pull structure and daily metrics for one ad account and date range.
     * @return array{campaigns:array,ad_sets:array,ads:array,rows:array}
     */
    public function pull(\App\Models\AdAccount $account, string $from, string $to): array
    {
        throw new RuntimeException($this->network->name.' sync is not available yet.');
    }

    /** Current access token, refreshed first when it is about to expire. */
    protected function token(Connection $c): string
    {
        if ($c->refresh_token && $c->token_expires_at && $c->token_expires_at->lt(now()->addMinutes(2))) {
            $t = $this->refresh((string) $c->refresh_token);
            $c->update(['access_token' => $t['access_token'], 'refresh_token' => $t['refresh_token'] ?? $c->refresh_token,
                'token_expires_at' => ! empty($t['expires_in']) ? now()->addSeconds((int) $t['expires_in'] - 60) : null]);
        }

        return $c->access_token ?: throw new RuntimeException($this->network->name.': no access token. Reconnect the account.');
    }

    /** @return array{access_token:string,refresh_token:?string,expires_in:?int} */
    protected function refresh(string $refreshToken): array
    {
        throw new RuntimeException($this->network->name.': the token expired. Reconnect the account.');
    }

    /** Standard OAuth2 refresh_token grant. */
    protected function oauthRefresh(string $url, string $refreshToken, array $extra = []): array
    {
        $res = Http::asForm()->timeout(20)->post($url, $extra + ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken,
            'client_id' => $this->cred('client_id'), 'client_secret' => $this->cred('client_secret')]);
        $tok = $res->json('access_token') ?: throw new RuntimeException($this->network->name.' refresh failed: '.($res->json('error_description') ?? $res->json('error') ?? 'HTTP '.$res->status()));

        return ['access_token' => $tok, 'refresh_token' => $res->json('refresh_token'), 'expires_in' => $res->json('expires_in')];
    }

    /** Split a date range into windows of at most $days days. @return array<int, array{0:string,1:string}> */
    protected static function windows(string $from, string $to, int $days): array
    {
        $out = [];
        $f = \Carbon\Carbon::parse($from);
        $end = \Carbon\Carbon::parse($to);
        while ($f->lte($end)) {
            $t = $f->copy()->addDays($days - 1)->min($end);
            $out[] = [$f->toDateString(), $t->toDateString()];
            $f = $t->copy()->addDay();
        }

        return $out;
    }

    /** A metrics row with every counter present. */
    protected static function metrics(array $m, string $type, ?float $results = null): array
    {
        $base = array_fill_keys(['impressions', 'reach', 'clicks', 'link_clicks'], 0)
            + array_fill_keys(['spend', 'purchases', 'leads', 'messages', 'calls', 'calls_confirmed', 'calls_20s', 'thruplays', 'landing_views', 'add_to_cart', 'checkouts', 'revenue'], 0.0);
        $m = array_merge($base, $m);
        $m['results'] = (float) ($results ?? match ($type) {
            'purchase' => $m['purchases'], 'lead' => $m['leads'], 'message' => $m['messages'], 'link_click' => $m['link_clicks'],
            'landing_page_view' => $m['landing_views'], 'thruplay' => $m['thruplays'], 'reach' => $m['reach'], 'impressions' => $m['impressions'],
            'conversion' => $m['purchases'] + $m['leads'], default => 0,
        });
        $m['result_type'] = $type;
        $m['roas_eligible'] = in_array($type, ['purchase', 'conversion'], true) && $m['revenue'] > 0;

        return $m;
    }

    /**
     * Problems with an ad account (disabled, payment, restrictions, rejected ads).
     * @return array<int, array{0:string,1:string}> [severity critical|warning, message] (D-88)
     */
    public function health(AdAccount $account): array
    {
        if (! $account->connection || ! $this->supportsSync()) {
            return [];
        }
        foreach ($this->accounts($account->connection) as $a) {
            if ((string) $a['id'] === (string) $account->external_id) {
                $st = strtoupper((string) ($a['status'] ?? ''));
                if ($st && preg_match('/DISABLE|SUSPEND|CLOSED|CANCEL|DELETE|PAUSED_BY|LIMIT|RESTRICT|PENDING|FRAUD|DENY/', $st)) {
                    return [['critical', 'Account status on '.$this->network->name.': '.str_replace('_', ' ', strtolower($st)).'.']];
                }

                return [];
            }
        }

        return [['critical', 'The account is no longer reachable with this connection. It may have been removed or access was revoked.']];
    }

    /** Check the saved keys without a user. @return array{0:?bool,1:string} [ok (null = cannot test), message] */
    abstract public function test(): array;

    /**
     * Generic OAuth client check: send a fake authorization code. A rejected *code* (invalid_grant)
     * proves the client ID and secret are valid; a rejected *client* (invalid_client) means they are wrong.
     */
    protected function oauthClientTest(string $tokenUrl, array $extra = [], string $idKey = 'client_id', string $secretKey = 'client_secret'): array
    {
        $id = $this->cred($idKey);
        $secret = $this->cred($secretKey);
        if (! $id || ! $secret) {
            return [false, 'Client ID or secret is missing.'];
        }
        try {
            $res = Http::asForm()->timeout(15)->post($tokenUrl, $extra + [
                'grant_type' => 'authorization_code',
                'code' => 'adsranger-key-test',
                'redirect_uri' => $this->redirectUri(),
                'client_id' => $id,
                'client_secret' => $secret,
            ]);
        } catch (\Throwable $e) {
            return [false, 'Could not reach the network: '.$e->getMessage()];
        }
        $err = strtolower((string) ($res->json('error') ?? ''));
        $desc = (string) ($res->json('error_description') ?? $res->json('message') ?? '');
        if (in_array($err, ['invalid_client', 'unauthorized_client'], true) || str_contains(strtolower($desc), 'client secret') || str_contains(strtolower($desc), 'invalid client')) {
            return [false, 'The network rejected the client ID or secret. '.mb_substr($desc, 0, 160)];
        }
        if (in_array($err, ['invalid_grant', 'invalid_request', 'bad_verification_code'], true)) {
            return [true, 'Keys accepted (the test code was rejected, as expected).'];
        }

        return [null, 'Unclear answer from the network (HTTP '.$res->status().'): '.mb_substr($err.' '.$desc, 0, 160)];
    }
}
