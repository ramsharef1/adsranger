<?php

namespace Tests\Feature;

use App\Filament\Pages\AgencyOpsHubPage;
use App\Filament\Pages\ClientOnboardingLinksPage;
use App\Filament\Pages\IntelligenceHubPage;
use App\Filament\Pages\WebhooksPage;
use App\Models\Agency;
use App\Models\AgencyWebhook;
use App\Models\Client;
use App\Models\ClientOnboardingLink;
use App\Support\Modules;
use App\Support\NetworkDepthManager;
use App\Support\Webhooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** D-229: hubs off, fake states `simulated`, webhooks + #105 on their own modules; cl52 also drops the doubled X-Frame-Options. */
class HonestyFixesD229Test extends TestCase
{
    use RefreshDatabase;

    public function test_hubs_and_moved_modules_are_off_by_default_and_have_their_own_pages(): void
    {
        foreach (['agency_ops_hub', 'intelligence_hub', 'webhooks', 'client_onboarding'] as $module) {
            $this->assertFalse(Modules::enabled($module), $module);
        }
        $this->assertSame('webhooks', WebhooksPage::getSlug());
        $this->assertSame('client-onboarding', ClientOnboardingLinksPage::getSlug());
        $this->assertFalse(method_exists(AgencyOpsHubPage::class, 'createWebhook'));
        $this->assertFalse(method_exists(AgencyOpsHubPage::class, 'createOnboardingLink'));
        $this->assertTrue(class_exists(IntelligenceHubPage::class));
    }

    public function test_public_onboarding_link_and_feed_answer_only_when_their_module_is_on(): void
    {
        $agency = Agency::create(['name' => 'A', 'slug' => 'a-d229', 'plan' => 'agency', 'status' => 'active']);
        $link = ClientOnboardingLink::create(['agency_id' => $agency->id, 'token' => Str::random(40), 'prospect_name' => 'P', 'requested_networks' => ['meta'], 'status' => 'pending']);
        [$hook] = AgencyWebhook::issue(['agency_id' => $agency->id, 'client_id' => null, 'name' => 'Feed', 'target_url' => 'https://hooks.example.com/x', 'events' => Webhooks::EVENTS]);

        $this->get('/onboard/'.$link->token)->assertNotFound();
        $this->getJson('/api/v1/export/'.$hook->feed_token)->assertNotFound();

        config(['modules.enabled.client_onboarding' => true, 'modules.enabled.webhooks' => true]);
        $this->get('/onboard/'.$link->token)->assertOk();
        $this->getJson('/api/v1/export/'.$hook->feed_token)->assertOk();
    }

    public function test_audience_sync_is_labelled_simulated(): void
    {
        $agency = Agency::create(['name' => 'B', 'slug' => 'b-d229', 'plan' => 'agency', 'status' => 'active']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'C', 'currency' => 'USD']);

        $sync = NetworkDepthManager::syncAudienceList($client, 'List', 'purchasers', ['meta'], 100);

        $this->assertSame('simulated', $sync->status);
        $this->assertNull($sync->last_synced_at);
    }

    public function test_x_frame_options_is_left_to_nginx(): void
    {
        $this->get('/')->assertHeaderMissing('X-Frame-Options')->assertHeader('Content-Security-Policy');
    }
}
