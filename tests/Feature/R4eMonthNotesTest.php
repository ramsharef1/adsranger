<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\User;
use App\Support\MonthlyReport;
use App\Support\MonthNotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R4: XS-03 / FS-20 "what we did" + "next month" notes, drafted from the app, edited, and printed in the monthly report. */
class R4eMonthNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_reflects_the_month_and_saved_notes_reach_the_report(): void
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4e', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active', 'monthly_budget' => 1000]);
        $c->update(['monthly_budget' => 1500]);
        Alert::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'rule_key' => 'spend_spike', 'severity' => 'high', 'status' => 'resolved', 'resolved_at' => now(), 'title' => 'x', 'fingerprint' => 'a1']);
        ClientContact::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'kind' => 'meeting', 'happened_on' => now()->toDateString()]);
        $month = now()->format('Y-m');

        $d = MonthNotes::draft($c, $month, 'en');
        $this->assertStringContainsString('Handled 1 account alerts.', $d['done']);
        $this->assertStringContainsString('including 1 meetings', $d['done']);
        $this->assertStringContainsString('Updated the monthly budget.', $d['done']);
        $this->assertStringContainsString('التنبيهات التي عالجناها على الحسابات الإعلانية: 1.', MonthNotes::draft($c, $month, 'ar')['done']);

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(ViewClient::class, ['record' => $c->id])
            ->mountAction('monthNotes')->assertActionDataSet(['month' => $month])
            ->setActionData(['month' => $month, 'done' => '• Launched the winter sale', 'next' => '• Ramadan creative'])->callMountedAction()->assertHasNoActionErrors();

        $html = view('reports.monthly-pdf', MonthlyReport::data($c->fresh(), $month, 'en'))->render();
        $this->assertStringContainsString('What we did this month', $html);
        $this->assertStringContainsString('Launched the winter sale', $html);
        $this->assertStringContainsString('Ramadan creative', $html);
    }
}
