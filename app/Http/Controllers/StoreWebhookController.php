<?php

namespace App\Http\Controllers;

use App\Models\ClientStoreConnection;
use App\Support\Attribution\ProfitAndAttributionManager;
use App\Support\Attribution\StoreWebhooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * E2-063: server-to-server receiver for Salla, Zid and Shopify webhooks (/webhooks/stores/{token}, CSRF-exempt).
 * 404 for an unknown token or while intelligence_hub is off for the workspace; 401 when the signature doesn't verify
 * (nothing is stored); 200 with duplicate=true for a replay; 202 while the connection is paused.
 */
class StoreWebhookController extends Controller
{
    public function receive(Request $request, string $token): JsonResponse
    {
        $connection = ClientStoreConnection::with(['agency', 'client'])->where('webhook_token', $token)->first();
        abort_unless($connection && $connection->client && $connection->client->status !== 'archived' && ProfitAndAttributionManager::moduleLiveFor($connection->agency), 404);

        $raw = $request->getContent();
        if (strlen($raw) > (int) config('store_sync.max_body_kb', 512) * 1024) {
            return $this->answer(['ok' => false, 'error' => 'too_large'], 413);
        }
        $header = (string) config("store_sync.platforms.{$connection->platform}.header");
        if (! StoreWebhooks::verify($connection->platform, $raw, $request->header($header), (string) $connection->webhook_secret)) {
            return $this->answer(['ok' => false, 'error' => 'bad_signature'], 401);
        }
        if ($connection->status === 'paused') {
            return $this->answer(['ok' => true, 'paused' => true], 202);
        }
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return $this->answer(['ok' => false, 'error' => 'bad_json'], 422);
        }

        $headers = array_map(fn ($v) => is_array($v) ? ($v[0] ?? null) : $v, array_change_key_case($request->headers->all(), CASE_LOWER));
        $event = StoreWebhooks::normalise($connection->platform, $payload, $headers, $raw);
        if (StoreWebhooks::tooOld($event)) {
            return $this->answer(['ok' => false, 'error' => 'stale'], 409);
        }
        $fresh = StoreWebhooks::apply($connection, $event);

        return $this->answer(['ok' => true, 'duplicate' => ! $fresh, 'type' => $event['type']], 200);
    }

    /** @param  array<string, mixed>  $body */
    private function answer(array $body, int $status): JsonResponse
    {
        return response()->json($body, $status)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }
}
