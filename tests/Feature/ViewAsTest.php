<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** #71 "View as" role & member preview switcher in the workspace context bar and MemberResource. */
class ViewAsTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        config(['modules.enabled.view_as' => true]); // D-163: held back by default; these tests cover the feature itself
        $this->agency = Agency::create([
            'name' => 'Preview Agency',
            'slug' => 'preview',
            'plan' => 'pro',
            'status' => 'active',
            'language' => 'en',
        ]);
    }

    private function member(string $role, string $name = 'Member'): User
    {
        $u = User::factory()->create(['name' => $name]);
        DB::table('agency_user')->where('user_id', $u->id)->delete();
        $this->agency->members()->attach($u->id, ['role' => $role]);

        return $u;
    }

    public function test_owner_can_preview_as_analyst_role_and_exit_preview(): void
    {
        $owner = $this->member('owner', 'Owner User');

        $r = $this->withSession(['locale' => 'en'])->actingAs($owner)->get('/app/preview/desk?view_as=r:analyst');
        $r->assertOk()
            ->assertSee('data-context-view-as', false)
            ->assertSee('Exit preview');

        $this->assertSame('analyst', $owner->roleIn($this->agency));
        $this->assertSame('owner', $owner->rawRoleIn($this->agency));

        $rOff = $this->withSession(['locale' => 'en'])->actingAs($owner)->get('/app/preview/desk?view_as=off');
        $rOff->assertOk()->assertDontSee('Exit preview');
        $this->assertSame('owner', $owner->roleIn($this->agency));
    }

    public function test_owner_previewing_as_restricted_member_sees_only_their_clients(): void
    {
        $owner = $this->member('owner', 'Owner User');
        $managerA = $this->member('manager', 'Sara Manager');
        $managerB = $this->member('manager', 'Omar Manager');

        $clientA = Client::create(['agency_id' => $this->agency->id, 'name' => 'Alpha Brand', 'currency' => 'USD', 'status' => 'active']);
        $clientB = Client::create(['agency_id' => $this->agency->id, 'name' => 'Beta Brand', 'currency' => 'USD', 'status' => 'active']);
        $clientA->users()->attach($managerA->id);
        $clientB->users()->attach($managerB->id);

        // Before preview: owner sees both Alpha Brand and Beta Brand
        $allNames = Client::visibleTo($owner, $this->agency)->pluck('name')->all();
        $this->assertContains('Alpha Brand', $allNames);
        $this->assertContains('Beta Brand', $allNames);

        // Activate ViewAs Sara Manager
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get('/app/preview/desk?view_as=u:'.$managerA->id)->assertOk();

        $scopedNames = Client::visibleTo($owner, $this->agency)->pluck('name')->all();
        $this->assertContains('Alpha Brand', $scopedNames);
        $this->assertNotContains('Beta Brand', $scopedNames);
    }

    public function test_analyst_cannot_use_view_as(): void
    {
        $analyst = $this->member('analyst', 'Read Only');
        $r = $this->withSession(['locale' => 'en'])->actingAs($analyst)->get('/app/preview/desk?view_as=r:admin');
        $r->assertOk()->assertDontSee('data-context-view-as', false);
        $this->assertSame('analyst', $analyst->roleIn($this->agency));
    }

    public function test_view_as_is_hidden_for_agencies_by_default(): void
    {
        config(['modules.enabled.view_as' => false, 'modules.preview_agencies' => []]);
        $owner = $this->member('owner', 'Owner User');
        $analyst = $this->member('analyst', 'Read Only');

        $r = $this->withSession(['locale' => 'en'])->actingAs($owner)->get('/app/preview/desk?view_as=u:'.$analyst->id);
        $r->assertOk()->assertDontSee('data-context-view-as', false)->assertDontSee('Exit preview');
        $this->assertSame('owner', $owner->roleIn($this->agency));
    }

    public function test_admin_cannot_preview_as_owner_or_another_admin(): void
    {
        $owner = $this->member('owner', 'Owner User');
        $admin = $this->member('admin', 'Admin User');
        $admin2 = $this->member('admin', 'Second Admin');

        foreach (['u:'.$owner->id, 'u:'.$admin2->id, 'r:admin'] as $target) {
            $this->withSession(['locale' => 'en'])->actingAs($admin)->get('/app/preview/desk?view_as='.$target)->assertOk();
            $this->assertSame('admin', $admin->roleIn($this->agency), $target);
        }

        // Lower roles still work for an admin.
        $this->withSession(['locale' => 'en'])->actingAs($admin)->get('/app/preview/desk?view_as=r:analyst')->assertOk();
        $this->assertSame('analyst', $admin->roleIn($this->agency));
    }

    public function test_stale_session_target_above_your_rights_is_dropped(): void
    {
        $owner = $this->member('owner', 'Owner User');
        $admin = $this->member('admin', 'Admin User');

        $this->withSession(['locale' => 'en', 'view_as.'.$this->agency->id => 'u:'.$owner->id])
            ->actingAs($admin)->get('/app/preview/desk')->assertOk()->assertDontSee('Exit preview');
        $this->assertSame('admin', $admin->roleIn($this->agency));
    }

    public function test_preview_mode_shows_banner_and_enforces_read_only_guard(): void
    {
        $owner = $this->member('owner', 'Owner User');
        $client = Client::create(['agency_id' => $this->agency->id, 'name' => 'Client One', 'currency' => 'USD', 'status' => 'active']);

        $r = $this->withSession(['locale' => 'en'])->actingAs($owner)->get('/app/preview/desk?view_as=r:manager');
        $r->assertOk()
            ->assertSee('data-view-as-banner', false)
            ->assertSee('Preview mode is read-only');

        // Create/Edit pages redirect away while previewing (UX-97 read-only preview)
        $this->withSession(['locale' => 'en', 'view_as.'.$this->agency->id => 'r:manager'])
            ->actingAs($owner)
            ->get('/app/preview/clients/create')
            ->assertRedirect('/app/preview/clients');

        $this->withSession(['locale' => 'en', 'view_as.'.$this->agency->id => 'r:manager'])
            ->actingAs($owner)
            ->get('/app/preview/clients/'.$client->id.'/edit')
            ->assertRedirect('/app/preview/clients');
    }
}
