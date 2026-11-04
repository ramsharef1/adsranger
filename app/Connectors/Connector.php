<?php

namespace App\Connectors;

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
