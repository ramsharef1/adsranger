<?php

namespace Tests\Feature;

use App\Filament\Pages\RamadanPhasesPage;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\BudgetPlan;
use App\Models\Client;
use App\Models\Connection;
use App\Models\DailyMetric;
use App\Models\DeliveryWeek;
use App\Models\User;
use App\Support\DeliveryOutcomes;
use App\Support\RamadanPhases;
use App\Support\Shell;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RamadanPhasesBoardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Agency, 1: User}
     */
    private function makeWorkspace(string $slug, string $lang = 'en', string $tz = 'Asia/Riyadh'): array
    {
        $agency = Agency::create([
            'name' => 'Agency '.$slug,
            'slug' => $slug,
            'plan' => 'agency',
            'status' => 'active',
            'currency' => 'SAR',
            'timezone' => $tz,
            'language' => $lang,
            'country' => 'SA',
        ]);
        $owner = User::factory()->create();
        DB::table('agency_user')->where('user_id', $owner->id)->delete();
        $agency->members()->attach($owner->id, ['role' => 'owner']);

        return [$agency, $owner];
    }

    private function attachAccount(Agency $agency, Client $client, string $extId): AdAccount
    {
        $network = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta', 'category' => 'social']);
        $conn = Connection::firstOrCreate(
            ['agency_id' => $agency->id, 'ad_network_id' => $network->id],
            ['status' => 'active', 'environment' => 'live'],
        );

        return AdAccount::create([
            'agency_id' => $agency->id,
            'connection_id' => $conn->id,
            'client_id' => $client->id,
            'ad_network_id' => $network->id,
            'external_id' => $extId,
            'name' => $client->name.' Meta',
            'currency' => $client->currency,
            'is_active' => true,
        ]);
    }

    public function test_board_builds_six_contiguous_phases_from_mena_calendar_and_ramadan_hours(): void
    {
        $this->assertSame('today', Shell::MAP[RamadanPhasesPage::class] ?? null);

        [$agency, $owner] = $this->makeWorkspace('ramadan-phases-cal');
        $agency->forceFill(['ramadan_digest_at' => '22:00'])->save();

        $anchor = Carbon::parse('2026-11-14 12:00', 'Asia/Riyadh');
        $board = RamadanPhases::board($agency, $owner, null, null, $anchor, 'en');

        $cycle = $board['selected_cycle'];
        $this->assertNotNull($cycle);
        $this->assertSame('expected', $cycle['ramadan']['status']);
        $this->assertSame('expected', $cycle['eid_fitr']['status']);
        $this->assertFalse($board['hours']['active']);
        $this->assertTrue($board['hours']['enabled']);
        $this->assertSame('22:00', $board['hours']['digest_at']);
        $this->assertSame('03:00', $board['hours']['quiet_start']);
        $this->assertSame('12:00', $board['hours']['quiet_end']);

        $phases = $cycle['phases'];
        $this->assertCount(6, $phases);
        $this->assertSame(['before', 'days_1_10', 'days_11_20', 'last_ten_nights', 'eid_fitr', 'after'], array_column($phases, 'key'));
        $this->assertSame(14, $phases[0]['days']);
        $this->assertSame(10, $phases[1]['days']);
        $this->assertSame(10, $phases[2]['days']);
        $this->assertGreaterThanOrEqual(9, $phases[3]['days']);
        $this->assertLessThanOrEqual(10, $phases[3]['days']);
        $this->assertSame(3, $phases[4]['days']);
        $this->assertSame(14, $phases[5]['days']);

        for ($i = 1; $i < count($phases); $i++) {
            $expectedNextStart = Carbon::parse($phases[$i - 1]['end'])->addDay()->toDateString();
            $this->assertSame($expectedNextStart, $phases[$i]['start'], "Phase {$phases[$i]['key']} must immediately follow {$phases[$i - 1]['key']}");
        }

        // Confirm moon sighting (+1 day shift) in holiday_statuses and verify read-only propagation + active RamadanHours.
        $calcStart = $cycle['ramadan']['calc_start'];
        $year = (int) substr($calcStart, 0, 4);
        $shiftedRamadan = Carbon::parse($calcStart)->addDay()->toDateString();
        $shiftedEid = Carbon::parse($cycle['eid_fitr']['calc_start'])->addDay()->toDateString();

        DB::table('holiday_statuses')->insert([
            [
                'event_key' => 'ramadan',
                'year' => $year,
                'status' => 'confirmed',
                'start_date' => $shiftedRamadan,
                'source' => 'Supreme Court announcement',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_key' => 'eid_fitr',
                'year' => $year,
                'status' => 'confirmed',
                'start_date' => $shiftedEid,
                'source' => 'Official Shawwal sighting',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $duringRamadan = Carbon::parse($shiftedRamadan.' 21:30', 'Asia/Riyadh')->addDays(4);
        $confirmedBoard = RamadanPhases::board($agency, $owner, $calcStart, null, $duringRamadan, 'en');
        $confirmedCycle = $confirmedBoard['selected_cycle'];

        $this->assertNotNull($confirmedCycle);
        $this->assertSame('confirmed', $confirmedCycle['ramadan']['status']);
        $this->assertSame($shiftedRamadan, $confirmedCycle['ramadan']['start']);
        $this->assertSame($calcStart, $confirmedCycle['ramadan']['calc_start']);
        $this->assertSame('Supreme Court announcement', $confirmedCycle['ramadan']['source']);
        $this->assertSame('confirmed', $confirmedCycle['eid_fitr']['status']);
        $this->assertSame($shiftedEid, $confirmedCycle['eid_fitr']['start']);
        $this->assertSame('Official Shawwal sighting', $confirmedCycle['eid_fitr']['source']);
        $this->assertTrue($confirmedBoard['hours']['active']);
        $this->assertSame('active', $confirmedCycle['phases'][1]['state']);
    }

    public function test_per_phase_metrics_deltas_three_decimal_currencies_sector_labels_and_budget_plan_pacing(): void
    {
        [$agency, $owner] = $this->makeWorkspace('ramadan-phases-math');

        $storeClient = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Kuwait Boutique',
            'vertical' => 'ecommerce',
            'currency' => 'KWD',
            'status' => 'active',
        ]);
        $charityClient = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Rahma Relief',
            'vertical' => 'charity',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        $storeAcc = $this->attachAccount($agency, $storeClient, 'act_kwd_1');
        $charityAcc = $this->attachAccount($agency, $charityClient, 'act_sar_2');

        $cycles = RamadanPhases::cycles(Carbon::parse('2026-11-14', 'Asia/Riyadh'), 'en');
        $cycle = collect($cycles)->first(fn (array $c): bool => str_starts_with($c['key'], '2027-')) ?? $cycles[0];
        $phasesByKey = collect($cycle['phases'])->keyBy('key');

        $febDays = Carbon::parse('2027-02-01')->daysInMonth;
        $marDays = Carbon::parse('2027-03-01')->daysInMonth;

        // 100 KWD/day planned across Jan/Feb/Mar for storeClient; 200 SAR/day planned for charityClient.
        foreach (['2027-01' => 31 * 100, '2027-02' => $febDays * 100, '2027-03' => $marDays * 100] as $ym => $budget) {
            BudgetPlan::create([
                'agency_id' => $agency->id,
                'client_id' => $storeClient->id,
                'month' => $ym.'-01',
                'budget' => $budget,
            ]);
        }
        foreach (['2027-01' => 31 * 200, '2027-02' => $febDays * 200, '2027-03' => $marDays * 200] as $ym => $budget) {
            BudgetPlan::create([
                'agency_id' => $agency->id,
                'client_id' => $charityClient->id,
                'month' => $ym.'-01',
                'budget' => $budget,
            ]);
        }

        // Seed storeClient:
        // Phase 1 (before, 14d): 80 KWD/day * 14 = 1,120.000 KWD, 4 purchases/day * 14 = 56 (CPR 20.000 KWD), pace 80% (under).
        DailyMetric::create([
            'agency_id' => $agency->id,
            'client_id' => $storeClient->id,
            'ad_account_id' => $storeAcc->id,
            'date' => $phasesByKey['before']['start'],
            'network' => 'meta',
            'currency' => 'KWD',
            'result_type' => 'purchase',
            'spend' => 1120,
            'results' => 56,
            'purchases' => 56,
            'impressions' => 40000,
        ]);

        // Phase 2 (days_1_10, 10d): 100 KWD/day * 10 = 1,000.000 KWD (+25% daily spend vs before), 5 purchases/day * 10 = 50 (+25%), CPR 20.000 KWD (0%), pace 100% (on).
        DailyMetric::create([
            'agency_id' => $agency->id,
            'client_id' => $storeClient->id,
            'ad_account_id' => $storeAcc->id,
            'date' => $phasesByKey['days_1_10']['start'],
            'network' => 'meta',
            'currency' => 'KWD',
            'result_type' => 'purchase',
            'spend' => 1000,
            'results' => 50,
            'purchases' => 50,
            'impressions' => 50000,
        ]);

        // Phase 3 (days_11_20, 10d): 130 KWD/day * 10 = 1,300.000 KWD (+30% vs days_1_10), 50 purchases, CPR 26.000 KWD (+30%), pace 130% (over).
        DailyMetric::create([
            'agency_id' => $agency->id,
            'client_id' => $storeClient->id,
            'ad_account_id' => $storeAcc->id,
            'date' => $phasesByKey['days_11_20']['start'],
            'network' => 'meta',
            'currency' => 'KWD',
            'result_type' => 'purchase',
            'spend' => 1300,
            'results' => 50,
            'purchases' => 50,
            'impressions' => 55000,
        ]);

        // Charity client in last_ten_nights: Donor label from SectorPacks.
        DailyMetric::create([
            'agency_id' => $agency->id,
            'client_id' => $charityClient->id,
            'ad_account_id' => $charityAcc->id,
            'date' => $phasesByKey['last_ten_nights']['start'],
            'network' => 'meta',
            'currency' => 'SAR',
            'result_type' => 'purchase',
            'spend' => 2000,
            'results' => 100,
            'purchases' => 100,
            'revenue' => 18000,
            'impressions' => 90000,
        ]);

        $afterSeason = Carbon::parse($cycle['window_end'])->addDays(2);
        $board = RamadanPhases::board($agency, $owner, $cycle['key'], null, $afterSeason, 'en');

        $rows = collect($board['rows'])->keyBy(fn (array $r): int => $r['client']->id);
        $storeRow = $rows[$storeClient->id];
        $charityRow = $rows[$charityClient->id];

        $this->assertSame('1,120.000 KWD', $storeRow['phases']['before']['spend_formatted']);
        $this->assertSame('20.000 KWD', $storeRow['phases']['before']['cpr_formatted']);
        $this->assertSame('under', $storeRow['phases']['before']['pace_status']);
        $this->assertEqualsWithDelta(80.0, $storeRow['phases']['before']['pace_pct'], 0.2);

        $this->assertSame('1,000.000 KWD', $storeRow['phases']['days_1_10']['spend_formatted']);
        $this->assertEqualsWithDelta(25.0, $storeRow['phases']['days_1_10']['spend_delta_pct'], 0.2);
        $this->assertEqualsWithDelta(25.0, $storeRow['phases']['days_1_10']['results_delta_pct'], 0.2);
        $this->assertEqualsWithDelta(0.0, $storeRow['phases']['days_1_10']['cpr_delta_pct'], 0.2);
        $this->assertSame('on', $storeRow['phases']['days_1_10']['pace_status']);
        $this->assertEqualsWithDelta(100.0, $storeRow['phases']['days_1_10']['pace_pct'], 0.2);

        $this->assertSame('over', $storeRow['phases']['days_11_20']['pace_status']);
        $this->assertEqualsWithDelta(30.0, $storeRow['phases']['days_11_20']['spend_delta_pct'], 0.2);
        $this->assertEqualsWithDelta(30.0, $storeRow['phases']['days_11_20']['cpr_delta_pct'], 0.2);

        // Empty phase ("after") has zero fake fallback numbers.
        $this->assertFalse($storeRow['phases']['after']['has_data']);
        $this->assertNull($storeRow['phases']['after']['spend']);
        $this->assertNull($storeRow['phases']['after']['results']);
        $this->assertNull($storeRow['phases']['after']['cpr']);
        $this->assertNull($storeRow['phases']['after']['pace_pct']);
        $this->assertSame('1,400.000 KWD', $storeRow['phases']['after']['planned_formatted']);

        // Charity sector pack label ("Donors").
        $this->assertSame('Donors', $charityRow['phases']['last_ten_nights']['result_label']);
        $this->assertSame('2,000.00 SAR', $charityRow['phases']['last_ten_nights']['spend_formatted']);
    }

    public function test_client_visibility_tenant_isolation_and_hide_spend_redaction(): void
    {
        [$agency, $owner] = $this->makeWorkspace('ramadan-phases-vis');
        [$otherAgency] = $this->makeWorkspace('ramadan-phases-other');

        $openClient = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Open Client',
            'currency' => 'SAR',
            'status' => 'active',
        ]);
        $restrictedClient = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Owner Only Client',
            'currency' => 'SAR',
            'status' => 'active',
        ]);
        $restrictedClient->users()->attach($owner->id);

        $hiddenSpendClient = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Confidential Brand',
            'currency' => 'SAR',
            'status' => 'active',
            'hide_spend' => true,
            'monthly_budget' => 9876,
        ]);

        $foreignClient = Client::create([
            'agency_id' => $otherAgency->id,
            'name' => 'Foreign Tenant Brand',
            'currency' => 'SAR',
            'status' => 'active',
        ]);

        $hiddenAcc = $this->attachAccount($agency, $hiddenSpendClient, 'act_hide_1');
        $cycle = RamadanPhases::cycles(Carbon::parse('2026-11-14', 'Asia/Riyadh'), 'en')[0];

        BudgetPlan::create([
            'agency_id' => $agency->id,
            'client_id' => $hiddenSpendClient->id,
            'month' => substr($cycle['ramadan']['start'], 0, 7).'-01',
            'budget' => 9432,
        ]);
        DailyMetric::create([
            'agency_id' => $agency->id,
            'client_id' => $hiddenSpendClient->id,
            'ad_account_id' => $hiddenAcc->id,
            'date' => $cycle['ramadan']['start'],
            'network' => 'meta',
            'currency' => 'SAR',
            'result_type' => 'lead',
            'spend' => 7654.32,
            'results' => 123,
            'leads' => 123,
            'impressions' => 30000,
        ]);

        $analyst = User::factory()->create();
        DB::table('agency_user')->where('user_id', $analyst->id)->delete();
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);

        $analystBoard = RamadanPhases::board($agency, $analyst, $cycle['key']);
        $visibleNames = array_map(fn (array $r): string => $r['client']->name, $analystBoard['rows']);

        $this->assertContains('Open Client', $visibleNames);
        $this->assertContains('Confidential Brand', $visibleNames);
        $this->assertNotContains('Owner Only Client', $visibleNames);
        $this->assertNotContains('Foreign Tenant Brand', $visibleNames);

        $hiddenRow = collect($analystBoard['rows'])->first(fn (array $r): bool => $r['client']->id === $hiddenSpendClient->id);
        $this->assertNotNull($hiddenRow);
        $this->assertTrue($hiddenRow['hide_spend']);
        $phaseCell = $hiddenRow['phases']['days_1_10'];
        $this->assertSame('123', $phaseCell['results_formatted']);
        $this->assertNull($phaseCell['spend']);
        $this->assertNull($phaseCell['spend_formatted']);
        $this->assertNull($phaseCell['cpr']);
        $this->assertNull($phaseCell['cpr_formatted']);
        $this->assertNull($phaseCell['planned_budget']);
        $this->assertNull($phaseCell['planned_formatted']);
        $this->assertNull($phaseCell['pace_pct']);
        $this->assertSame('hidden', $phaseCell['pace_status']);

        Shell::forget();
        $html = $this->withSession(['locale' => 'en'])
            ->actingAs($analyst)
            ->get("/app/{$agency->slug}/ramadan-phases?cycleStart={$cycle['key']}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Confidential Brand', $html);
        $this->assertStringContainsString('Spend hidden', $html);
        $this->assertStringContainsString('123', $html);
        $this->assertStringNotContainsString('Owner Only Client', $html);
        $this->assertStringNotContainsString('Foreign Tenant Brand', $html);
        $this->assertStringNotContainsString('7,654', $html);
        $this->assertStringNotContainsString('9,432', $html);
        $this->assertStringNotContainsString('9,876', $html);
        $this->assertStringNotContainsString('62.23', $html);
    }

    public function test_arabic_and_english_rendering_hub_styles_and_mobile_360px_touch_targets(): void
    {
        [$agency, $owner] = $this->makeWorkspace('ramadan-phases-ui', 'ar');
        $agency->forceFill(['module_overrides' => ['panel_shell' => true, 'mission_shell' => true]])->save();

        $client = Client::create([
            'agency_id' => $agency->id,
            'name' => 'متجر النور',
            'vertical' => 'charity',
            'currency' => 'JOD',
            'status' => 'active',
            'hide_spend' => true,
        ]);
        $acc = $this->attachAccount($agency, $client, 'act_ar_1');
        $defaultCycle = RamadanPhases::board($agency, $owner, null, null, null, 'ar')['selected_cycle'];
        $this->assertNotNull($defaultCycle);

        DailyMetric::create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'ad_account_id' => $acc->id,
            'date' => $defaultCycle['ramadan']['start'],
            'network' => 'meta',
            'currency' => 'JOD',
            'result_type' => 'purchase',
            'spend' => 450,
            'results' => 45,
            'purchases' => 45,
            'impressions' => 15000,
        ]);

        Shell::forget();
        $enHtml = $this->withSession(['locale' => 'en'])
            ->actingAs($owner)
            ->get("/app/{$agency->slug}/ramadan-phases")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-hub-styles', $enHtml);
        $this->assertStringContainsString('data-ramadan-phases-styles', $enHtml);
        $this->assertStringContainsString('data-ramadan-phases', $enHtml);
        $this->assertStringContainsString('.hub .rp-phases-grid{display:grid;grid-template-columns:minmax(0,1fr)', $enHtml);
        $this->assertStringContainsString('min-height:44px', $enHtml);
        $this->assertStringContainsString('14 days before Ramadan', $enHtml);
        $this->assertStringContainsString('Days 1–10', $enHtml);
        $this->assertStringContainsString('Days 11–20', $enHtml);
        $this->assertStringContainsString('Last ten nights', $enHtml);
        $this->assertStringContainsString('Eid al-Fitr', $enHtml);
        $this->assertStringContainsString('14 days after Eid', $enHtml);
        $this->assertDoesNotMatchRegularExpression('/\(#\d+/', strip_tags($enHtml));

        Shell::forget();
        $arHtml = $this->withSession(['locale' => 'ar'])
            ->actingAs($owner)
            ->get("/app/{$agency->slug}/ramadan-phases")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('لوحة مراحل رمضان', $arHtml);
        $this->assertStringContainsString('14 يومًا قبل رمضان', $arHtml);
        $this->assertStringContainsString('الأيام 1–10', $arHtml);
        $this->assertStringContainsString('الأيام 11–20', $arHtml);
        $this->assertStringContainsString('العشر الأواخر', $arHtml);
        $this->assertStringContainsString('عيد الفطر', $arHtml);
        $this->assertStringContainsString('14 يومًا بعد العيد', $arHtml);
        $this->assertStringContainsString('الإنفاق مخفي', $arHtml);
        $this->assertStringContainsString('المتبرعون', $arHtml);
        $this->assertStringNotContainsString('450', $arHtml);

        // Livewire filter switching works cleanly without pre-filled sample numbers.
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs($owner);
        Filament::setTenant($agency);
        Livewire::test(RamadanPhasesPage::class)
            ->assertSet('clientFilter', '')
            ->set('clientFilter', (string) $client->id)
            ->assertSee('متجر النور');
    }

    public function test_removing_delivery_week_records_audit_log_with_week_and_app_name(): void
    {
        [$agency, $owner] = $this->makeWorkspace('delivery-audit-sp', 'en', 'Asia/Amman');
        $restaurant = Client::create([
            'agency_id' => $agency->id,
            'name' => 'Amman Grill',
            'vertical' => 'restaurant',
            'currency' => 'JOD',
            'status' => 'active',
        ]);

        $row = DeliveryOutcomes::save($restaurant, '2026-11-08', 'Talabat', 95, 1140.500, 'manual', $owner);

        $analyst = User::factory()->create();
        DB::table('agency_user')->where('user_id', $analyst->id)->delete();
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);

        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs($analyst);
        Filament::setTenant($agency);

        Livewire::test(ViewClient::class, ['record' => $restaurant->id])
            ->call('removeDeliveryWeek', $row->id)
            ->assertForbidden();
        $this->assertNotNull(DeliveryWeek::find($row->id));
        $this->assertFalse(AuditLog::where('action', 'delivery_week.removed')->exists());

        $this->actingAs($owner);
        Filament::setTenant($agency);

        Livewire::test(ViewClient::class, ['record' => $restaurant->id])
            ->call('removeDeliveryWeek', $row->id)
            ->assertOk();

        $this->assertNull(DeliveryWeek::find($row->id));

        $audit = AuditLog::where('action', 'delivery_week.removed')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($agency->id, $audit->agency_id);
        $this->assertSame($owner->id, $audit->user_id);
        $this->assertSame(Client::class, $audit->subject_type);
        $this->assertSame($restaurant->id, $audit->subject_id);
        $this->assertSame('2026-11-08', $audit->meta['week_start'] ?? null);
        $this->assertSame('Talabat', $audit->meta['app_name'] ?? null);
    }
}
