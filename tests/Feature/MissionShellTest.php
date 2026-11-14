<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\User;
use App\Support\MissionShell;
use App\Support\Shell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mission control shell (D-312): behind mission_shell + panel_shell; each person can keep the classic look; simple mode is shorter. */
class MissionShellTest extends TestCase
{
    use RefreshDatabase;

    private const MARKS = ['data-mission-tokens', 'data-mission-theme', 'data-mission-rail', 'data-mission-next', 'data-mission-dock', 'data-mission-mode'];

    private function workspace(array $overrides = []): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-mission', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'language' => 'en']);
        $agency->forceFill(['module_overrides' => $overrides])->save();
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);

        return [$agency, $owner];
    }

    private function page(Agency $agency, User $owner, string $path = 'desk'): string
    {
        Shell::forget();

        return $this->withSession(['locale' => 'en'])->actingAs($owner->fresh())->get("/app/{$agency->slug}/$path")->assertOk()->getContent();
    }

    public function test_switch_off_changes_nothing(): void
    {
        $this->assertFalse((bool) config('modules.enabled.mission_shell'));
        [$agency, $owner] = $this->workspace(['panel_shell' => true]);
        $html = $this->page($agency, $owner);
        $this->assertStringContainsString('data-shell-areas', $html);
        foreach (self::MARKS as $mark) {
            $this->assertStringNotContainsString($mark, $html);
        }
    }

    public function test_it_needs_the_panel_shell_too(): void
    {
        [$agency, $owner] = $this->workspace(['mission_shell' => true]);
        $html = $this->page($agency, $owner);
        foreach (self::MARKS as $mark) {
            $this->assertStringNotContainsString($mark, $html);
        }
    }

    public function test_switch_on_shows_rail_dock_next_step_and_mode(): void
    {
        [$agency, $owner] = $this->workspace(['panel_shell' => true, 'mission_shell' => true]);
        $calm = Client::create(['agency_id' => $agency->id, 'name' => 'Calm Co', 'status' => 'active', 'currency' => 'USD']);
        $hot = Client::create(['agency_id' => $agency->id, 'name' => 'Hot Co', 'status' => 'active', 'currency' => 'USD']);
        Alert::create(['agency_id' => $agency->id, 'client_id' => $calm->id, 'rule_key' => 'x', 'severity' => 'warning', 'title' => 'Slow week', 'fingerprint' => 'a', 'status' => 'open']);
        Alert::create(['agency_id' => $agency->id, 'client_id' => $hot->id, 'rule_key' => 'y', 'severity' => 'critical', 'title' => 'Spend is twice the plan', 'fingerprint' => 'b', 'status' => 'open']);

        $html = $this->page($agency, $owner);
        foreach (self::MARKS as $mark) {
            $this->assertStringContainsString($mark, $html);
        }
        $this->assertMatchesRegularExpression('#data-mission-next.*?Critical.*?Spend is twice the plan.*?Hot Co#s', $html, 'the most severe alert is the next step');
        $this->assertMatchesRegularExpression('#data-mission-dock>.*?Hot Co.*?Calm Co#s', $html, 'clients with the worst alerts come first in the dock');
        $this->assertMatchesRegularExpression('#data-mission-rail>.*?data-locked.*?Agency office#s', $html, 'an add-on that is off shows as locked');
        $this->assertStringNotContainsString('First steps', $html, 'the first-steps list is for simple mode');
    }

    public function test_next_step_follows_the_client_picked_in_the_top_bar_and_its_link_opens(): void
    {
        [$agency, $owner] = $this->workspace(['panel_shell' => true, 'mission_shell' => true]);
        $calm = Client::create(['agency_id' => $agency->id, 'name' => 'Calm Co', 'status' => 'active', 'currency' => 'USD']);
        $hot = Client::create(['agency_id' => $agency->id, 'name' => 'Hot Co', 'status' => 'active', 'currency' => 'USD']);
        $slow = Alert::create(['agency_id' => $agency->id, 'client_id' => $calm->id, 'rule_key' => 'x', 'severity' => 'warning', 'title' => 'Slow week', 'fingerprint' => 'a', 'status' => 'open']);
        Alert::create(['agency_id' => $agency->id, 'client_id' => $hot->id, 'rule_key' => 'y', 'severity' => 'critical', 'title' => 'Spend is twice the plan', 'fingerprint' => 'b', 'status' => 'open']);

        $html = $this->page($agency, $owner, 'desk?client='.$calm->id);
        $this->assertMatchesRegularExpression('#data-mission-next.*?Slow week.*?Calm Co#s', $html, 'with a client picked, the next step is that client\'s');
        $this->assertStringContainsString("/app/{$agency->slug}/alerts/{$slow->id}", $html);
        $this->actingAs($owner)->get("/app/{$agency->slug}/alerts/{$slow->id}")->assertOk();
    }

    public function test_simple_mode_shortens_the_rail_and_shows_first_steps(): void
    {
        [$agency, $owner] = $this->workspace(['panel_shell' => true, 'mission_shell' => true, 'agency_ops_hub' => true]);
        $this->actingAs($owner)->post("/app/{$agency->slug}/ui/prefs", ['mode' => 'simple'])->assertRedirect();
        $this->assertSame('simple', $owner->fresh()->ui_prefs['mode']);

        $html = $this->page($agency, $owner);
        $this->assertStringContainsString('First steps', $html);
        $this->assertStringContainsString('Add your first client', $html);
        $this->assertDoesNotMatchRegularExpression('#data-mission-rail>.*?data-locked.*?</nav>#s', $html, 'locked add-ons are left out in simple mode');
        $this->assertMatchesRegularExpression('#data-mission-rail>.*?Agency office.*?</nav>#s', $html, 'an add-on that is on stays');
    }

    public function test_a_person_can_keep_the_classic_look(): void
    {
        [$agency, $owner] = $this->workspace(['panel_shell' => true, 'mission_shell' => true]);
        $this->actingAs($owner)->post("/app/{$agency->slug}/ui/prefs", ['look' => 'classic'])->assertRedirect();
        $html = $this->page($agency, $owner);
        $this->assertStringContainsString('data-shell-areas', $html);
        foreach (self::MARKS as $mark) {
            $this->assertStringNotContainsString($mark, $html);
        }
        $this->actingAs($owner->fresh());
        $this->assertTrue(MissionShell::look() === 'classic');
    }

    public function test_prefs_refuse_unknown_values(): void
    {
        [$agency, $owner] = $this->workspace(['panel_shell' => true, 'mission_shell' => true]);
        $this->actingAs($owner)->post("/app/{$agency->slug}/ui/prefs", ['mode' => 'wizard'])->assertSessionHasErrors('mode');
        $this->assertArrayNotHasKey('mode', (array) $owner->fresh()->ui_prefs);
    }
}
