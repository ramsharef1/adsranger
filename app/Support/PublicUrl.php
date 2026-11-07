<?php

namespace App\Support;

/**
 * Public-site URLs (ADSR-040 §1, P-0, D-05 / D-06 / D-152): Arabic at the root, English under /en; the English home is
 * the only trailing-slash URL (`/en/`). Every page has a self canonical and ar / en / x-default (→ Arabic) alternates.
 */
class PublicUrl
{
    public const HOST = 'https://adsranger.com';

    /** Path of a page in one language, from its Arabic path ('/' for home): '/pricing' → '/en/pricing'; '/' → '/en/'. */
    public static function path(string $arPath, string $lang): string
    {
        $arPath = '/'.ltrim($arPath, '/');
        if ($lang !== 'en') {
            return $arPath;
        }

        return $arPath === '/' ? '/en/' : '/en'.$arPath;
    }

    public static function absolute(string $arPath, string $lang): string
    {
        return static::HOST.static::path($arPath, $lang);
    }

    /**
     * The Arabic path of the current request ('/en/pricing' → '/pricing', '/en/' → '/').
     */
    public static function arPathOf(string $requestPath): string
    {
        $p = '/'.trim($requestPath, '/');
        if ($p === '/en') {
            return '/';
        }

        return str_starts_with($p, '/en/') ? substr($p, 3) : $p;
    }
}
