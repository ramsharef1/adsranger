<?php

namespace App\Support\Ops;

use Illuminate\Support\Carbon;
use Throwable;

/** EG-14: days until each of our HTTPS certificates expires (certbot renews; this notices when renewal fails). */
class CertExpiry
{
    /** @var (\Closure(string): ?Carbon)|null tests swap the reader */
    public static ?\Closure $reader = null;

    /** @return array<string, ?int> host → days left (null = could not read) */
    public static function days(): array
    {
        $out = [];
        foreach ((array) config('ops.cert_hosts') as $host) {
            $until = (self::$reader ?? fn (string $h) => self::read($h))($host);
            $out[$host] = $until ? (int) floor(now()->diffInDays($until, false)) : null;
        }

        return $out;
    }

    public static function read(string $host): ?Carbon
    {
        try {
            $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
            $c = @stream_socket_client('ssl://'.$host.':443', $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx);
            if (! $c) {
                return null;
            }
            $cert = stream_context_get_params($c)['options']['ssl']['peer_certificate'] ?? null;
            fclose($c);
            $info = $cert ? openssl_x509_parse($cert) : null;

            return isset($info['validTo_time_t']) ? Carbon::createFromTimestamp($info['validTo_time_t']) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    public static function problems(): array
    {
        $warn = (int) config('ops.cert_warn_days', 14);
        $p = [];
        foreach (self::days() as $host => $days) {
            if ($days === null) {
                $p[] = __('Could not read the HTTPS certificate of :host.', ['host' => $host]);
            } elseif ($days < $warn) {
                $p[] = __('The HTTPS certificate of :host expires in :n days.', ['host' => $host, 'n' => $days]);
            }
        }

        return $p;
    }
}
