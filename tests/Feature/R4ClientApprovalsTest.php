<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientApproval;
use App\Models\ClientLink;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** XS-06 (R4): the agency asks for an approval, the client answers on the report link, the agency sees who and when. */
class R4ClientApprovalsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-ap', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR', 'status' => 'active']);

        return [$agency, $owner, $client];
    }

    public function test_client_approves_on_the_report_link_and_the_agency_sees_who_and_when(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $link = ClientLink::issue($client);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(ViewClient::class, ['record' => $client->id])
            ->callAction('askApproval', ['kind' => 'budget', 'title' => 'Ramadan budget 12,000 SAR', 'details' => 'Split 70/30 Meta/Google'])
            ->assertHasNoActionErrors()->assertSee('Waiting for the client');

        $ap = ClientApproval::firstOrFail();
        auth()->logout();
        $page = $this->get('/r/'.$link->token.'?lang=en')->assertOk();
        $page->assertSee('Ramadan budget 12,000 SAR')->assertSee('Approve');

        $this->post('/r/'.$link->token.'/approvals/'.$ap->id, ['decision' => 'approved', 'name' => 'Dr. Sara', 'note' => 'Go ahead'])
            ->assertRedirect('/r/'.$link->token.'#approvals');
        $ap->refresh();
        $this->assertSame('approved', $ap->status);
        $this->assertSame('Dr. Sara', $ap->answered_by);
        $this->assertNotNull($ap->answered_at);

        $this->post('/r/'.$link->token.'/approvals/'.$ap->id, ['decision' => 'changes', 'name' => 'Someone else'])->assertNotFound();
        $this->assertSame('approved', $ap->fresh()->status, 'an answer is final');

        $this->actingAs($owner);
        Livewire::test(ViewClient::class, ['record' => $client->id])->assertSee('Approved by Dr. Sara')->assertSee('Go ahead');
    }

    public function test_a_link_cannot_answer_another_clients_approval(): void
    {
        [$agency, , $client] = $this->workspace();
        $other = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        $ap = ClientApproval::create(['agency_id' => $agency->id, 'client_id' => $other->id, 'kind' => 'plan', 'title' => 'Plan']);
        $link = ClientLink::issue($client);
        $this->post('/r/'.$link->token.'/approvals/'.$ap->id, ['decision' => 'approved', 'name' => 'X'])->assertNotFound();
        $this->post('/r/'.str_repeat('a', 40).'/approvals/'.$ap->id, ['decision' => 'approved', 'name' => 'X'])->assertNotFound();
        $this->assertSame('pending', $ap->fresh()->status);
    }
}
