<?php

namespace Tests\Feature;

use App\Filament\Pages\CreativeFatigueBoardPage;
use App\Filament\Pages\StudioPage;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Connection;
use App\Models\CreativeFatigueAlert;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\MenaCalendar;
use App\Support\Studio\CreativeFatigueDetector;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** E2-066 creative fatigue & peak-season decay guard (module studio, off). */
class CreativeFatigueGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Agency, 1: User, 2: Client} */
    private function workspace(string $slug, string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $agency = Agency::create(['name' => 'Agency '.$slug, 'slug' => $slug, 'plan' => 'pro', 'status' => 'active', 'signup_source' => 'admin', 'owner_id' => $user->id, 'language' => 'ar', 'timezone' => 'Asia/Riyadh', 'country' => 'SA']);
        DB::table('agency_user')->where('user_id', $user->id)->delete();
        $agency->members()->attach($user->id, ['role' => $role]);

        return [$agency, $user, Client::create(['agency_id' => $agency->id, 'name' => 'Client '.$slug, 'currency' => 'KWD', 'status' => 'active'])];
    }

    /** Baseline days 8..28 before $end; recent days 0..6. Per-day numbers. */
    private function ad(Client $client, string $ref, Carbon $end, array $base, array $recent): void
    {
        foreach (range(0, 27) as $i) {
            $m = $i <= 6 ? $recent : $base;
            DailyMetric::create(['agency_id' => $client->agency_id, 'client_id' => $client->id, 'level' => 'ad', 'ad_ref' => $ref, 'network' => 'meta',
                'date' => $end->copy()->subDays($i)->toDateString(), 'currency' => 'KWD', 'spend' => $m['spend'] ?? 10, 'impressions' => $m['impr'], 'reach' => $m['reach'],
                'clicks' => $m['clicks'], 'link_clicks' => $m['clicks'], 'thruplays' => $m['tp'] ?? 0]);
        }
    }

    public function test_frequency_and_ctr_decay_mark_ads_tired_and_peaks_tighten_the_limits(): void
    {
        [, , $client] = $this->workspace('fatigue-core');
        $end = Carbon::parse('2026-10-20');
        // Tired: freq 1000/250 = 4, CTR 2% → 1.2% (−40%).
        $this->ad($client, 'ad_tired', $end, ['impr' => 1000, 'reach' => 800, 'clicks' => 20], ['impr' => 1000, 'reach' => 250, 'clicks' => 12, 'spend' => 7.125]);
        // Watch: CTR −20% only → nothing in normal season, "watch" in a peak window.
        $this->ad($client, 'ad_mild', $end, ['impr' => 1000, 'reach' => 900, 'clicks' => 20], ['impr' => 1000, 'reach' => 900, 'clicks' => 16]);
        // Healthy and too small.
        $this->ad($client, 'ad_ok', $end, ['impr' => 1000, 'reach' => 900, 'clicks' => 20], ['impr' => 1000, 'reach' => 900, 'clicks' => 21]);
        $this->ad($client, 'ad_tiny', $end, ['impr' => 20, 'reach' => 19, 'clicks' => 1], ['impr' => 10, 'reach' => 2, 'clicks' => 0]);
        $net = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta Ads', 'category' => 'social', 'stage' => 1, 'auth_type' => 'oauth', 'is_active' => true]);
        $conn = Connection::create(['agency_id' => $client->agency_id, 'ad_network_id' => $net->id, 'status' => 'active']);
        $acct = AdAccount::create(['agency_id' => $client->agency_id, 'client_id' => $client->id, 'ad_network_id' => $net->id, 'connection_id' => $conn->id, 'external_id' => 'act_1', 'name' => 'Meta 1', 'currency' => 'KWD', 'is_active' => true]);
        Ad::create(['agency_id' => $client->agency_id, 'ad_account_id' => $acct->id, 'external_id' => 'ad_tired', 'name' => 'Oud video 15s']);

        $normal = CreativeFatigueDetector::evaluate($client, $end);
        $this->assertNull($normal['peak']);
        $this->assertSame(['ad_tired'], array_column($normal['rows'], 'ad_ref'));
        $tired = $normal['rows'][0];
        $this->assertSame(['fatigued', 4.0, 40.0, 'Oud video 15s'], [$tired['status'], $tired['frequency'], $tired['ctr_drop_pct'], $tired['ad_name']]);

        $wf = Carbon::parse('2026-11-28'); // White Friday 2026-11-27 … 30
        $this->assertSame('white_friday', CreativeFatigueDetector::peakWindow($client, $wf)['key']);
        $this->ad($client, 'ad_tired', $wf, ['impr' => 1000, 'reach' => 800, 'clicks' => 20], ['impr' => 1000, 'reach' => 250, 'clicks' => 12]);
        $this->ad($client, 'ad_mild', $wf, ['impr' => 1000, 'reach' => 900, 'clicks' => 20], ['impr' => 1000, 'reach' => 900, 'clicks' => 16]);
        $peak = CreativeFatigueDetector::evaluate($client, $wf);
        $this->assertSame('white_friday', $peak['peak']['key']);
        $this->assertSame(['ad_tired' => 'fatigued', 'ad_mild' => 'watch'], array_column($peak['rows'], 'status', 'ad_ref'));

        $ramadan = collect(MenaCalendar::events('2027-01-01', '2027-04-30'))->firstWhere('key', 'ramadan');
        $this->assertSame('ramadan_last_10', CreativeFatigueDetector::peakWindow($client, Carbon::parse($ramadan['start'])->addDays(24))['key']);
        $this->assertNull(CreativeFatigueDetector::peakWindow($client, Carbon::parse($ramadan['start'])->addDays(5)), 'early Ramadan keeps normal limits');
    }

    public function test_run_keeps_one_open_alert_per_ad_and_resolves_recovered_ads(): void
    {
        [, , $client] = $this->workspace('fatigue-run');
        $end = Carbon::parse('2026-10-20');
        $this->ad($client, 'ad_tired', $end, ['impr' => 1000, 'reach' => 800, 'clicks' => 20], ['impr' => 1000, 'reach' => 250, 'clicks' => 12]);

        $this->assertSame(1, CreativeFatigueDetector::run($client, $end)['open']);
        CreativeFatigueDetector::run($client, $end);
        $this->assertSame(1, CreativeFatigueAlert::count(), 'updated in place, not duplicated');

        DB::table('daily_metrics')->where('date', '>=', $end->copy()->subDays(6)->toDateString())->update(['reach' => 900, 'clicks' => 20, 'link_clicks' => 20]);
        $this->assertSame(['open' => 0, 'resolved' => 1], array_intersect_key(CreativeFatigueDetector::run($client, $end), ['open' => 1, 'resolved' => 1]));
        $this->assertNotNull(CreativeFatigueAlert::sole()->resolved_at);
    }

    public function test_board_is_held_by_studio_respects_visibility_and_hide_spend_and_deep_links_to_studio(): void
    {
        [$agency, $owner, $client] = $this->workspace('fatigue-ui');
        $this->assertContains($this->actingAs($owner)->get("/app/{$agency->slug}/creative-fatigue")->status(), [403, 404]);

        config(['modules.preview_agencies' => ['fatigue-ui']]);
        $other = Client::create(['agency_id' => $agency->id, 'name' => 'Hidden Spend Co', 'currency' => 'SAR', 'status' => 'active', 'hide_spend' => true]);
        $base = ['agency_id' => $agency->id, 'ad_ref' => 'x', 'status' => 'fatigued', 'frequency' => 4, 'ctr_recent' => 1.2, 'ctr_baseline' => 2, 'ctr_drop_pct' => 40, 'window_end' => '2026-10-20', 'detected_at' => now()];
        $a1 = CreativeFatigueAlert::create($base + ['client_id' => $client->id, 'ad_name' => 'Oud video', 'spend_recent' => 71.125]);
        CreativeFatigueAlert::create($base + ['client_id' => $other->id, 'ad_name' => 'Secret spend ad', 'spend_recent' => 999.5]);

        $html = $this->actingAs($owner)->get("/app/{$agency->slug}/creative-fatigue?lang=ar")->assertOk()
            ->assertSee('Oud video')->assertSee('71.125 KWD')->assertSee('Secret spend ad')->assertDontSee('999.5')->assertSee('اكتب بديلًا')->getContent();
        $this->assertStringContainsString('studio?fatigue='.$a1->id, $html);

        $this->actingAs($owner);
        Filament::setTenant($agency);
        $this->get("/app/{$agency->slug}/studio?fatigue={$a1->id}")->assertOk();
        Livewire::withQueryParams(['fatigue' => $a1->id])->test(StudioPage::class)->assertSet('clientId', $client->id)->assertSet('briefTitle', 'بديل للإعلان Oud video');

        // Analysts view only: no checks, no draft links.
        $manager = User::factory()->create();
        DB::table('agency_user')->where('user_id', $manager->id)->delete();
        $agency->members()->attach($manager->id, ['role' => 'analyst']);
        $this->actingAs($manager);
        Livewire::test(CreativeFatigueBoardPage::class)->call('checkNow')->assertDontSee('اكتب بديلًا');
    }
}
