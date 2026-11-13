<?php

namespace Tests\Feature;

use App\Filament\Pages\ClientCalendarPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientLicence;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Support\ClientCalendar;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R4: XS-12 one calendar for all clients, from what the app already holds. */
class R4gClientCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_gathers_reports_follow_ups_licences_and_pauses(): void
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4g', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $a = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        $b = Client::create(['agency_id' => $agency->id, 'name' => 'Noor Clinic', 'currency' => 'SAR', 'status' => 'paused', 'paused_until' => now()->addDays(4)->toDateString()]);
        $day = now()->addDays(3);
        ReportSchedule::create(['agency_id' => $agency->id, 'client_id' => $a->id, 'monthly_day' => min(28, (int) $day->day), 'lang' => 'en', 'recipients' => ['x@example.com'], 'active' => true]);
        ClientContact::create(['agency_id' => $agency->id, 'client_id' => $a->id, 'kind' => 'call', 'happened_on' => now()->toDateString(), 'next_on' => now()->addDays(5)->toDateString()]);
        ClientLicence::create(['agency_id' => $agency->id, 'client_id' => $a->id, 'kind' => 'real_estate', 'number' => 'FAL-77', 'expires_on' => now()->addDays(10)->toDateString()]);

        $this->actingAs($owner);
        $ev = ClientCalendar::events($agency, $owner, now()->toDateString(), now()->addDays(30)->toDateString());
        $kinds = array_column(array_filter($ev, fn ($e) => $e['client_id']), 'kind');
        foreach (['follow_up', 'licence', 'resume'] as $k) {
            $this->assertContains($k, $kinds);
        }
        $this->assertSame(['Noor Clinic'], array_values(array_unique(array_column(array_filter($ev, fn ($e) => $e['kind'] === 'resume'), 'client'))));
        $dates = array_column($ev, 'date');
        $sorted = $dates;
        sort($sorted);
        $this->assertSame($sorted, $dates, 'in date order');

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(ClientCalendarPage::class)->assertSee('FAL-77')->assertSee('Noor Clinic')
            ->set('clientId', $b->id)->assertDontSee('FAL-77')
            ->set('clientId', null)->set('kinds', ['licence'])->assertSee('FAL-77')->assertDontSee('Back from pause');
    }
}
