<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\User;
use App\Support\Shell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PX-01 / PX-02 (D-302): the new shell sits behind the panel_shell switch; with it on, the sidebar follows the header area. */
class PanelShellTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-shell', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);

        return [$agency, $owner];
    }

    private function page(Agency $agency, User $owner, string $path): string
    {
        Shell::forget();

        return $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/$path")->assertOk()->getContent();
    }

    public function test_switch_off_changes_nothing(): void
    {
        [$agency, $owner] = $this->workspace();
        $html = $this->page($agency, $owner, 'desk');
        foreach (['data-shell-tokens', 'data-shell-areas', 'data-shell-ribbon', 'data-shell-bottom'] as $mark) {
            $this->assertStringNotContainsString($mark, $html);
        }
        $this->assertStringContainsString("/app/{$agency->slug}/utm-builder", $html, 'the full side menu');
    }

    public function test_switch_on_shows_areas_ribbon_bottom_bar_and_an_area_side_menu(): void
    {
        [$agency, $owner] = $this->workspace();
        config(['modules.preview_agencies' => ['acme-shell']]);

        $desk = $this->page($agency, $owner, 'desk');
        foreach (['data-shell-tokens', 'data-shell-areas', 'data-shell-ribbon', 'data-shell-bottom'] as $mark) {
            $this->assertStringContainsString($mark, $desk);
        }
        $this->assertStringNotContainsString("/app/{$agency->slug}/utm-builder", $desk, 'an Ads page is not in the Today side menu');
        $this->assertStringContainsString("/app/{$agency->slug}/plan", $desk, 'pages of no area stay reachable');

        $utm = $this->page($agency, $owner, 'utm-builder');
        $this->assertMatchesRegularExpression('#data-shell-areas>.*?aria-current="page"\s*>Ads</a>#s', $utm, 'Ads is the current area');
        $this->assertStringContainsString("/app/{$agency->slug}/landing-pages", $utm);
    }

    public function test_switcher_ribbon_status_density_and_account_menu(): void
    {
        [$agency, $owner] = $this->workspace();
        config(['modules.preview_agencies' => ['acme-shell']]);
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR', 'status' => 'active']);
        Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        Alert::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'severity' => 'high', 'status' => 'open', 'title' => 'Ad account stopped', 'rule_key' => 'spend_drop', 'fingerprint' => 'x1', 'entity_name' => 'Acct', 'last_seen_at' => now()]);

        $html = $this->page($agency, $owner, 'desk');
        $this->assertStringContainsString('data-shell-client-switcher', $html);
        $this->assertStringNotContainsString('data-context-client', $html, 'one client picker, in the header');
        $this->assertStringContainsString('1 of 2 clients without open Critical or Warning alerts', $html);
        $this->assertStringContainsString('Critical 1 · Warning 0', $html);
        $this->assertStringContainsString('Compact rows', $html);
        $this->assertStringNotContainsString('min-height:34px', $html);

        $owner->forceFill(['ui_prefs' => ['density' => 'compact']])->save();
        $this->assertStringContainsString('min-height:34px', $this->page($agency, $owner, 'desk'));
    }

    public function test_areas_follow_modules(): void
    {
        [$agency, $owner] = $this->workspace();
        $agency->forceFill(['module_overrides' => ['panel_shell' => true]])->save();
        $html = $this->page($agency, $owner, 'desk');
        $this->assertStringContainsString('data-shell-areas', $html);
        $this->assertDoesNotMatchRegularExpression('#data-shell-areas>.*?>SEO</a>.*?</nav>#s', $html, 'the SEO module is off, so no SEO area');
    }
}
