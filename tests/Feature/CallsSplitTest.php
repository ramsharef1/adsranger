<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\CallResults;
use App\Support\DataExport;
use App\Support\MetricsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** #12 Calls split: placed / confirmed / 20s+ (Starter). */
class CallsSplitTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $basis = 'placed'): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-calls-'.$basis, 'plan' => 'starter', 'status' => 'active']);
        $user = User::factory()->create();
        $agency->members()->attach($user->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Smile Clinic', 'currency' => 'JOD', 'call_result' => $basis]);
        $day = now()->subDays(3)->toDateString();
        // Meta call campaign: 10 placed, 7 confirmed, 4 connected 20s+; 100 spent.
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => $day, 'network' => 'meta', 'currency' => 'JOD',
            'result_type' => 'call', 'results' => 10, 'calls' => 10, 'calls_confirmed' => 7, 'calls_20s' => 4, 'spend' => 100, 'revenue' => 0]);
        // Google call conversions: one count only (5), 50 spent.
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => $day, 'network' => 'google', 'currency' => 'JOD',
            'result_type' => 'call', 'results' => 5, 'calls' => 5, 'spend' => 50, 'revenue' => 0]);

        return [$agency, $user, $client];
    }

    private function range(): array
    {
        return [now()->subDays(10)->toDateString(), now()->toDateString()];
    }

    public function test_default_counts_calls_placed(): void
    {
        [, , $c] = $this->workspace();
        $t = MetricsQuery::totals($c, ...$this->range());

        $this->assertSame('call', $t['main_type']);
        $this->assertEquals(15, $t['main_results']);
        $this->assertEquals(10.0, $t['cpr']);
    }

    public function test_client_basis_changes_meta_results_only(): void
    {
        [, , $c] = $this->workspace('20s');
        $t = MetricsQuery::totals($c, ...$this->range());
        $this->assertEquals(9, $t['main_results']);            // 4 Meta 20s+ calls + 5 Google calls
        $this->assertEquals(150 / 9, $t['cpr']);
        $this->assertEquals(15, $t['calls']);                  // raw counts unchanged

        $c->update(['call_result' => 'confirmed']);
        $this->assertEquals(12, MetricsQuery::totals($c->fresh(), ...$this->range())['main_results']);
        $this->assertSame('placed', CallResults::basis('nonsense'));
    }

    public function test_summary_gives_rates_and_costs(): void
    {
        [, , $c] = $this->workspace();
        $s = CallResults::summary(MetricsQuery::totals($c, ...$this->range()));

        $this->assertEquals(15, $s['placed']);
        $this->assertEquals(7, $s['confirmed']);
        $this->assertEquals(4, $s['connected']);
        $this->assertEqualsWithDelta(46.67, $s['confirm_rate'], 0.01);
        $this->assertEquals(10.0, $s['cost_placed']);
        $this->assertEquals(37.5, $s['cost_connected']);
        $this->assertTrue($s['split']);
        $this->assertNull(CallResults::summary(['calls' => 0, 'calls_confirmed' => 0, 'calls_20s' => 0, 'type_spend' => []]));
    }

    public function test_client_page_shows_calls_card_in_both_languages_and_export_has_columns(): void
    {
        [$a, $u, $c] = $this->workspace('20s');
        $this->actingAs($u)->withSession(['locale' => 'en'])->get("/app/{$a->slug}/clients/{$c->id}")
            ->assertOk()->assertSee('Counted as the result: Calls 20s+')->assertSee('47% of calls placed');
        $this->actingAs($u)->withSession(['locale' => 'ar'])->get("/app/{$a->slug}/clients/{$c->id}")
            ->assertOk()->assertSee('المحتسب نتيجةً: مكالمات 20 ثانية فأكثر');
        $this->actingAs($u)->withSession(['locale' => 'en'])->get("/app/{$a->slug}/clients/{$c->id}/edit")
            ->assertOk()->assertSee('Count calls as');

        app()->setLocale('en');
        $out = fopen('php://memory', 'w+');
        DataExport::writeMetrics($out, $a, null, ...$this->range());
        rewind($out);
        $csv = stream_get_contents($out);
        $this->assertStringContainsString('Calls confirmed', $csv);
        $this->assertStringContainsString('Calls 20s+', $csv);
    }
}
