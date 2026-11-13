<?php

namespace App\Http\Middleware;

use App\Support\AiWorkspace\Connector;
use App\Support\Modules;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI Workspace connector guard (spec v1 §16.4): a live connection for this token, the module on for its workspace,
 * payload size, per-minute and per-day call caps. Runs after `auth:api`.
 */
class AiConnectorGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');
        $connection = Connector::forToken($user);
        if (! $connection) {
            return $this->refuse(403, 'This AI is not connected to AdsRanger. Connect it again from AI Workspace › Connections.');
        }
        if (! Modules::on('ai_workspace', $user, $connection->agency)) {
            return $this->refuse(403, 'AI Workspace is not available for this workspace yet.');
        }
        if (! $connection->agency->members()->whereKey($user->id)->exists()) {
            $connection->revoke();

            return $this->refuse(403, 'You are no longer a member of this workspace.');
        }
        if ($connection->status === 'paused') {
            return $this->refuse(403, 'This connection is paused for unusual activity. The workspace owner can restore it.');
        }
        if (strlen((string) $request->getContent()) > (int) config('ai_workspace.max_payload_kb', 64) * 1024) {
            return $this->refuse(413, 'Request too large.');
        }

        $perMinute = (int) config('ai_workspace.calls_per_minute', 30);
        $minuteKey = 'ai-conn-minute:'.$connection->id;
        if (RateLimiter::tooManyAttempts($minuteKey, $perMinute)) {
            Connector::anomaly($connection, 'bursts', 'Too many calls in a short time.');

            return $this->refuse(429, 'Too many requests. Try again in a minute.', ['Retry-After' => (string) RateLimiter::availableIn($minuteKey)]);
        }
        RateLimiter::hit($minuteKey, 60);

        $today = now($connection->agency->timezone ?: 'UTC')->toDateString();
        $calls = $connection->calls_day?->toDateString() === $today ? $connection->calls_today : 0;
        if ($this->isToolCall($request) && $calls >= (int) config('ai_workspace.calls_per_day', 200)) {
            return $this->refuse(429, 'Daily limit reached for this AI connection. It resets at midnight, workspace time.');
        }
        $connection->forceFill(['last_call_at' => now(), 'calls_day' => $today, 'calls_today' => $calls + ($this->isToolCall($request) ? 1 : 0)])->save();
        $request->attributes->set('ai_connection', $connection);

        return $next($request);
    }

    protected function isToolCall(Request $request): bool
    {
        return $request->json('method') === 'tools/call';
    }

    /** @param array<string, string> $headers */
    protected function refuse(int $status, string $message, array $headers = []): Response
    {
        return response()->json(['error' => $message], $status, $headers);
    }
}
