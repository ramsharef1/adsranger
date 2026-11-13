<?php

namespace App\Jobs;

use App\Models\AgencyWebhook;
use App\Support\Ops\Lanes;
use App\Support\Webhooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * #77 / #115 Sends one event to one webhook. Signed with the webhook's own signing secret (never the feed token):
 * X-AdsRanger-Signature = sha256 HMAC of "<timestamp>.<body>". The target is re-checked and its IP pinned; redirects
 * are not followed. After 20 failures in a row the webhook is switched off.
 */
class DeliverAgencyWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const MAX_FAILURES = 20;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $webhookId, public string $event, public array $data)
    {
        $this->onQueue(Lanes::for(self::class));
    }

    public function handle(): void
    {
        $hook = AgencyWebhook::find($this->webhookId);
        if (! $hook || ! $hook->is_active || ! $hook->signing_secret) {
            return;
        }
        if ($problem = Webhooks::targetProblem($hook->target_url)) {
            $this->failed_($hook, null, $problem);

            return;
        }
        $host = parse_url($hook->target_url, PHP_URL_HOST);
        $ip = Webhooks::resolve($host);
        $body = json_encode(['event' => $this->event, 'sent_at' => now()->toIso8601String(), 'data' => $this->data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $ts = (string) now()->timestamp;

        try {
            $res = Http::withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => ["$host:443:".(str_contains($ip, ':') ? "[$ip]" : $ip)]]])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'AdsRanger-Webhooks/1.0',
                    'X-AdsRanger-Event' => $this->event,
                    'X-AdsRanger-Timestamp' => $ts,
                    'X-AdsRanger-Signature' => 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $hook->signing_secret),
                ])
                ->withBody($body, 'application/json')->timeout(10)->connectTimeout(5)->post($hook->target_url);
        } catch (\Throwable $e) {
            $this->failed_($hook, null, Str::limit($e->getMessage(), 280));
            throw $e;
        }

        if ($res->successful()) {
            $hook->forceFill(['last_triggered_at' => now(), 'last_status' => $res->status(), 'last_error' => null, 'failures' => 0])->save();

            return;
        }
        $this->failed_($hook, $res->status(), 'HTTP '.$res->status());
        if ($res->serverError()) {
            throw new \RuntimeException('Webhook target answered HTTP '.$res->status().'; retrying.');
        }
    }

    protected function failed_(AgencyWebhook $hook, ?int $status, string $error): void
    {
        $failures = $hook->failures + 1;
        $hook->forceFill(['last_status' => $status, 'last_error' => $error, 'failures' => $failures,
            'is_active' => $failures < self::MAX_FAILURES && $hook->is_active])->save();
    }
}
