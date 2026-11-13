<?php

namespace Tests\Feature;

use App\Filament\Pages\DailyDesk;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\ApprovalRequest;
use App\Models\Client;
use App\Models\ClientLicence;
use App\Models\User;
use App\Support\TwoPerson;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R4 (D-305): DS-35 two-person rule for risky actions, XS-24 / NB-36 licences with expiry reminders. */
class R4fTwoPersonLicencesTest extends TestCase
{
    use RefreshDatabase;

    private function as(Agency $agency, User $u): void
    {
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
    }

    private function workspace(bool $second): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4f', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $admin = null;
        if ($second) {
            $admin = User::factory()->create();
            $agency->members()->attach($admin->id, ['role' => 'admin']);
        }
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);

        return [$agency, $owner, $admin, $client];
    }

    public function test_one_person_agency_deletes_after_typing_the_name(): void
    {
        [$agency, $owner, , $client] = $this->workspace(false);
        $this->as($agency, $owner);
        Livewire::test(EditClient::class, ['record' => $client->id])->assertActionHidden('askDelete')
            ->callAction('delete', ['confirm' => 'wrong'])->assertHasActionErrors(['confirm']);
        $this->assertNotNull(Client::find($client->id));
        Livewire::test(EditClient::class, ['record' => $client->id])->callAction('delete', ['confirm' => 'Zad Store']);
        $this->assertNull(Client::find($client->id));
    }

    public function test_second_admin_must_approve_and_the_approval_is_used_once(): void
    {
        [$agency, $owner, $admin, $client] = $this->workspace(true);
        $this->as($agency, $owner);
        Livewire::test(EditClient::class, ['record' => $client->id])->assertActionHidden('delete')->callAction('askDelete');
        $r = ApprovalRequest::firstOrFail();
        $this->assertFalse(TwoPerson::decide($r, $owner, true), 'the requester cannot approve their own request');

        $this->as($agency, $admin);
        Livewire::test(DailyDesk::class)->assertSee('Waiting for your approval')->call('decideApproval', $r->id, true);
        $this->assertSame('approved', $r->fresh()->status);

        $this->as($agency, $owner);
        Livewire::test(EditClient::class, ['record' => $client->id])->assertActionVisible('delete')->callAction('delete');
        $this->assertNull(Client::find($client->id));
        $this->assertSame('used', $r->fresh()->status);
    }

    public function test_workspace_download_is_refused_without_approval_when_two_admins(): void
    {
        [$agency, $owner] = $this->workspace(true);
        $this->actingAs($owner)->get("/app/{$agency->slug}/export/workspace.zip")->assertForbidden();
    }

    public function test_licence_shows_on_the_client_and_on_the_desk_before_it_expires(): void
    {
        [$agency, $owner, , $client] = $this->workspace(false);
        $this->as($agency, $owner);
        Livewire::test(ViewClient::class, ['record' => $client->id])
            ->callAction('addLicence', ['kind' => 'real_estate', 'number' => 'FAL-1200012345', 'expires_on' => now()->addDays(20)->toDateString()])
            ->assertHasNoActionErrors()->assertSee('FAL-1200012345');
        Livewire::test(DailyDesk::class)->assertSee('Licences to renew')->assertSee('FAL-1200012345');

        ClientLicence::query()->update(['expires_on' => now()->addDays(90)->toDateString()]);
        Livewire::test(DailyDesk::class)->assertDontSee('Licences to renew');
    }
}
