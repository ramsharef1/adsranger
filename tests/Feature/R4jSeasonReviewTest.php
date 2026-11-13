<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\AiBrainItem;
use App\Models\BudgetPlan;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\SeasonReview;
use Carbon\CarbonPeriod;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R4: FS-22 season review after Ramadan: plan vs actual by week, kept in the client brain on request. */
class R4jSeasonReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_after_ramadan_the_client_page_shows_plan_vs_actual_and_can_keep_it(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 25)->setTime(12, 0));
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4j', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active', 'monthly_budget' => 3100]);
        BudgetPlan::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'month' => '2026-03-01', 'budget' => 6200]);
        foreach (CarbonPeriod::create('2026-01-20', '2026-03-20') as $d) {
            DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'date' => $d->toDateString(), 'network' => 'meta', 'currency' => 'SAR',
                'spend' => $d->toDateString() >= '2026-02-18' ? 200 : 100, 'purchases' => 5, 'results' => 5]);
        }

        $r = SeasonReview::latest($c);
        $this->assertNotNull($r);
        $this->assertSame('ramadan', $r['key']);
        $this->assertSame(100.0, (float) $r['spend_vs_before'], 'double the daily spend of the 28 days before');
        $this->assertSame(1400.0, $r['weeks'][0]['spend']);

        config(['modules.preview_agencies' => ['acme-r4j']]);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(ViewClient::class, ['record' => $c->id])->assertSee('Season review: Ramadan')->call('rememberSeason');
        $this->assertStringContainsString('per day vs the 28 days before', AiBrainItem::where('client_id', $c->id)->where('type', 'learning')->value('original'));
    }
}
