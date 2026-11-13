<?php

namespace Tests\Feature;

use App\Alerts\AlertEngine;
use App\Filament\Pages\AlertRules;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\OnCall;
use App\Support\WeekList;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** R4: FS-21 on-call rota, FS-19 several currencies on one client; new-style severities counted everywhere. */
class R4iOnCallMarketsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4i', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        return [$agency, $owner];
    }

    public function test_rota_assigns_new_critical_alerts_to_the_day_s_person(): void
    {
        [$agency, $owner] = $this->workspace();
        $sami = User::factory()->create(['name' => 'Sami']);
        $agency->members()->attach($sami->id, ['role' => 'manager']);
        Livewire::test(AlertRules::class)->set('onCall.'.now()->isoWeekday(), (string) $sami->id)->call('saveOnCall')->assertSee('On call today: Sami');
        $this->assertSame($sami->id, OnCall::userFor($agency->fresh())?->id);

        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        foreach (range(1, 3) as $ago) {
            DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'date' => now()->subDays($ago)->toDateString(), 'network' => 'meta', 'currency' => 'SAR', 'spend' => 100, 'results' => 0]);
        }
        AlertEngine::run($agency->fresh(), now()->subDay()->toDateString());
        $al = Alert::where('rule_key', 'spend_no_results')->firstOrFail();
        $this->assertSame('critical', $al->severity);
        $this->assertSame($sami->id, $al->assigned_to);

        // A 'critical' (D-88) alert counts in the week list too, not only legacy 'high'.
        $n = Alert::whereIn('severity', ['critical'])->where('status', 'open')->count();
        $this->assertSame([trans_choice(':n open Critical alert|:n open Critical alerts', $n, ['n' => $n])], WeekList::for($agency, $owner)[0]['reasons']);
    }

    public function test_client_with_two_currencies_shows_each_and_the_total(): void
    {
        [$agency] = $this->workspace();
        DB::table('fx_rates')->insert([['date' => now()->subDays(40)->toDateString(), 'base' => 'USD', 'quote' => 'SAR', 'rate' => 3.75], ['date' => now()->subDays(40)->toDateString(), 'base' => 'USD', 'quote' => 'EGP', 'rate' => 50]]);
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'date' => now()->subDays(3)->toDateString(), 'network' => 'meta', 'currency' => 'SAR', 'spend' => 375, 'results' => 5]);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'date' => now()->subDays(3)->toDateString(), 'network' => 'meta', 'currency' => 'EGP', 'spend' => 5000, 'results' => 9]);
        Livewire::test(ViewClient::class, ['record' => $c->id])->assertSee('By account currency')->assertSee('5,000.00 EGP')->assertSee('750.00 SAR')->assertSee('200.00 USD');
    }
}
