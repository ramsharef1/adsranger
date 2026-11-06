<?php

namespace App\Support;

use App\Models\Agency;

class CustomDomainManager
{
    public static function normalizeHost(string $domain): string
    {
        $clean = strtolower(trim($domain));
        $clean = preg_replace('#^https?://#', '', $clean) ?? $clean;
        return rtrim(explode('/', $clean)[0], '.');
    }

    public static function configure(Agency $agency, string $domain): Agency
    {
        $host = self::normalizeHost($domain);

        $agency->forceFill([
            'custom_domain' => $host,
            'custom_domain_status' => 'pending',
            'custom_domain_verified_at' => null,
        ])->save();

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

    public static function resolveActiveAgency(string $host): ?Agency
    {
        $normalized = self::normalizeHost($host);
        $rootDomain = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'adsranger.com';

        if ($normalized === '' || $normalized === $rootDomain || str_ends_with($normalized, '.' . $rootDomain)) {
            return null;
        }

        return Agency::query()
            ->where('custom_domain', $normalized)
            ->where('custom_domain_status', 'active')
            ->first();
    }
}