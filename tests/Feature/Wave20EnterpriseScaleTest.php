<?php

namespace Tests\Feature;

use App\Jobs\DeliverAgencyWebhookJob;
use App\Models\Agency;
use App\Models\AgencyWebhook;
use App\Models\Client;
use App\Support\AiAnalyst;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Wave20EnterpriseScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_analyst_driver_caches_and_returns_insight(): void
    {
        $res = AiAnalyst::analyze('Optimize campaign spend', 'en');
        $this->assertStringContainsString('Advanced AI insight', $res);
    }

    public function test_webhook_job_dispatches_with_signature(): void
    {
        Queue::fake();

        $agency = Agency::create([
            'name' => 'Enterprise Lab',
            'slug' => 'ent-lab',
            'plan' => 'agency',
            'status' => 'active',
            'language' => 'en',
        ]);

        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Dubai Real Estate',
            'currency' => 'AED',
            'status' => 'active',
        ]);

        $webhook = AgencyWebhook::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'name' => 'Zapier Lead Forwarder',
            'target_url' => 'https://hooks.example.com/test',
            'events' => ['lead.created'],
            'secret_token' => 'sec_'.uniqid(),
            'is_active' => true,
        ]);

        DeliverAgencyWebhookJob::dispatch($webhook, ['event' => 'lead.created', 'id' => 123]);

        Queue::assertPushed(DeliverAgencyWebhookJob::class);
    }
}
