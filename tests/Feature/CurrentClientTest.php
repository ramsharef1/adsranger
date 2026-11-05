<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\User;
use App\Support\CurrentClient;
use App\Support\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** I-X-1: top bar with workspace + role (UX-82), current client (UX-81) and data freshness (UX-83, #10). */
class CurrentClientTest extends TestCase
{
    use RefreshDatabase;

    private function setUpAgency(string $role = 'owner'): array
    {
        $a = Agency::create(['name' => 'Acme Agency', 'slug' => 'acme-cc', 'plan' => 'agency', 'status' => 'active', 'timezone' => 'UTC']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => $role]);
        DemoData::seed($a, 10);
        $clients = Client::where('agency_id', $a->id)->where('status', 'active')->orderBy('id')->get();

        return [$a, $u, $clients[0], $clients[1]];
    }

    private function viewUrl(Agency $a, Client $c): string
    {
        return "/app/{$a->slug}/clients/{$c->id}";
    }

    public function test_top_bar_shows_workspace_role_and_client_chip(): void
    {
        [$a, $u] = $this->setUpAgency('analyst');
        $this->actingAs($u)->withSession(['locale' => 'ar'])->get("/app/{$a->slug}/desk")->assertOk()
            ->assertSee('data-context-bar', false)
            ->assertSee('Acme Agency')
            ->assertSee('المحلل (عرض فقط)')
            ->assertSee('كل العملاء');
        $this->actingAs($u)->withSession(['locale' => 'en'])->get("/app/{$a->slug}/desk")->assertOk()
            ->assertSee('Analyst (read only)')->assertSee('All clients');
    }

    public function test_choosing_a_client_filters_the_desk_and_is_remembered_until_cleared(): void
    {
        [$a, $u, $one, $two] = $this->setUpAgency();
        $this->actingAs($u);

        $this->get("/app/{$a->slug}/desk?client={$one->id}")->assertOk()
            ->assertSee($this->viewUrl($a, $one))->assertDontSee($this->viewUrl($a, $two));
        // Kept in the session: the next page without ?client= is still filtered.
        $this->get("/app/{$a->slug}/desk")->assertOk()
            ->assertSee($this->viewUrl($a, $one))->assertDontSee($this->viewUrl($a, $two));
        $this->get("/app/{$a->slug}/desk?client=all")->assertOk()
            ->assertSee($this->viewUrl($a, $one))->assertSee($this->viewUrl($a, $two));
    }

    public function test_a_client_the_member_cannot_see_is_ignored(): void
    {
        [$a, $u, $one, $two] = $this->setUpAgency('manager');
        $one->users()->attach($u->id);     // manager restricted to $one
        $other = User::factory()->create();
        $a->members()->attach($other->id, ['role' => 'manager']);
        $two->users()->attach($other->id);
        $this->actingAs($u);
        $this->get("/app/{$a->slug}/desk?client={$two->id}")->assertOk();
        $this->assertNull(CurrentClient::id($a));
    }

    public function test_alerts_follow_the_current_client(): void
    {
        [$a, $u, $one, $two] = $this->setUpAgency();
        Alert::where('agency_id', $a->id)->delete();
        foreach ([$one, $two] as $i => $c) {
            Alert::create(['agency_id' => $a->id, 'client_id' => $c->id, 'rule_key' => 'tracking_break', 'severity' => 'critical', 'title' => 'T'.$i, 'status' => 'open', 'fingerprint' => 'cc'.$i, 'last_seen_at' => now()]);
        }
        $this->actingAs($u);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('app'));
        \Filament\Facades\Filament::setTenant($a);
        $this->assertSame(2, \App\Filament\Resources\Alerts\AlertResource::getEloquentQuery()->count());
        CurrentClient::remember($one->id, $a);
        $this->assertSame(['T0'], \App\Filament\Resources\Alerts\AlertResource::getEloquentQuery()->pluck('title')->all());
    }

    public function test_freshness_names_the_oldest_account_and_turns_amber_after_a_day(): void
    {
        [$a, $u, $one] = $this->setUpAgency();
        $this->actingAs($u);
        AdAccount::where('agency_id', $a->id)->update(['is_active' => true, 'client_id' => $one->id]);
        $accts = AdAccount::where('agency_id', $a->id)->get();
        $this->assertNotEmpty($accts);
        AdAccount::whereIn('id', $accts->pluck('id'))->update(['last_synced_at' => now()->subHours(2)]);
        $f = CurrentClient::freshness($a);
        $this->assertFalse($f['stale']);
        $this->assertTrue($f['at']->diffInMinutes(now()) >= 119);

        $old = $accts->first();
        $old->forceFill(['last_synced_at' => now()->subDays(2)])->save();
        $f = CurrentClient::freshness($a);
        $this->assertTrue($f['stale']);
        $this->assertStringContainsString((string) ($old->name ?: $old->external_id), $f['oldest']);

        $this->withSession(['locale' => 'ar'])->get("/app/{$a->slug}/desk")->assertOk()->assertSee('آخر تحديث للبيانات');
    }
}
