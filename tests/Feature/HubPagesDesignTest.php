<?php

namespace Tests\Feature;

use App\Filament\Pages\AgencyOpsHubPage;
use App\Filament\Pages\IntelligenceHubPage;
use App\Filament\Pages\OrganicSocialPage;
use App\Models\Agency;
use App\Models\AgencyOperationalAudit;
use App\Models\Alert;
use App\Models\BlendedRoasMetric;
use App\Models\Client;
use App\Models\InfluencerCampaign;
use App\Models\IntentKeywordMap;
use App\Models\ProspectPitchDeck;
use App\Models\PsychologicalHookScore;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\AdminShell;
use App\Support\OrganicSocialManager;
use App\Support\Shell;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** D-315: the three hub pages get a stylesheet; Agency Ops has no sample numbers, checks roles and validates what is typed. */
class HubPagesDesignTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-hub', 'plan' => 'agency', 'status' => 'active', 'timezone' => 'Asia/Amman', 'language' => 'en']);
        $this->agency->forceFill(['module_overrides' => ['agency_ops_hub' => true, 'intelligence_hub' => true, 'organic' => true]])->save();
        $this->client = Client::create(['agency_id' => $this->agency->id, 'name' => 'Zad Store', 'status' => 'active', 'currency' => 'KWD']);
    }

    private function member(string $role): User
    {
        $u = User::factory()->create();
        DB::table('agency_user')->where('user_id', $u->id)->delete();
        $this->agency->members()->attach($u->id, ['role' => $role]);

        return $u;
    }

    private function page(User $u, string $path): string
    {
        Shell::forget();

        return $this->withSession(['locale' => 'en'])->actingAs($u)->get("/app/{$this->agency->slug}/$path")->assertOk()->getContent();
    }

    public function test_the_three_hub_pages_carry_the_stylesheet_and_no_ticket_numbers(): void
    {
        $owner = $this->member('owner');
        foreach (['agency-ops', 'intelligence-hub', 'organic-social'] as $path) {
            $html = $this->page($owner, $path);
            $this->assertStringContainsString('data-hub-styles', $html, $path);
            $this->assertMatchesRegularExpression('#class="hub [^"]*"#', $html, $path);
            $this->assertDoesNotMatchRegularExpression('/\(#\d+/', strip_tags($html), "$path shows an internal ticket number");
        }
    }

    public function test_organic_page_does_not_invent_a_best_time(): void
    {
        $html = $this->page($this->member('owner'), 'organic-social');
        $this->assertStringContainsString('data-no-best-day', $html);
        $this->assertStringNotContainsString('19:00', $html);
    }

    public function test_agency_ops_has_no_prefilled_numbers_and_a_view_only_note_for_analysts(): void
    {
        $html = $this->page($this->member('analyst'), 'agency-ops');
        $this->assertStringContainsString('data-ops-view-only', $html);
        $this->assertStringNotContainsString('wire:submit="runRunwayAudit"', $html);
        $owner = $this->page($this->member('owner'), 'agency-ops');
        $this->assertStringContainsString('wire:submit="runRunwayAudit"', $owner);
        foreach (['1500', '8500'] as $sample) {
            $this->assertStringNotContainsString($sample, $owner);
        }
    }

    public function test_an_analyst_cannot_record_and_bad_numbers_are_refused(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs($this->member('analyst'));
        Filament::setTenant($this->agency);
        Livewire::test(AgencyOpsHubPage::class)->set('remainingBalance', 900)->set('dailyBurnRate', 300)->call('runRunwayAudit');
        $this->assertSame(0, AgencyOperationalAudit::count());

        $this->actingAs($this->member('manager'));
        Filament::setTenant($this->agency);
        Livewire::test(AgencyOpsHubPage::class)->set('remainingBalance', 900)->set('dailyBurnRate', 0)->call('runRunwayAudit')->assertHasErrors('dailyBurnRate');
        $this->assertSame(0, AgencyOperationalAudit::count());

        Livewire::test(AgencyOpsHubPage::class)->set('remainingBalance', 900)->set('dailyBurnRate', 300)->call('runRunwayAudit')->assertHasNoErrors();
        $audit = AgencyOperationalAudit::sole();
        $this->assertSame('critical_depletion', $audit->status);
        $this->assertSame(['Runs out within 3 days', 'bad'], AgencyOpsHubPage::statusLabel($audit->status));

        Livewire::test(AgencyOpsHubPage::class)->set('influencerName', 'Noura')->set('promoCode', 'bad code!')->set('fixedFee', 500)
            ->set('commissionPercent', 10)->set('ordersCount', 40)->set('attributedRevenue', 6200)->call('recordInfluencer')->assertHasErrors('promoCode');
        Livewire::test(AgencyOpsHubPage::class)->set('influencerName', 'Noura')->set('promoCode', 'noura10')->set('fixedFee', 500)
            ->set('commissionPercent', 10)->set('ordersCount', 40)->set('attributedRevenue', 6200)->call('recordInfluencer')->assertHasNoErrors();
        $ic = InfluencerCampaign::sole();
        $this->assertSame('NOURA10', $ic->promo_code);
        $this->assertEqualsWithDelta(1120.0, (float) $ic->total_payout, 0.001);

        $html = $this->page($this->member('owner'), 'agency-ops');
        $this->assertStringContainsString('1,120.000 KWD', $html, 'payout shown in the client currency with 3 decimals');
        $this->assertStringContainsString('3 days left', $html);
    }

    public function test_intelligence_hub_reads_saved_numbers_only_and_checks_roles(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs($this->member('analyst'));
        Filament::setTenant($this->agency);
        Livewire::test(IntelligenceHubPage::class)->set('seedKeyword', 'dental implants')->call('generateKeywords')
            ->set('adCopySample', 'Book now, limited offer')->call('scoreHook')->call('computeBlendedRoas');
        $this->assertSame(0, IntentKeywordMap::count() + PsychologicalHookScore::count() + BlendedRoasMetric::count());

        $this->actingAs($this->member('manager'));
        Filament::setTenant($this->agency);
        Livewire::test(IntelligenceHubPage::class)->set('seedKeyword', 'x')->call('generateKeywords')->assertHasErrors('seedKeyword');
        Livewire::test(IntelligenceHubPage::class)->set('seedKeyword', 'dental implants')->set('keywordLocale', 'en')->call('generateKeywords')->assertHasNoErrors();
        $this->assertSame('dental implants', IntentKeywordMap::sole()->target_service);

        BlendedRoasMetric::create(['agency_id' => $this->agency->id, 'client_id' => $this->client->id, 'metric_date' => '2026-11-13', 'total_spend' => 100, 'total_revenue' => 450,
            'total_conversions' => 9, 'blended_roas' => 4.5, 'marketing_efficiency_ratio' => 4.5, 'channel_breakdown' => ['meta' => ['spend' => 60, 'revenue' => 330, 'roas' => 5.5], 'google_ads' => ['spend' => 40, 'revenue' => 120, 'roas' => 3]]]);
        Alert::create(['agency_id' => $this->agency->id, 'client_id' => $this->client->id, 'rule_key' => 'x', 'severity' => 'critical', 'title' => 'Spend is twice the plan', 'fingerprint' => 'a', 'status' => 'open']);
        Livewire::test(IntelligenceHubPage::class)->set('clientId', $this->client->id)->call('askPlatform')
            ->assertSee('spend 100.000 KWD')->assertSee('return 4.50')->assertSee('Highest return that day: meta.')
            ->call('generateWeeklyReview')->assertSee('Zad Store: Spend is twice the plan');

        $html = $this->page($this->member('owner'), 'intelligence-hub');
        $this->assertStringContainsString('No AI model is used here', $html);
        $this->assertStringContainsString('dental implants pricing', $html, 'the saved list shows its phrases');
    }

    public function test_organic_social_is_a_content_plan_with_controls(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs($this->member('analyst'));
        Filament::setTenant($this->agency);
        Livewire::test(OrganicSocialPage::class)->set('planClientId', $this->client->id)->set('planContent', 'Ramadan offer')->set('planPlatforms', ['meta'])->call('planPost');
        $this->assertSame(0, SocialPost::count());

        $this->actingAs($this->member('manager'));
        Filament::setTenant($this->agency);
        Livewire::test(OrganicSocialPage::class)->set('planClientId', $this->client->id)->set('planContent', 'Ramadan offer')->call('planPost')->assertHasErrors('planPlatforms');
        Livewire::test(OrganicSocialPage::class)->set('planClientId', $this->client->id)->set('planContent', 'Ramadan offer')->set('planPlatforms', ['meta', 'tiktok'])
            ->set('planAt', now()->addDays(3)->format('Y-m-d\TH:i'))->call('planPost')->assertHasNoErrors();
        $post = SocialPost::sole();
        $this->assertSame('scheduled', $post->status);
        $this->assertNull($post->published_at, 'nothing is published by planning');

        Livewire::test(OrganicSocialPage::class)->call('markPosted', $post->id);
        $this->assertSame('published', $post->fresh()->status);
        $second = OrganicSocialManager::schedulePost($this->client, 'Second', ['x']);
        Livewire::test(OrganicSocialPage::class)->call('cancelPost', $second->id);
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertSame(2, SocialPost::count(), 'cancelled posts are kept');

        $html = $this->page($this->member('owner'), 'organic-social');
        $this->assertStringContainsString('data-social-honesty', $html);
        $this->assertStringContainsString('Posted (marked by hand)', $html);
    }

    public function test_a_pitch_deck_link_can_be_switched_off(): void
    {
        $deck = ProspectPitchDeck::create(['agency_id' => $this->agency->id, 'prospect_name' => 'Lamsa', 'prospect_url' => 'https://example.com', 'currency' => 'SAR',
            'share_token' => str_repeat('a', 40), 'audit_snapshot' => [], 'tier_quotes' => []]);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs($this->member('analyst'));
        Filament::setTenant($this->agency);
        Livewire::test(AgencyOpsHubPage::class)->call('revokePitchDeck', $deck->id);
        $this->assertNull($deck->fresh()->revoked_at);

        $this->actingAs($this->member('owner'));
        Filament::setTenant($this->agency);
        Livewire::test(AgencyOpsHubPage::class)->call('revokePitchDeck', $deck->id);
        $this->assertNotNull($deck->fresh()->revoked_at);
        $this->get('/pitch/'.$deck->share_token)->assertNotFound();
        $this->get('/pitch/'.$deck->share_token.'/pdf')->assertNotFound();
        $this->assertStringContainsString('data-deck-revoked', $this->page($this->member('admin'), 'agency-ops'));
    }

    public function test_admin_gets_the_mission_look_and_a_person_can_leave_it(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save(); // two-step is required on /admin
        $html = $this->withSession(['locale' => 'en'])->actingAs($admin)->get('/admin')->assertOk()->getContent();
        $this->assertStringContainsString('data-mission-admin-tokens', $html);
        $admin->forceFill(['ui_prefs' => ['look' => 'classic']])->save();
        AdminShell::forget();
        $html = $this->actingAs($admin->fresh())->get('/admin')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-mission-admin-tokens', $html);
        $this->assertStringContainsString('data-admin-shell-tokens', $html);
    }
}
