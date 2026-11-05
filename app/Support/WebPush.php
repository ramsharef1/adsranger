<?php

namespace App\Support;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * #175 Web Push without a third-party package: VAPID (RFC 8292, ES256) + aes128gcm payload encryption (RFC 8291).
 * The VAPID key pair is made on first use on the server and kept in storage/app/private/vapid.json (0600, not in git);
 * only the public key ever leaves the server (the browser needs it to subscribe).
 */
class WebPush
{
    private const SPKI_P256_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function keyPath(): string
    {
        return config('push.vapid_path') ?: storage_path('app/private/vapid.json');
    }

    /** @return array{public: string, private_pem: string} public = base64url raw 65-byte key */
    public static function vapid(): array
    {
        $path = static::keyPath();
        if (is_file($path)) {
            $k = json_decode((string) file_get_contents($path), true);
            if (! empty($k['public']) && ! empty($k['private_pem'])) {
                return $k;
            }
        }
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (! $key) {
            throw new RuntimeException('Could not create the VAPID key.');
        }
        openssl_pkey_export($key, $pem);
        $k = ['public' => static::b64(static::rawPublic($key)), 'private_pem' => $pem];
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, json_encode($k));
        @chmod($path, 0600);

        return $k;
    }

    public static function publicKey(): string
    {
        return static::vapid()['public'];
    }

    /**
     * Send one push. Returns the HTTP status (201 = accepted). 404 / 410 delete the subscription (the browser dropped it).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function send(PushSubscription $sub, array $payload, int $ttl = 86400): int
    {
        $body = static::encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), static::unb64($sub->p256dh), static::unb64($sub->auth));
        $parts = parse_url($sub->endpoint);
        $aud = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $res = Http::timeout(10)->withHeaders([
            'Authorization' => 'vapid t='.static::jwt($aud).', k='.static::publicKey(),
            'Content-Encoding' => 'aes128gcm',
            'TTL' => (string) $ttl,
            'Urgency' => 'high',
        ])->withBody($body, 'application/octet-stream')->post($sub->endpoint);
        $status = $res->status();
        if (in_array($status, [404, 410], true)) {
            $sub->delete();
        } elseif ($res->successful()) {
            $sub->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $status;
    }

    /** RFC 8291 aes128gcm: salt(16) | rs(4) | idlen(1) | keyid(as_public) | ciphertext+tag. */
    public static function encrypt(string $plain, string $uaPublic, string $authSecret, ?string $salt = null, $asKey = null): string
    {
        if (strlen($uaPublic) !== 65 || strlen($authSecret) !== 16) {
            throw new RuntimeException('Bad subscription keys.');
        }
        $asKey = $asKey ?: openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $asPublic = static::rawPublic($asKey);
        $ecdh = openssl_pkey_derive(static::publicKeyFromRaw($uaPublic), $asKey, 32);
        if ($ecdh === false) {
            throw new RuntimeException('Key agreement failed.');
        }
        $salt = $salt ?? random_bytes(16);
        $prkKey = hash_hmac('sha256', $ecdh, $authSecret, true);
        $ikm = hash_hmac('sha256', "WebPush: info\x00".$uaPublic.$asPublic."\x01", $prkKey, true);
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = substr(hash_hmac('sha256', "Content-Encoding: aes128gcm\x00\x01", $prk, true), 0, 16);
        $nonce = substr(hash_hmac('sha256', "Content-Encoding: nonce\x00\x01", $prk, true), 0, 12);
        $cipher = openssl_encrypt($plain."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);

        return $salt.pack('N', 4096).chr(65).$asPublic.$cipher.$tag;
    }

    public static function jwt(string $aud): string
    {
        $head = static::b64(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = static::b64(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => config('push.subject', 'mailto:support@adsranger.com')]));
        openssl_sign($head.'.'.$claims, $der, openssl_pkey_get_private(static::vapid()['private_pem']), OPENSSL_ALGO_SHA256);

        return $head.'.'.$claims.'.'.static::b64(static::derToRaw($der));
    }

    public static function rawPublic($key): string
    {
        $d = openssl_pkey_get_details($key)['ec'];

        return "\x04".str_pad($d['x'], 32, "\x00", STR_PAD_LEFT).str_pad($d['y'], 32, "\x00", STR_PAD_LEFT);
    }

    public static function publicKeyFromRaw(string $raw)
    {
        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode(hex2bin(self::SPKI_P256_PREFIX).$raw), 64, "\n")."-----END PUBLIC KEY-----\n";

        return openssl_pkey_get_public($pem);
    }

    /** ECDSA DER signature → raw r|s (64 bytes) as JWS ES256 needs. */
    public static function derToRaw(string $der): string
    {
        $pos = 2; // SEQUENCE tag + length (signatures are always < 128 bytes)
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $out .= str_pad(ltrim($int, "\x00"), 32, "\x00", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $out;
    }

    public static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function unb64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
