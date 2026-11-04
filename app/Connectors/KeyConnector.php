<?php

namespace App\Connectors;

use App\Models\Connection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Networks where each agency connects with its own API credentials (no platform app, no sign-in redirect).
 * The credentials are stored encrypted on the connection; short-lived tokens are fetched from them when needed.
 */
abstract class KeyConnector extends Connector
{
    public function connectMode(): string
    {
        return 'keys';
    }

    public function supportsConnect(): bool
    {
        return true;
    }

    public function supportsSync(): bool
    {
        return true;
    }

    /** @return array{token:?string, expires_in:?int} */
    abstract protected function fetchToken(array $creds): array;

    /** Who the credentials belong to. @return array{0:?string,1:?string} [external user id, display name] */
    protected function identify(array $creds, ?string $token): array
    {
        return [null, null];
    }

    /** Check pasted credentials. @return array{credentials:array, access_token:?string, expires_in:?int, user_id:?string, user_name:?string} */
    public function connectWithKeys(array $input): array
    {
        $creds = [];
        foreach ($this->keyFields() as $f) {
            $v = trim((string) ($input[$f[0]] ?? ''));
            if ($v === '' && empty($f[5])) {
                throw new RuntimeException($f[1].' is required.');
            }
            if ($v !== '') {
                $creds[$f[0]] = $v;
            }
        }
        $t = $this->fetchToken($creds);
        [$id, $name] = $this->identify($creds, $t['token']);

        return ['credentials' => $creds, 'access_token' => $t['token'], 'expires_in' => $t['expires_in'], 'user_id' => $id, 'user_name' => $name];
    }

    protected function token(Connection $c): string
    {
        if ($c->access_token && (! $c->token_expires_at || $c->token_expires_at->gt(now()->addMinutes(2)))) {
            return $c->access_token;
        }
        $creds = (array) ($c->credentials ?? []);
        if (! $creds) {
            throw new RuntimeException($this->network->name.': the API credentials are missing. Reconnect the account.');
        }
        $t = $this->fetchToken($creds);
        $c->update(['access_token' => $t['token'], 'token_expires_at' => $t['expires_in'] ? now()->addSeconds(max(60, (int) $t['expires_in'] - 60)) : null]);

        return (string) $t['token'];
    }

    /** HTTP client that waits and retries on rate limits (429) and brief server errors. */
    protected static function client(int $timeout = 90): \Illuminate\Http\Client\PendingRequest
    {
        return Http::acceptJson()->timeout($timeout)->retry(3,
            fn (int $n, $e) => min(30, (int) (($e->response ?? null)?->header('Retry-After') ?: 2 * $n)) * 1000,
            fn ($e) => in_array(($e->response ?? null)?->status(), [429, 500, 502, 503], true), throw: false);
    }

    /** Readable error from a failed call. */
    protected function fail(Response $res, string $what): RuntimeException
    {
        $msg = $res->json('message') ?? $res->json('error_description') ?? $res->json('error.message') ?? $res->json('error') ?? $res->json('errors.0.message') ?? null;
        if (is_array($msg)) {
            $msg = json_encode($msg);
        }

        return new RuntimeException($this->network->name.' '.$what.': '.mb_substr((string) ($msg ?: 'HTTP '.$res->status()), 0, 200));
    }

    public function test(): array
    {
        return [null, 'Each agency connects '.$this->network->name.' with its own API credentials, so there are no platform keys to test.'];
    }
}
