<?php

namespace App\Http\Middleware;

use App\Support\Seo\ToolGuard;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** BZ-10: refuse blocked visitors / hosts on the free URL tools, and count limit hits and unreadable hosts. */
class ToolAbuseGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $tool = (string) ($request->segment(2) ?? 'tool');
        $who = ToolGuard::who($request);
        $host = ToolGuard::host($request->input('url'));

        if (ToolGuard::refuses($who, $host)) {
            ToolGuard::recordRefusal($tool);

            // Same reply as the daily limit, so the page shows its usual "try again tomorrow" line.
            return response()->json(['error' => 'limit'], 429);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 429) {
            ToolGuard::recordLimitHit($tool, $who);
        } elseif ($response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data) && ($data['ok'] ?? null) === false && in_array($data['error'] ?? null, ['fetch_failed', 'not_200', 'empty', 'blocked'], true)) {
                ToolGuard::recordFailure($tool, $host);
            }
        }

        return $response;
    }
}
