<?php

namespace Tests\Feature;

use App\Alerts\AlertEngine;
use App\Filament\Pages\WebhooksPage;
use App\Jobs\DeliverAgencyWebhookJob;
use App\Models\Agency;
use App\Models\AgencyWebhook;
use App\Models\Client;
use App\Models\User;
use App\Support\Webhooks;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** #77 / #115 webhook fixes: separate secrets, SSRF guard, delivery actually runs, owners / admins only. */
class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function agency(string $role = 'owner'): array
    {
        config(['modules.enabled.webhooks' => true]);
        $a = Agency::create(['name' => 'Hook Lab', 'slug' => 'hook-lab-'.$role, 'plan' => 'agency', 'status' => 'active', 'currency' => 'USD']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => $role]);
        $c = Client::create(['agency_id' => $a->id, 'name' => 'Acme Client', 'currency' => 'USD', 'status' => 'active']);

        return [$a, $u, $c];
    }

    private function page(Agency $a, User $u)
    {
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        return Livewire::test(WebhooksPage::class);
    }

    public function test_targets_must_be_public_https(): void
    {
        Webhooks::$resolver = fn (string $host) => $host === 'hooks.example.com' ? '93.184.216.34' : null;

        $this->assertNull(Webhooks::targetProblem('https://hooks.example.com/in'));
        $this->assertNotNull(Webhooks::targetProblem('http://hooks.example.com/in'));
        $this->assertNotNull(Webhooks::targetProblem('https://hooks.example.com:8443/in'));
        $this->assertNotNull(Webhooks::targetProblem('https://user:pw@hooks.example.com/in'));
        $this->assertNotNull(Webhooks::targetProblem('https://internal.local/in')); // resolves to nothing public
        Webhooks::$resolver = null;
        $this->assertNotNull(Webhooks::targetProblem('https://127.0.0.1/in'));
        $this->assertNotNull(Webhooks::targetProblem('https://169.254.169.254/latest/meta-data'));
    }

    public function test_only_owners_and_admins_create_and_the_signing_secret_is_not_the_feed_token(): void
    {
        Webhooks::$resolver = fn () => '93.184.216.34';
        [$a, $owner] = $this->agency();

        $this->page($a, $owner)->set('webhookName', 'Zapier')->set('webhookTargetUrl', 'http://hooks.example.com/x')->call('createWebhook');
        $this->assertSame(0, AgencyWebhook::count());

        $this->page($a, $owner)->set('webhookName', 'Zapier')->set('webhookTargetUrl', 'https://hooks.example.com/x')->call('createWebhook')->assertOk();
        $hook = AgencyWebhook::sole();
        $this->assertNull($hook->secret_token);
        $this->assertNotSame($hook->feed_token, $hook->signing_secret);
        $this->assertSame(hash('sha256', $hook->feed_token), $hook->feed_token_hash);
        $this->assertSame(Webhooks::EVENTS, $hook->events);
        $this->getJson('/api/v1/export/'.$hook->feed_token)->assertOk()->assertJsonFragment(['feed_name' => 'Zapier']);
        $this->getJson('/api/v1/export/'.str_repeat('a', 40))->assertNotFound();

        $manager = User::factory()->create();
        $a->members()->attach($manager->id, ['role' => 'manager']);
        $this->page($a, $manager)->set('webhookName', 'Other')->set('webhookTargetUrl', 'https://hooks.example.com/y')->call('createWebhook')->assertForbidden();
        $this->page($a, $manager)->call('deactivateWebhook', $hook->id)->assertForbidden();

        $this->page($a, $owner)->call('deactivateWebhook', $hook->id)->assertOk();
        $this->getJson('/api/v1/export/'.$hook->feed_token)->assertNotFound();
    }

    public function test_delivery_is_signed_with_the_signing_secret_and_does_not_follow_redirects(): void
    {
        Webhooks::$resolver = fn () => '93.184.216.34';
        [$a, , $c] = $this->agency();
        [$hook, $token, $secret] = AgencyWebhook::issue(['agency_id' => $a->id, 'client_id' => $c->id, 'name' => 'Z', 'target_url' => 'https://hooks.example.com/in', 'events' => Webhooks::EVENTS]);

        Http::fake(['hooks.example.com/*' => Http::sequence()->push('ok', 200)->push('', 302, ['Location' => 'http://169.254.169.254/'])]);
        (new DeliverAgencyWebhookJob($hook->id, 'alert.critical', ['alert_id' => 7]))->handle();

        Http::assertSent(function (Request $r) use ($secret, $token) {
            $expected = 'sha256='.hash_hmac('sha256', $r->header('X-AdsRanger-Timestamp')[0].'.'.$r->body(), $secret);

            return $r->url() === 'https://hooks.example.com/in' && $r->header('X-AdsRanger-Signature')[0] === $expected
                && $r->header('X-AdsRanger-Event')[0] === 'alert.critical'
                && $expected !== 'sha256='.hash_hmac('sha256', $r->header('X-AdsRanger-Timestamp')[0].'.'.$r->body(), $token);
        });
        $this->assertSame(200, $hook->fresh()->last_status);

        (new DeliverAgencyWebhookJob($hook->id, 'alert.critical', ['alert_id' => 8]))->handle();
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '169.254'));
        $this->assertSame(1, $hook->fresh()->failures);

        Webhooks::$resolver = fn () => null; // target now resolves privately: nothing more is sent
        (new DeliverAgencyWebhookJob($hook->id, 'alert.critical', ['alert_id' => 9]))->handle();
        Http::assertSentCount(2);
        $this->assertSame(2, $hook->fresh()->failures);
    }

    public function test_critical_alerts_fire_webhooks_only_while_the_module_is_on(): void
    {
        [$a, , $c] = $this->agency();
        AgencyWebhook::issue(['agency_id' => $a->id, 'client_id' => null, 'name' => 'All', 'target_url' => 'https://hooks.example.com/in', 'events' => ['alert.critical']]);
        AgencyWebhook::issue(['agency_id' => $a->id, 'client_id' => null, 'name' => 'Reports', 'target_url' => 'https://hooks.example.com/r', 'events' => ['report.sent']]);

        Queue::fake();
        $this->assertSame(1, Webhooks::fire($a, 'alert.critical', ['alert_id' => 1], $c->id));
        $this->assertSame(0, Webhooks::fire($a, 'lead.created', [], $c->id));
        Queue::assertPushed(DeliverAgencyWebhookJob::class, 1);

        config(['modules.enabled.webhooks' => false]);
        $this->assertSame(0, Webhooks::fire($a, 'alert.critical', ['alert_id' => 2], $c->id));
        $this->assertTrue(method_exists(AlertEngine::class, 'run'));
    }
}
