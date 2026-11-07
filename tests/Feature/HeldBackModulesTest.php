<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\CreativeBrief;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** ADSR-110 step 0.2 (D-149): held-back modules are off for agencies, previewable by super admins and preview workspaces. */
class HeldBackModulesTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create(['name' => 'Held Co', 'slug' => 'held-co', 'plan' => 'agency', 'status' => 'active', 'language' => 'en']);
    }

    private function member(string $role): User
    {
        $u = User::factory()->create();
        DB::table('agency_user')->where('user_id', $u->id)->delete();
        $this->agency->members()->attach($u->id, ['role' => $role]);

        return $u;
    }

    public function test_every_module_is_off_by_default(): void
    {
        foreach (array_keys(config('modules.enabled')) as $m) {
            $this->assertFalse(Modules::enabled($m), $m);
        }
    }

    public function test_agency_owner_cannot_open_held_back_pages(): void
    {
        $owner = $this->member('owner');
        foreach (['studio', 'organic-social', 'intelligence-hub', 'agency-ops'] as $p) {
            $this->assertContains($this->actingAs($owner)->get("/app/held-co/$p")->status(), [403, 404], $p);
        }
        $this->actingAs($owner)->get('/app/held-co/export-data')->assertOk()->assertDontSee('Delete workspace');
    }

    public function test_preview_workspace_and_enabled_module_open_the_page(): void
    {
        $owner = $this->member('owner');
        config(['modules.preview_agencies' => ['held-co']]);
        $this->assertTrue(Modules::on('studio', $owner, $this->agency));
        $this->assertTrue(Modules::previewOnly('studio', $owner, $this->agency));

        config(['modules.preview_agencies' => [], 'modules.enabled.organic' => true]);
        $this->assertTrue(Modules::on('organic', $owner, $this->agency));
        $this->assertFalse(Modules::on('studio', $owner, $this->agency));
    }

    public function test_super_admin_keeps_a_preview(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->assertTrue(Modules::on('studio', $admin, $this->agency));
        $this->assertTrue(Modules::on('self_serve_deletion', $admin, $this->agency));
    }

    public function test_studio_brief_link_is_not_found_while_studio_is_off(): void
    {
        $client = Client::create(['agency_id' => $this->agency->id, 'name' => 'Brief Client', 'currency' => 'USD', 'status' => 'active']);
        $brief = CreativeBrief::create([
            'agency_id' => $this->agency->id, 'client_id' => $client->id, 'title' => 'Test brief', 'primary_text' => 'Text', 'headline' => 'Headline',
            'status' => 'pending_approval', 'share_token' => Str::random(40),
        ]);
        $this->get('/studio/brief/'.$brief->share_token)->assertNotFound();
        $this->post('/studio/brief/'.$brief->share_token, ['decision' => 'approved'])->assertNotFound();
        $this->assertSame('pending_approval', $brief->fresh()->status);
    }
}
