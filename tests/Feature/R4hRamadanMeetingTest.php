<?php

namespace Tests\Feature;

use App\Filament\Pages\MeetingPrepPage;
use App\Filament\Pages\RamadanReadinessPage;
use App\Models\Agency;
use App\Models\BudgetPlan;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use App\Support\RamadanReadiness;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R4: FS-39 Ramadan readiness score with missing steps; XS-07 / PX-39 meeting prep with presentation mode. */
class R4hRamadanMeetingTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4h', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        return [$agency, $owner];
    }

    public function test_readiness_counts_steps_and_lists_what_is_missing(): void
    {
        [$agency, $owner] = $this->workspace();
        $next = RamadanReadiness::next();
        $this->assertNotNull($next);
        $ready = Client::create(['agency_id' => $agency->id, 'name' => 'Ready Co', 'currency' => 'SAR', 'status' => 'active', 'monthly_budget' => 1000, 'target_cpr' => 10]);
        BudgetPlan::create(['agency_id' => $agency->id, 'client_id' => $ready->id, 'month' => substr($next['start'], 0, 7).'-01', 'budget' => 1500]);
        Client::create(['agency_id' => $agency->id, 'name' => 'Bare Co', 'currency' => 'SAR', 'status' => 'active']);

        $rows = RamadanReadiness::for($agency, $owner, $next['start']);
        $this->assertSame('Bare Co', $rows[0]['client']->name, 'lowest score first');
        $this->assertContains('Targets set', $rows[0]['missing']);
        $this->assertNotContains('Ramadan month budget planned (Budget planner)', $rows[1]['missing']);
        $this->assertGreaterThan($rows[0]['score'], $rows[1]['score']);

        Livewire::test(RamadanReadinessPage::class)->assertSee('Ramadan starts on')->assertSee('Bare Co');
    }

    public function test_meeting_prep_hides_internal_notes_in_presentation_mode(): void
    {
        [$agency] = $this->workspace();
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        ClientContact::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'kind' => 'call', 'happened_on' => now()->toDateString(), 'note' => 'They are late paying']);

        Livewire::withQueryParams(['client' => $c->id])->test(MeetingPrepPage::class)
            ->assertSee('Zad Store')->assertSee('They are late paying')->assertSee('Agree a monthly budget')
            ->set('present', true)->assertDontSee('They are late paying')->assertSee('Leave presentation mode');
    }
}
