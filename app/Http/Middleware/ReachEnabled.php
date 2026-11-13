<?php

namespace App\Http\Middleware;

use App\Support\Reach\Reach;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** FR-REACH: every /reach route answers 404 while the `reach` module is off, and is never indexed. */
class ReachEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Reach::publicOn(), 404);

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
