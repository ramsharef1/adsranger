<?php

namespace Tests\Feature;

use App\Filament\Pages\ClientCalendarPage;
use App\Filament\Pages\MarketDatesPage;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\Client;
use App\Models\MarketDate;
use App\Models\User;
use App\Support\MarketDates;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R5 part 2 (D-307): PS-21 / XS-19 paydays and XS-20 school / exam periods, entered by the agency, shown as "usually". */
class R5bMarketDatesTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $role = 'owner'): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r5b', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh', 'language' => 'en', 'country' => 'SA']);
        $user = User::factory()->create();
        $agency->members()->attach($user->id, ['role' => $role]);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        return [$agency, $user, $client];
    }

    public function test_agency_adds_dates_and_they_show_on_chart_and_calendar(): void
    {
        [$agency, , $client] = $this->workspace();
        $this->travelTo(Carbon::parse('2026-11-20 10:00', 'Asia/Riyadh'));
        Livewire::test(MarketDatesPage::class)
            ->callAction('addPayday', ['country' => 'SA', 'day_of_month' => 27, 'source' => 'Agency experience'])->assertHasNoActionErrors()
            ->callAction('addPeriod', ['country' => 'SA', 'kind' => 'exams', 'label' => 'Final exams', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-10'])->assertHasNoActionErrors()
            ->callAction('addPayday', ['country' => 'EG', 'day_of_month' => 0])
            ->assertSee('Day 27 of each month')->assertSee('Agency experience')->assertSee('Last day of the month');
        $this->assertSame(3, MarketDate::count());

        Livewire::test(ViewClient::class, ['record' => $client->id])->call('setPeriod', '30d')
            ->assertSee('Payday (usually)')->assertSee('2026-10-27');

        Livewire::test(ClientCalendarPage::class)->set('days', 30)
            ->assertSee('Payday (usually)')->assertSee('Final exams')->assertDontSee('Last day of the month');
    }

    public function test_payday_falls_back_to_the_last_day_and_client_market_wins(): void
    {
        [$agency, , $client] = $this->workspace();
        $this->assertSame('2027-02-28', MarketDates::paydayIn(2027, 2, 30));
        $this->assertSame('2027-02-28', MarketDates::paydayIn(2027, 2, 0));
        MarketDate::create(['agency_id' => $agency->id, 'country' => 'AE', 'kind' => 'payday', 'day_of_month' => 25]);
        MarketDate::create(['agency_id' => $agency->id, 'country' => 'SA', 'kind' => 'payday', 'day_of_month' => 27]);
        $client->forceFill(['market' => 'AE'])->save();
        $dates = MarketDates::forClient($client->fresh(), '2026-11-01', '2026-11-30');
        $this->assertSame(['2026-11-25'], array_column($dates, 'date'));
    }

    public function test_viewers_cannot_add_or_remove_dates(): void
    {
        [$agency] = $this->workspace('viewer');
        $row = MarketDate::create(['agency_id' => $agency->id, 'country' => 'SA', 'kind' => 'payday', 'day_of_month' => 27]);
        Livewire::test(MarketDatesPage::class)->assertActionHidden('addPayday')->call('remove', $row->id)->assertForbidden();
        $this->assertTrue($row->fresh() !== null);
    }
}
