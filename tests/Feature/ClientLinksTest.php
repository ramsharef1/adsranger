<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\User;
use App\Support\DemoData;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** #56 signed client links. */
class ClientLinksTest extends TestCase
{
    use RefreshDatabase;

    private function setUpAgency(string $role = 'owner'): array
    {
        $a = Agency::create(['name' => 'Acme Agency', 'slug' => 'acme', 'plan' => 'starter', 'status' => 'active', 'language' => 'en']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => $role]);
        DemoData::seed($a, 40);
        $client = Client::where('agency_id', $a->id)->orderBy('id')->first();

        return [$a, $u, $client];
    }

    public function test_link_opens_the_clients_report_without_login(): void
    {
        [$a, $u, $client] = $this->setUpAgency();
        $other = Client::where('agency_id', $a->id)->where('id', '!=', $client->id)->first();
        $link = ClientLink::issue($client, $u, 'Monthly');

        $this->get('/r/'.$link->token)->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee($client->name)
            ->assertSee('Prepared by Acme Agency')
            ->assertDontSee($other->name);

        $this->assertSame(1, $link->fresh()->views);
        $this->assertNotNull($link->fresh()->last_viewed_at);
    }

    public function test_arabic_report(): void
    {
        [, $u, $client] = $this->setUpAgency();
        $link = ClientLink::issue($client, $u);
        $this->get('/r/'.$link->token.'?lang=ar')->assertOk()->assertSee('dir="rtl"', false)->assertSee('الإنفاق')->assertSee('آخر 30 يومًا');
    }

    public function test_revoked_expired_and_wrong_tokens_are_not_found(): void
    {
        [, $u, $client] = $this->setUpAgency();
        $revoked = ClientLink::issue($client, $u);
        $revoked->forceFill(['revoked_at' => now()])->save();
        $expired = ClientLink::issue($client, $u, null, 30);
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get('/r/'.$revoked->token)->assertNotFound();
        $this->get('/r/'.$expired->token)->assertNotFound();
        $this->get('/r/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/r/short')->assertNotFound();
    }

    public function test_hidden_spend_stays_hidden(): void
    {
        [, $u, $client] = $this->setUpAgency();
        $client->update(['hide_spend' => true]);
        $link = ClientLink::issue($client, $u);
        $this->get('/r/'.$link->token)->assertOk()->assertDontSee('Cost per result')->assertDontSee('Monthly budget')->assertSee('Impressions');
    }

    public function test_token_is_stored_hashed_and_encrypted(): void
    {
        [, $u, $client] = $this->setUpAgency();
        $link = ClientLink::issue($client, $u);
        $raw = \DB::table('client_links')->where('id', $link->id)->first();
        $this->assertSame(hash('sha256', $link->token), $raw->token_hash);
        $this->assertStringNotContainsString($link->token, $raw->token);
    }

    public function test_managers_create_and_revoke_links_from_the_client_page(): void
    {
        [$a, $u, $client] = $this->setUpAgency('manager');
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])
            ->callAction('clientLinks', ['label' => 'Q4', 'days' => '30'])
            ->assertNotified();

        $link = ClientLink::where('client_id', $client->id)->first();
        $this->assertSame('Q4', $link->label);
        $this->assertTrue($link->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_link.created']);

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])->call('revokeLink', $link->id);
        $this->assertNotNull($link->fresh()->revoked_at);
    }

    public function test_analysts_cannot_create_or_revoke(): void
    {
        [$a, $u, $client] = $this->setUpAgency('analyst');
        $link = ClientLink::issue($client, null);
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])->call('revokeLink', $link->id)->assertForbidden();
        $this->assertNull($link->fresh()->revoked_at);
    }
}
