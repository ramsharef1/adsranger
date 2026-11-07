<?php

namespace App\Jobs;

use App\Models\AgencyWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverAgencyWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public AgencyWebhook $webhook,
        public array $payload
    ) {}

    public function handle(): void
    {
        if (! $this->webhook->is_active) {
            return;
        }

        $body = json_encode($this->payload);
        $signature = hash_hmac('sha256', $body, $this->webhook->secret_token);

        $response = Http::withHeaders([
            'X-AdsRanger-Signature' => 'sha256='.$signature,
            'Content-Type' => 'application/json',
        ])->timeout(10)->post($this->webhook->target_url, $this->payload);

        if ($response->successful()) {
            $this->webhook->update(['last_triggered_at' => now()]);
        }
    }
}
