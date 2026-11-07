<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\User;
use App\Support\PlanGate;
use App\Support\PlanLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** #177 Solo plan (D-140 / D-184 / D-187) + owner invite for a new workspace. */
class SoloPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_plan_has_its_limits_networks_and_no_monthly_pdf(): void
    {
        $solo = DB::table('plans')->where('key', 'solo')->first();
        $this->assertNotNull($solo);
        $this->assertSame([1, 3, 5], [(int) $solo->seats, (int) $solo->ad_accounts, (int) $solo->clients]);
        $starter = DB::table('plans')->where('key', 'starter')->value('id');
        $this->assertEqualsCanonicalizing(
            DB::table('ad_network_plan')->where('plan_id', $starter)->pluck('ad_network_id')->all(),
            DB::table('ad_network_plan')->where('plan_id', $solo->id)->pluck('ad_network_id')->all());

        $a = Agency::create(['name' => 'Own Biz', 'slug' => 'own-biz', 'plan' => 'solo', 'status' => 'active', 'currency' => 'USD']);
        $this->assertSame('solo', PlanGate::tier($a));
        $this->assertSame(1, PlanLimits::seatsLimit($a));
        $this->assertFalse(PlanGate::allows($a, 'monthly_pdf'));
        $this->assertFalse(PlanGate::allows($a, 'scheduled_reports'));
        $this->assertTrue(PlanGate::allows($a, 'some_ungated_feature'));

        $c = Client::create(['agency_id' => $a->id, 'name' => 'My brand', 'currency' => 'USD', 'status' => 'active']);
        $link = ClientLink::issue($c, null, null, null);
        $token = $link->token;
        $this->get('/r/'.$token)->assertOk()->assertDontSee('/pdf', false);
        $this->get('/r/'.$token.'/pdf')->assertNotFound();

        $a->update(['plan' => 'starter']);
        $this->assertTrue(PlanGate::allows($a->fresh(), 'monthly_pdf'));
    }

    public function test_an_owner_invite_makes_the_invitee_owner_of_the_new_workspace(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $a = Agency::create(['name' => 'Invited workspace', 'slug' => 'invited-ws', 'plan' => 'solo', 'status' => 'active', 'currency' => 'USD', 'signup_source' => 'invite']);
        $invite = AgencyInvitation::send($a, 'Owner@Example.com', 'owner', $admin);
        $this->assertSame('owner@example.com', $invite->email);

        $u = User::factory()->create(['email' => 'owner@example.com']);
        $invite->accept($u);

        $a->refresh();
        $this->assertSame($u->id, $a->owner_id);
        $this->assertSame('owner', $u->roleIn($a));
        $this->assertFalse($a->members()->whereKey($admin->id)->exists());
    }
}
