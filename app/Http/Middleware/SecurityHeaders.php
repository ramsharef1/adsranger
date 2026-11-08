<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every response (Website W2, 2026-11-08). The CSP is deliberately narrow
 * (framing, base URI, plugins, mixed content) so it can't break Filament, Livewire or the public pages'
 * inline styles; a full script/style policy comes later with nonces. HSTS is sent only over HTTPS and
 * without includeSubDomains, so agency custom domains and other subdomains are not affected.
 */
class SecurityHeaders
{
    /** @var array<string, string> */
    public const HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; upgrade-insecure-requests",
    ];

    public const HSTS = 'max-age=31536000';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (static::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if ($request->isSecure() || app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', static::HSTS);
        }

        return $response;
    }
}
