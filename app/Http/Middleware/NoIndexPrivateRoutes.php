<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends `X-Robots-Tag: noindex, nofollow` on every private or utility route (panels, invites, OAuth connect,
 * Filament downloads, Livewire, storage, health check), so search engines never index them even if linked.
 * Public pages (home, legal, later the Phase 1 site) are left alone; Phase 1 decides their indexing (ADSR-040 §2).
 */
class NoIndexPrivateRoutes
{
    /** Path prefixes that are never indexable (no leading slash). */
    public const PRIVATE_PREFIXES = ['app', 'admin', 'invite', 'filament', 'livewire', 'storage', 'up'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (static::isPrivate($request->path())) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }

    public static function isPrivate(string $path): bool
    {
        $first = strtok(trim($path, '/'), '/') ?: '';

        foreach (static::PRIVATE_PREFIXES as $prefix) {
            if ($first === $prefix || str_starts_with($first, $prefix.'-')) {
                return true;
            }
        }

        return false;
    }
}
