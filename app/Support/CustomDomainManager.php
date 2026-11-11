<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AuditLog;

class CustomDomainManager
{
    public static function cnameTarget(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'adsranger.com';
    }

    public static function normalizeHost(string $domain): string
    {
        $clean = strtolower(trim($domain));
        $clean = preg_replace('#^https?://#', '', $clean) ?? $clean;

        return rtrim(explode('/', $clean)[0], '.');
    }

    /** @return array{record_type: string, example_host: string, target: string, status: string, verified_at: ?string} */
    public static function instructions(Agency $agency): array
    {
        return [
            'record_type' => 'CNAME',
            'example_host' => $agency->custom_domain ?: 'reports.agency.com',
            'target' => static::cnameTarget(),
            'status' => $agency->custom_domain ? ($agency->custom_domain_status ?: 'pending') : 'not_configured',
            'verified_at' => $agency->custom_domain_verified_at?->format('Y-m-d H:i'),
        ];
    }

    public static function configure(Agency $agency, ?string $domain): Agency
    {
        $host = static::normalizeHost((string) $domain);

        if ($host === '') {
            $agency->forceFill([
                'custom_domain' => null,
                'custom_domain_status' => 'pending',
                'custom_domain_verified_at' => null,
            ])->save();

            return $agency->refresh();
        }

        if ($agency->custom_domain !== $host) {
            $agency->forceFill([
                'custom_domain' => $host,
                'custom_domain_status' => 'pending',
                'custom_domain_verified_at' => null,
            ])->save();
            AuditLog::record('agency.custom_domain_configured', $agency, ['domain' => $host]);
        }

        return $agency->refresh();
    }

    public static function activate(Agency $agency): Agency
    {
        $agency->forceFill([
            'custom_domain_status' => 'active',
            'custom_domain_verified_at' => now(),
        ])->save();

        return $agency->refresh();
    }

    /**
     * M6.1: Honest DNS CNAME check (D-229: only marks active if DNS CNAME actually points to adsranger.com).
     *
     * @return array{ok: bool, status: string, target_found: ?string}
     */
    public static function verifyDns(Agency $agency, ?callable $resolver = null): array
    {
        $host = (string) $agency->custom_domain;
        if ($host === '') {
            return ['ok' => false, 'status' => 'not_configured', 'target_found' => null];
        }

        $records = $resolver ? $resolver($host) : (@dns_get_record($host, DNS_CNAME) ?: []);
        $expected = static::cnameTarget();

        foreach ((array) $records as $row) {
            $found = static::normalizeHost((string) ($row['target'] ?? ''));
            if ($found === $expected || str_ends_with($found, '.'.$expected)) {
                static::activate($agency);

                return ['ok' => true, 'status' => 'active', 'target_found' => $found];
            }
        }

        return [
            'ok' => false,
            'status' => $agency->custom_domain_status ?: 'pending',
            'target_found' => isset($records[0]['target']) ? static::normalizeHost((string) $records[0]['target']) : null,
        ];
    }

    public static function resolveActiveAgency(string $host): ?Agency
    {
        $normalized = self::normalizeHost($host);
        $rootDomain = static::cnameTarget();

        if ($normalized === '' || $normalized === $rootDomain || str_ends_with($normalized, '.'.$rootDomain)) {
            return null;
        }

        return Agency::query()
            ->where('custom_domain', $normalized)
            ->where('custom_domain_status', 'active')
            ->first();
    }
}
