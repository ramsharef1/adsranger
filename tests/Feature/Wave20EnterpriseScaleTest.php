<?php

namespace Tests\Feature;

use App\Jobs\DeliverAgencyWebhookJob;
use App\Models\Agency;
use App\Models\AgencyWebhook;
use App\Models\Client;
use App\Support\AiAnalyst;
use App\Support\Webhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class Wave20EnterpriseScaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_analyst_driver_caches_and_returns_insight(): void
    {
        $res = AiAnalyst::analyze('Optimize campaign spend', 'en');
        $this->assertStringContainsString('no AI provider is connected yet', $res);
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

        config(['modules.enabled.webhooks' => true]);
        [$webhook] = AgencyWebhook::issue([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'name' => 'Zapier Alert Forwarder',
            'target_url' => 'https://hooks.example.com/test',
            'events' => ['alert.critical'],
        ]);

        $this->assertSame(1, Webhooks::fire($agency, 'alert.critical', ['alert_id' => 123], $client->id));

        Queue::assertPushed(DeliverAgencyWebhookJob::class);
    }
}
