<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Alert;
use App\Models\User;
use App\Support\DemoData;
use App\Support\Severity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** D-88 / UX-66: alert levels Critical / Warning / Info, with icon + text, data migrated from high / medium / low. */
class AlertLevelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_levels_normalize_legacy_values(): void
    {
        $this->assertSame('critical', Severity::normalize('high'));
        $this->assertSame('warning', Severity::normalize('MEDIUM'));
        $this->assertSame('info', Severity::normalize('low'));
        $this->assertSame('info', Severity::normalize(null));
        $this->assertSame('danger', Severity::color('critical'));
        $this->assertSame(['critical', 'warning', 'info'], array_keys(Severity::options()));
        foreach (config('alerts.rules') as $r) {
            $this->assertContains($r['severity'], Severity::LEVELS);
        }
    }

    public function test_migration_renames_stored_levels(): void
    {
        $a = Agency::create(['name' => 'Acme', 'slug' => 'acme-lv', 'plan' => 'starter', 'status' => 'active']);
        DemoData::seed($a, 5);
        $acct = DB::table('ad_accounts')->where('agency_id', $a->id)->first();
        DB::table('ad_accounts')->where('id', $acct->id)->update(['health' => json_encode([['high', 'x'], ['medium', 'y']])]);
        $al = Alert::create(['agency_id' => $a->id, 'rule_key' => 'pacing', 'severity' => 'medium', 'title' => 'T', 'status' => 'open', 'fingerprint' => 'f1', 'last_seen_at' => now()]);

        $m = require database_path('migrations/2026_11_22_000001_alert_levels_d88.php');
        $m->up();

        $this->assertSame('warning', $al->fresh()->severity);
        $this->assertSame([['critical', 'x'], ['warning', 'y']], json_decode(DB::table('ad_accounts')->where('id', $acct->id)->value('health'), true));
        $m->down();
        $this->assertSame('medium', $al->fresh()->severity);
    }

    public function test_desk_and_rules_show_new_levels_in_both_languages(): void
    {
        $a = Agency::create(['name' => 'Acme', 'slug' => 'acme-lv2', 'plan' => 'starter', 'status' => 'active']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'owner']);
        DemoData::seed($a, 5);
        $client = \App\Models\Client::where('agency_id', $a->id)->first();
        Alert::create(['agency_id' => $a->id, 'client_id' => $client->id, 'rule_key' => 'tracking_break', 'severity' => 'critical', 'title' => 'Pixel stopped', 'status' => 'open', 'fingerprint' => 'f2', 'last_seen_at' => now()]);

        $this->actingAs($u)->withSession(['locale' => 'ar'])->get("/app/{$a->slug}/desk")->assertOk()->assertSee('حرج');
        $this->actingAs($u)->withSession(['locale' => 'en'])->get("/app/{$a->slug}/clients/{$client->id}")->assertOk()->assertSee('Critical');
        $this->actingAs($u)->withSession(['locale' => 'en'])->get("/app/{$a->slug}/alert-rules")->assertOk()->assertSee('Warning')->assertDontSee('>Medium<', false);
    }
}
