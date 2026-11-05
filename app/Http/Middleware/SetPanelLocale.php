<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel language (UX-3, EX-03): Arabic first. Order: ?lang= (stored in the session) → the session →
 * the page the visitor came from on our site (/en… → English, otherwise Arabic) → Arabic.
 * Filament takes the text direction from the locale, so Arabic renders RTL.
 */
class SetPanelLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = static::resolve($request);
        app()->setLocale($locale);

        return $next($request);
    }

    public static function resolve(Request $request): string
    {
        $asked = $request->query('lang');
        if (is_string($asked) && in_array($asked, static::SUPPORTED, true)) {
            $request->session()->put('locale', $asked);

            return $asked;
        }

        $stored = $request->session()->get('locale');
        if (is_string($stored) && in_array($stored, static::SUPPORTED, true)) {
            return $stored;
        }

        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            $path = '/'.ltrim((string) parse_url($referer, PHP_URL_PATH), '/');
            $locale = ($path === '/en' || str_starts_with($path, '/en/')) ? 'en' : 'ar';
            $request->session()->put('locale', $locale);

            return $locale;
        }

        return config('adsranger.panel_locale', 'ar');
    }
}
