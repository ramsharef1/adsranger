<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\Lead;
use App\Models\LeadDeskLink;
use App\Models\User;
use App\Support\LeadDesk;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R4 (D-306): XS-13 speed-to-lead and XS-23 WhatsApp results, from a no-login lead page behind the lead_desk switch. */
class R4kLeadDeskTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4k', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Noor Clinic', 'currency' => 'SAR', 'status' => 'active']);

        return [$agency, $owner, $c];
    }

    public function test_front_desk_logs_without_login_and_phone_numbers_are_never_stored(): void
    {
        [$agency, $owner, $c] = $this->workspace();
        $link = LeadDeskLink::issue($c, $owner);
        $this->get('/l/'.$link->token)->assertNotFound(); // switch off
        config(['modules.preview_agencies' => ['acme-r4k']]);

        $this->get('/l/'.$link->token)->assertOk()->assertSee('Noor Clinic')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post('/l/'.$link->token, ['channel' => 'whatsapp', 'label' => 'Sara 0501234567'])->assertRedirect();
        $lead = Lead::firstOrFail();
        $this->assertSame('Sara', $lead->label);
        $this->assertSame('desk', $lead->logged_via);

        $this->travel(20)->minutes();
        $this->post('/l/'.$link->token.'/'.$lead->id, ['mark' => 'sale', 'value' => 350])->assertRedirect();
        $lead->refresh();
        $this->assertSame('sale', $lead->outcome);
        $this->assertSame(20, (int) $lead->received_at->diffInMinutes($lead->contacted_at));

        $old = $link->token;
        LeadDeskLink::issue($c, $owner);
        $this->get('/l/'.$old)->assertNotFound(); // a new link stops the old one
    }

    public function test_summary_gives_response_time_and_cost_per_sale_on_the_client_page(): void
    {
        [$agency, $owner, $c] = $this->workspace();
        config(['modules.preview_agencies' => ['acme-r4k']]);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'date' => now()->subDays(2)->toDateString(), 'network' => 'meta', 'currency' => 'SAR', 'spend' => 600, 'results' => 10]);
        foreach ([[10, 'sale'], [90, 'qualified'], [null, null]] as $i => [$mins, $out]) {
            Lead::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'channel' => $i < 2 ? 'whatsapp' : 'call', 'received_at' => now()->subDays(2),
                'contacted_at' => $mins ? now()->subDays(2)->addMinutes($mins) : null, 'outcome' => $out]);
        }
        $s = LeadDesk::summary($c, now()->subDays(7)->toDateString(), now()->toDateString());
        $this->assertSame(3, $s['leads']);
        $this->assertSame(33.3, $s['within_hour']);
        $this->assertSame(10, $s['median_minutes']);
        $this->assertSame(2, $s['whatsapp_qualified']);
        $this->assertSame(600.0, $s['cost_per_sale']);

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(ViewClient::class, ['record' => $c->id])->assertSee('Leads and response time')->assertSee('Answered within 1 hour')
            ->callAction('logLead', ['channel' => 'call', 'label' => 'Walk-in'])->assertHasNoActionErrors();
        $this->assertSame('app', Lead::latest('id')->value('logged_via'));
    }
}
