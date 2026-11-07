<?php

namespace Tests\Feature;

use App\Filament\Pages\AgencyOpsHubPage;
use App\Filament\Pages\IntelligenceHubPage;
use App\Models\Agency;
use App\Models\AgencyWebhook;
use App\Models\Client;
use App\Models\ClientOnboardingLink;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class Wave19AiOnboardingWebhooksTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $owner;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'modules.enabled.intelligence_hub' => true,
            'modules.enabled.agency_ops_hub' => true,
        ]);

        $this->agency = Agency::create([
            'name' => 'MENA Growth Lab',
            'slug' => 'mena-lab',
            'plan' => 'agency',
            'status' => 'active',
            'language' => 'en',
        ]);

        $this->owner = User::factory()->create();
        DB::table('agency_user')->where('user_id', $this->owner->id)->delete();
        $this->agency->members()->attach($this->owner->id, ['role' => 'owner']);

        $this->client = Client::create([
            'agency_id' => $this->agency->id,
            'name' => 'Riyadh Luxury Retail',
            'currency' => 'SAR',
            'status' => 'active',
        ]);
    }

    public function test_ask_the_platform_and_weekly_ai_review_generate_client_scoped_insights(): void
    {
        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->agency);

        Livewire::test(IntelligenceHubPage::class)
            ->set('clientId', $this->client->id)
            ->set('platformQuestion', 'How is our blended ROAS performing this week?')
            ->call('askPlatform')
            ->assertSee('Riyadh Luxury Retail')
            ->call('generateWeeklyReview')
            ->assertSee('Action 1 (Riyadh Luxury Retail)');
    }

    public function test_client_onboarding_link_and_webhook_export_feed_work_end_to_end(): void
    {
        $this->actingAs($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->agency);

        Livewire::test(AgencyOpsHubPage::class)
            ->set('clientId', $this->client->id)
            ->set('prospectName', 'Jeddah E-Com Prospect')
            ->set('prospectWebsite', 'https://jeddah-shop.example.com')
            ->call('createOnboardingLink')
            ->set('webhookName', 'Looker Studio Daily Feed')
            ->set('webhookTargetUrl', 'https://hooks.example.com/adsranger')
            ->call('createWebhook');

        $link = ClientOnboardingLink::where('agency_id', $this->agency->id)->firstOrFail();
        $this->assertSame('Jeddah E-Com Prospect', $link->prospect_name);

        // Public onboarding link opens and accepts network access confirmation
        $this->get('/onboard/'.$link->token)
            ->assertOk()
            ->assertSee('Jeddah E-Com Prospect');

        $this->post('/onboard/'.$link->token, [
            'website_url' => 'https://jeddah-shop.example.com',
            'granted_networks' => ['meta', 'google_ads', 'tiktok'],
            'brand_notes' => 'Target 3.5x ROAS in KSA.',
        ])->assertRedirect('/onboard/'.$link->token);

        $link->refresh();
        $this->assertSame('completed', $link->status);
        $this->assertContains('tiktok', $link->granted_networks);

        // Signed Looker Studio / Sheets JSON feed returns 200 JSON
        $webhook = AgencyWebhook::where('agency_id', $this->agency->id)->firstOrFail();
        $this->getJson('/api/v1/export/'.$webhook->secret_token)
            ->assertOk()
            ->assertJsonFragment([
                'workspace' => 'MENA Growth Lab',
                'feed_name' => 'Looker Studio Daily Feed',
            ]);
    }
}
