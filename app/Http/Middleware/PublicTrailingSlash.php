<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One trailing-slash rule on the public site (ADSR-040 §1, R-14, D-152): `/en/` is the only URL with a trailing slash;
 * `/en` and any other public URL ending in "/" get a 301 to the single form, keeping the query string.
 */
class PublicTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }
        $path = $request->getPathInfo();
        $target = null;
        if ($path === '/en') {
            $target = '/en/';
        } elseif ($path !== '/' && $path !== '/en/' && str_ends_with($path, '/')) {
            $target = rtrim($path, '/');
        }
        if ($target === null) {
            return $next($request);
        }
        $query = $request->getQueryString();

        // A plain RedirectResponse: the URL generator would strip the slash from /en/.
        return new RedirectResponse($target.($query ? '?'.$query : ''), 301);
    }
}
