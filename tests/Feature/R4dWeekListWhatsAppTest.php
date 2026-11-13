<?php

namespace Tests\Feature;

use App\Filament\Pages\DailyDesk;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\ClientSummaryText;
use App\Support\WeekList;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R4 (D-303): XS-01 this week's clients on the Desk, XS-08 copy as WhatsApp message. */
class R4dWeekListWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4d', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);

        return [$agency, $owner];
    }

    public function test_week_list_ranks_critical_first_and_names_quiet_clients(): void
    {
        [$agency, $owner] = $this->workspace();
        $quiet = Client::create(['agency_id' => $agency->id, 'name' => 'Quiet Co', 'currency' => 'SAR', 'status' => 'active']);
        $quiet->forceFill(['created_at' => now()->subDays(40)])->save();
        $hot = Client::create(['agency_id' => $agency->id, 'name' => 'Hot Co', 'currency' => 'SAR', 'status' => 'active']);
        ClientContact::create(['agency_id' => $agency->id, 'client_id' => $hot->id, 'kind' => 'call', 'happened_on' => now()->subDays(2)->toDateString()]);
        Alert::create(['agency_id' => $agency->id, 'client_id' => $hot->id, 'rule_key' => 'spend_spike', 'severity' => 'high', 'status' => 'open', 'title' => 'x', 'fingerprint' => 'a1']);
        $fine = Client::create(['agency_id' => $agency->id, 'name' => 'Fine Co', 'currency' => 'SAR', 'status' => 'active']);

        $list = WeekList::for($agency, $owner);
        $this->assertSame(['Hot Co', 'Quiet Co'], array_map(fn ($w) => $w['client']->name, $list));
        $this->assertSame(['1 open Critical alert'], $list[0]['reasons']);
        $this->assertSame(['No contact logged yet'], $list[1]['reasons']);

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(DailyDesk::class)->assertSee("This week's clients")->assertSee('Hot Co')->assertDontSee('Fine Co ·');
    }

    public function test_whatsapp_text_respects_hide_spend_and_shows_change(): void
    {
        [$agency, $owner] = $this->workspace();
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active', 'target_cpr' => 10]);
        foreach ([3 => 100, 10 => 50] as $ago => $spend) {
            DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'date' => now()->subDays($ago)->toDateString(), 'network' => 'meta', 'currency' => 'SAR', 'spend' => $spend, 'purchases' => 10, 'results' => 10]);
        }
        $from = now()->subDays(7)->toDateString();
        $to = now()->subDay()->toDateString();

        $en = ClientSummaryText::make($c, $from, $to, 'en');
        $this->assertStringContainsString('Spend: 100.00 SAR (+100% vs the period before)', $en);
        $this->assertStringContainsString('target 10.00 SAR', $en);
        $this->assertStringNotContainsString('AdsRanger', $en);
        $this->assertStringContainsString('الإنفاق: 100.00 SAR', ClientSummaryText::make($c, $from, $to, 'ar'));

        $c->forceFill(['hide_spend' => true])->save();
        $this->assertStringNotContainsString('Spend', ClientSummaryText::make($c->fresh(), $from, $to, 'en'));

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(ViewClient::class, ['record' => $c->id])->mountAction('copyWhatsApp')->assertSee('Zad Store');
    }
}
