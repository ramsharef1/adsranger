<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GR-01 / GR-07 (D-289): on public pages, remember in the visitor's own session the first page they landed on, the UTM
 * tags of that visit, the last free tool they opened and a colleague's share code (?ref=). Saved with a waitlist sign-up
 * only; no IP, no device ID, no cookie beyond the session.
 */
class TrackOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->hasSession()) {
            $s = $request->session();
            if (! $s->has('origin.landing')) {
                $s->put('origin.landing', mb_substr('/'.ltrim($request->path(), '/'), 0, 300));
                foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $k) {
                    if (is_string($v = $request->query($k)) && $v !== '') {
                        $s->put('origin.'.$k, mb_substr($v, 0, 80));
                    }
                }
            }
            if (preg_match('~^(?:en/)?tools/([a-z0-9-]+)$~', $request->path(), $m)) {
                $s->put('origin.tool', $m[1]);
            }
            if (is_string($ref = $request->query('ref')) && preg_match('/^[A-Za-z0-9]{6,12}$/', $ref)) {
                $s->put('origin.ref', $ref);
            }
        }

        return $next($request);
    }
}
