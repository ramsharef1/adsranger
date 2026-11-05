<?php

namespace Tests\Feature;

use App\Filament\Pages\GoalCalculatorPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\GoalCalculator;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** #88 Reverse-funnel goal calculator (Starter). */
class GoalCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $role = 'analyst'): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-gc-'.$role, 'plan' => 'starter', 'status' => 'active']);
        $user = User::factory()->create();
        $agency->members()->attach($user->id, ['role' => $role]);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Clinic One', 'currency' => 'JOD']);

        return [$agency, $user, $client];
    }

    private function metrics(Client $c, array $v): void
    {
        DailyMetric::create(array_merge(['agency_id' => $c->agency_id, 'client_id' => $c->id, 'date' => now()->subDays(5)->toDateString(),
            'network' => 'meta', 'currency' => 'JOD', 'result_type' => 'purchase', 'spend' => 0, 'revenue' => 0], $v));
    }

    public function test_plan_works_back_from_sales(): void
    {
        $p = GoalCalculator::plan('purchases', 100, ['link_clicks' => 1, 'landing_views' => 80, 'add_to_cart' => 10, 'checkouts' => 50, 'purchases' => 50], 5.0, null, 30);

        $needed = array_column($p['steps'], 'needed', 'key');
        $this->assertSame(100.0, $needed['purchases']);
        $this->assertSame(200.0, $needed['checkouts']);
        $this->assertSame(400.0, $needed['add_to_cart']);
        $this->assertSame(4000.0, $needed['landing_views']);
        $this->assertSame(5000.0, $needed['link_clicks']);
        $this->assertSame(500000.0, $needed['impressions']);
        $this->assertSame(2500.0, $p['budget']);
        $this->assertEqualsWithDelta(83.33, $p['per_day'], 0.01);
    }

    public function test_empty_step_is_skipped_and_revenue_uses_aov(): void
    {
        $p = GoalCalculator::plan('leads', 50, ['link_clicks' => 2, 'landing_views' => '', 'leads' => 10], null, null, 30);
        $this->assertSame(['impressions', 'link_clicks', 'leads'], array_column($p['steps'], 'key'));
        $this->assertSame(500.0, $p['steps'][1]['needed']);
        $this->assertNull($p['budget']);

        $r = GoalCalculator::plan('revenue', 10000, ['link_clicks' => 1, 'landing_views' => 80, 'add_to_cart' => 10, 'checkouts' => 50, 'purchases' => 50], 4.0, 50.0, 30);
        $this->assertSame(200.0, $r['goal_count']);
        $this->assertNull(GoalCalculator::plan('revenue', 10000, ['purchases' => 50], 4.0, null, 30));
        $this->assertNull(GoalCalculator::plan('leads', 50, ['link_clicks' => 2, 'leads' => ''], null, null, 30)); // goal step needs a rate
        $this->assertNull(GoalCalculator::plan('leads', 0, ['link_clicks' => 2, 'leads' => 10], null, null, 30));
    }

    public function test_history_uses_client_rates_cpm_and_aov(): void
    {
        [, , $c] = $this->workspace();
        $this->metrics($c, ['spend' => 900, 'revenue' => 3000, 'impressions' => 300000, 'link_clicks' => 3000, 'landing_views' => 2400,
            'add_to_cart' => 240, 'checkouts' => 120, 'purchases' => 60]);

        $h = GoalCalculator::history($c->fresh());
        $this->assertSame(1.0, $h['rates']['link_clicks']);
        $this->assertSame(80.0, $h['rates']['landing_views']);
        $this->assertSame(10.0, $h['rates']['add_to_cart']);
        $this->assertSame(50.0, $h['rates']['purchases']);
        $this->assertSame(3.0, $h['cpm']);
        $this->assertSame(50.0, $h['aov']);
        $this->assertEqualsWithDelta(15.0, $h['cost_per']['purchases'], 0.001);
        $this->assertArrayNotHasKey('leads', $h['rates']);
    }

    public function test_page_prefills_from_history_and_shows_budget(): void
    {
        [$agency, $user, $c] = $this->workspace();
        $this->metrics($c, ['spend' => 900, 'revenue' => 3000, 'impressions' => 300000, 'link_clicks' => 3000, 'landing_views' => 2400,
            'add_to_cart' => 240, 'checkouts' => 120, 'purchases' => 60]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        Livewire::test(GoalCalculatorPage::class)
            ->assertSet('clientId', $c->id)
            ->assertSet('rates.landing_views', '80')
            ->assertSet('cpm', '3')
            ->set('target', '120')
            ->assertSee('Budget needed')
            ->assertSee('1,800.00') // 120 sales → 600,000 impressions × 3 JOD CPM
            ->set('goal', 'leads')
            ->assertSet('rates.leads', (string) GoalCalculator::DEFAULT_RATES['leads']);
    }

    public function test_page_renders_in_both_languages_and_without_clients(): void
    {
        [$agency, $user] = $this->workspace('owner');
        $this->withSession(['locale' => 'ar'])->actingAs($user)->get("/app/{$agency->slug}/goal-calculator")
            ->assertOk()->assertSee('حاسبة الأهداف')->assertSee('افتراض مبدئي');
        $this->withSession(['locale' => 'en'])->actingAs($user)->get("/app/{$agency->slug}/goal-calculator")
            ->assertOk()->assertSee('Goal calculator')->assertSee('Enter a target above to see the funnel you need.');

        $empty = Agency::create(['name' => 'Empty', 'slug' => 'empty-gc', 'plan' => 'starter', 'status' => 'active']);
        $empty->members()->attach($user->id, ['role' => 'owner']);
        $this->withSession(['locale' => 'en'])->actingAs($user)->get('/app/empty-gc/goal-calculator')
            ->assertOk()->assertSee('No clients yet. Add a client first, then plan its goals.');
    }
}
