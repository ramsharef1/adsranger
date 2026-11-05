<?php

namespace Tests\Feature;

use App\Alerts\AlertEngine;
use App\Filament\Pages\AlertRules;
use App\Filament\Pages\InsightsPage;
use App\Filament\Pages\MenaCalendarPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use App\Support\PlanGate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Plan-tier gating (D-30, EX-50): config-driven, trial = Pro (D-61), locked pages stay visible with LockedState. */
class PlanGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withSession(['locale' => 'en']); // panels default to Arabic (UX-3); these checks read the English strings
    }

    private function agency(string $plan, string $status = 'active'): Agency
    {
        return Agency::create(['name' => "Acme $plan $status", 'slug' => Agency::uniqueSlug("acme $plan $status"), 'plan' => $plan, 'status' => $status]);
    }

    private function member(Agency $a, string $role): User
    {
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => $role]);

        return $u;
    }

    public function test_tier_resolution(): void
    {
        $this->assertSame('starter', PlanGate::tier($this->agency('starter')));
        $this->assertSame('pro', PlanGate::tier($this->agency('starter', 'trial')));
        $this->assertSame('agency', PlanGate::tier($this->agency('agency', 'trial')));
        $this->assertSame('starter', PlanGate::tier($this->agency('unknown-plan')));
    }

    public function test_feature_checks_follow_the_config(): void
    {
        $starter = $this->agency('starter');
        $pro = $this->agency('pro');
        $agency = $this->agency('agency');

        $this->assertFalse(PlanGate::allows($starter, 'insights'));
        $this->assertTrue(PlanGate::allows($pro, 'insights'));
        $this->assertFalse(PlanGate::allows($pro, 'annual_budget'));
        $this->assertTrue(PlanGate::allows($agency, 'annual_budget'));
        $this->assertTrue(PlanGate::allows($starter, 'not-a-gated-feature'));

        config(['plan_features.features.insights.tier' => 'starter']);
        $this->assertTrue(PlanGate::allows($starter, 'insights'), 'moving a feature is a config change only');
    }

    public function test_every_config_entry_is_well_formed(): void
    {
        $tiers = config('plan_features.tiers');
        foreach (config('plan_features.features') as $key => $f) {
            $this->assertArrayHasKey($f['tier'], $tiers, $key);
            $this->assertNotEmpty($f['refs'], $key);
            foreach (['name', 'what'] as $field) {
                $this->assertNotEmpty($f[$field]['en'] ?? null, "$key.$field.en");
                $this->assertNotEmpty($f[$field]['ar'] ?? null, "$key.$field.ar");
            }
        }
        foreach (config('plan_features.pro_alert_rules') as $rule) {
            $this->assertArrayHasKey($rule, config('alerts.rules'), $rule);
        }
        $this->assertCount(7, array_diff(array_keys(config('alerts.rules')), config('plan_features.pro_alert_rules')), '#23: 7 rules on Starter');
    }

    public function test_locked_page_shows_locked_state_with_upgrade_for_owner(): void
    {
        $a = $this->agency('starter');
        $owner = $this->member($a, 'owner');

        $this->actingAs($owner)->get("/app/{$a->slug}/insights")
            ->assertOk()
            ->assertSee('Available on Pro')
            ->assertSee('Upgrade to Pro')
            ->assertSee('Example')
            ->assertDontSee('Ask your workspace owner');
    }

    public function test_locked_page_tells_other_roles_to_ask_the_owner(): void
    {
        $a = $this->agency('starter');
        $analyst = $this->member($a, 'analyst');

        $this->actingAs($analyst)->get("/app/{$a->slug}/benchmarks")
            ->assertOk()
            ->assertSee('Available on Pro')
            ->assertSee('Ask your workspace owner')
            ->assertDontSee('Upgrade to Pro');
    }

    public function test_unlocked_page_renders_normally(): void
    {
        $a = $this->agency('pro');
        $owner = $this->member($a, 'owner');

        $this->actingAs($owner)->get("/app/{$a->slug}/insights")
            ->assertOk()
            ->assertDontSee('Available on Pro');

        // Annual budget plan is Agency: locked on Pro.
        $this->actingAs($owner)->get("/app/{$a->slug}/budget-plan")
            ->assertOk()
            ->assertSee('Available on Agency');
    }

    public function test_trial_workspaces_get_pro_features(): void
    {
        $a = $this->agency('starter', 'trial');
        $owner = $this->member($a, 'owner');

        $this->actingAs($owner)->get("/app/{$a->slug}/google")->assertOk()->assertDontSee('Available on Pro');
    }

    public function test_actions_on_a_locked_page_are_refused(): void
    {
        $a = $this->agency('starter');
        $owner = $this->member($a, 'owner');
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        Livewire::test(InsightsPage::class)->call('setPeriod', 'last_7')->assertForbidden();
    }

    public function test_navigation_marks_locked_pages(): void
    {
        $a = $this->agency('starter');
        $this->actingAs($this->member($a, 'owner'));
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        $this->assertSame('Pro', InsightsPage::getNavigationBadge());
        $this->assertSame('heroicon-o-lock-closed', InsightsPage::getNavigationIcon());

        $a->update(['plan' => 'pro']);
        $this->assertNull(InsightsPage::getNavigationBadge());
    }

    public function test_forecast_and_scenario_lock_inside_open_pages(): void
    {
        $a = $this->agency('starter');
        $owner = $this->member($a, 'owner');

        $client = Client::create(['agency_id' => $a->id, 'name' => 'Clinic One', 'monthly_budget' => 3000, 'market' => 'JO']);

        $this->actingAs($owner)->get("/app/{$a->slug}/pacing")->assertOk()
            ->assertSee('Clinic One')->assertSee('plan-locked-forecast')->assertDontSee('Month-end forecast</div><strong>', false);
        $this->actingAs($owner)->get("/app/{$a->slug}/clients/{$client->id}")->assertOk()->assertSee('plan-locked-forecast');
        $this->actingAs($owner)->get("/app/{$a->slug}/calendar")->assertOk()->assertSee('plan-locked-scenario_planner');

        // Pro: no locks, the forecast shows.
        $a->update(['plan' => 'pro']);
        $this->actingAs($owner)->get("/app/{$a->slug}/pacing")->assertOk()->assertDontSee('plan-locked-forecast');
        $this->actingAs($owner)->get("/app/{$a->slug}/calendar")->assertOk()->assertDontSee('plan-locked-scenario_planner');
    }

    public function test_scenario_action_is_refused_when_locked(): void
    {
        $a = $this->agency('starter');
        $this->actingAs($this->member($a, 'owner'));
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        Livewire::test(MenaCalendarPage::class)->call('plan', 'ramadan', '2027-02-08')->assertForbidden();
        $a->update(['plan' => 'pro']);
        Livewire::test(MenaCalendarPage::class)->call('plan', 'ramadan', '2027-02-08')->assertOk()->assertSet('event', 'ramadan|2027-02-08');
    }

    public function test_saving_alert_rules_keeps_locked_rules_untouched(): void
    {
        $a = $this->agency('starter');
        $this->actingAs($this->member($a, 'owner'));
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        Livewire::test(AlertRules::class)
            ->assertSee('plan-locked-alert_rules_pro')
            ->set('rules.pacing.enabled', true)
            ->call('save');

        $this->assertDatabaseMissing('alert_rules', ['agency_id' => $a->id, 'rule_key' => 'pacing']);
        $this->assertDatabaseHas('alert_rules', ['agency_id' => $a->id, 'rule_key' => 'tracking_break']);
    }

    public function test_pro_alert_rules_never_run_on_starter(): void
    {
        $starter = AlertEngine::rules($this->agency('starter'));
        $pro = AlertEngine::rules($this->agency('pro'));

        foreach (config('plan_features.pro_alert_rules') as $k) {
            $this->assertTrue($starter[$k]['locked'], $k);
            $this->assertFalse($starter[$k]['enabled'], $k);
            $this->assertFalse($pro[$k]['locked'], $k);
            $this->assertTrue($pro[$k]['enabled'], $k);
        }
        $this->assertTrue($starter['tracking_break']['enabled']);
    }

    public function test_plan_page_lists_what_each_tier_unlocks(): void
    {
        $a = $this->agency('starter');
        $owner = $this->member($a, 'owner');

        $this->actingAs($owner)->get("/app/{$a->slug}/plan")
            ->assertOk()
            ->assertSee('What each plan unlocks')
            ->assertSee('Insights')
            ->assertSee('Annual budget plan')
            ->assertSee('149 USD / month');
    }

    public function test_every_agency_page_renders_on_each_tier_and_role(): void
    {
        $pages = ['desk', 'alerts', 'alert-rules', 'clients', 'compare', 'pacing', 'budget-plan', 'calendar', 'insights', 'benchmarks',
            'google', 'landing-pages', 'competitors', 'networks', 'connections', 'ad-accounts', 'imports', 'members', 'invitations', 'notifications', 'plan'];
        if (! extension_loaded('intl')) {
            // Filament tables format numbers with intl (installed on the server); without it only the non-table pages can render.
            $pages = array_values(array_diff($pages, ['alerts', 'clients', 'connections', 'ad-accounts', 'imports', 'members', 'invitations']));
        }
        $users = [];
        foreach (['starter', 'pro', 'agency'] as $plan) {
            $a = $this->agency($plan);
            foreach (['owner', 'analyst'] as $role) {
                $users[] = [$plan, $role, $a, $this->member($a, $role)];
            }
        }
        foreach ($users as [$plan, $role, $a, $u]) {
            foreach ($pages as $page) {
                $status = $this->actingAs($u)->get("/app/{$a->slug}/$page")->status();
                $this->assertContains($status, [200, 403], "$plan/$role/$page gave $status");
            }
        }
    }

    public function test_locked_state_in_arabic(): void
    {
        $a = $this->agency('starter');
        $owner = $this->member($a, 'owner');

        $this->withSession(['locale' => 'ar'])->actingAs($owner)->get("/app/{$a->slug}/insights")
            ->assertOk()
            ->assertSee('متاحة في الباقة الاحترافية')
            ->assertSee('ترقية إلى الباقة الاحترافية')
            ->assertSee('مثال');
    }

    public function test_calendar_plan_button_renders_in_english_and_arabic_without_plan_group_collision(): void
    {
        $starter = $this->agency('starter');
        $starterOwner = $this->member($starter, 'owner');

        $this->withSession(['locale' => 'en'])->actingAs($starterOwner)->get("/app/{$starter->slug}/calendar")
            ->assertOk()
            ->assertSee('Plan season');

        $this->withSession(['locale' => 'ar'])->actingAs($starterOwner)->get("/app/{$starter->slug}/calendar")
            ->assertOk()
            ->assertSee('الخطة');

        $pro = $this->agency('pro');
        $proOwner = $this->member($pro, 'owner');

        $this->withSession(['locale' => 'en'])->actingAs($proOwner)->get("/app/{$pro->slug}/calendar")
            ->assertOk()
            ->assertSee('Plan season');
    }
}
