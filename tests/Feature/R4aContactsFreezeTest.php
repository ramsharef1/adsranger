<?php

namespace Tests\Feature;

use App\Filament\Pages\DailyDesk;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Jobs\QueueScheduledReports;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ReportSchedule;
use App\Models\ReportSend;
use App\Models\User;
use App\Support\ClientFreeze;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R4 part 1 (D-303): XS-02 contact log + Desk follow-ups, XS-10 client pause with resume date. */
class R4aContactsFreezeTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4a', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR', 'status' => 'active']);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        return [$agency, $owner, $client];
    }

    public function test_contact_is_logged_and_its_follow_up_shows_on_the_desk_until_done(): void
    {
        [, , $client] = $this->workspace();
        Livewire::test(ViewClient::class, ['record' => $client->id])
            ->callAction('logContact', ['kind' => 'whatsapp', 'happened_on' => now()->subDays(3)->toDateString(), 'note' => 'Asked for the Ramadan plan', 'next_on' => now()->subDay()->toDateString()])
            ->assertHasNoActionErrors()->assertSee('Asked for the Ramadan plan');

        $c = ClientContact::firstOrFail();
        Livewire::test(DailyDesk::class)->assertSee('Follow-ups due')->assertSee('Asked for the Ramadan plan')->call('followUpDone', $c->id)->assertDontSee('Follow-ups due');
        $this->assertNotNull($c->fresh()->next_done_at);
    }

    public function test_a_new_contact_closes_the_follow_up_it_answers(): void
    {
        [, , $client] = $this->workspace();
        $old = ClientContact::create(['agency_id' => $client->agency_id, 'client_id' => $client->id, 'kind' => 'call', 'happened_on' => now()->subWeek()->toDateString(), 'next_on' => now()->subDay()->toDateString()]);
        Livewire::test(ViewClient::class, ['record' => $client->id])->callAction('logContact', ['kind' => 'call', 'happened_on' => now()->toDateString()]);
        $this->assertNotNull($old->fresh()->next_done_at);
    }

    public function test_paused_client_gets_no_scheduled_report_and_resumes_on_its_date(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        Livewire::test(ViewClient::class, ['record' => $client->id])
            ->callAction('pauseClient', ['reason' => 'unpaid', 'until' => now()->addDays(2)->toDateString()])->assertSee('Unpaid invoice');
        $client->refresh();
        $this->assertSame('paused', $client->status);

        config(['reports.email_enabled' => true]);
        ReportSchedule::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'weekly_day' => (int) now($agency->timezone)->isoWeekday(), 'lang' => 'en',
            'recipients' => ['client@example.com'], 'active' => true]);
        (new QueueScheduledReports)->handle();
        $this->assertSame(0, ReportSend::count(), 'nothing is queued for a paused client');
        $client->forceFill(['status' => 'active'])->save();
        (new QueueScheduledReports)->handle();
        $this->assertGreaterThan(0, ReportSend::count(), 'the same schedule queues once the client is active');
        ReportSend::query()->delete();
        $client->forceFill(['status' => 'paused'])->save();

        $this->travel(3)->days();
        $this->assertSame(1, ClientFreeze::resumeDue());
        $this->assertSame('active', $client->fresh()->status);
        $this->assertNull($client->fresh()->paused_reason);
    }
}
