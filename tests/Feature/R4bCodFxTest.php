<?php

namespace Tests\Feature;

use App\Alerts\AlertEngine;
use App\Alerts\AlertText;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\CodWeek;
use App\Models\Connection;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\CodOutcomes;
use App\Support\Fx;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** R4 part 2 (D-304): FS-16 / XS-14 COD weekly counts → cost per delivered order; FS-17 FX drift alert at 5 %. */
class R4bCodFxTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $currency = 'SAR'): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4b', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => $currency, 'status' => 'active', 'monthly_budget' => 100000]);

        return [$agency, $owner, $client];
    }

    public function test_weekly_counts_from_a_sheet_give_cost_per_delivered_order(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $network = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta', 'category' => 'social']);
        $conn = Connection::create(['agency_id' => $agency->id, 'ad_network_id' => $network->id, 'status' => 'active', 'environment' => 'live']);
        $acc = AdAccount::create(['agency_id' => $agency->id, 'connection_id' => $conn->id, 'client_id' => $client->id, 'ad_network_id' => $network->id, 'external_id' => 'act_1', 'name' => 'Zad', 'currency' => 'SAR', 'is_active' => true]);
        $camp = Campaign::create(['agency_id' => $agency->id, 'ad_account_id' => $acc->id, 'external_id' => 'c1', 'name' => 'Winter sale']);
        $week = now('Asia/Amman')->subWeeks(2)->startOfWeek(CarbonInterface::SUNDAY);
        foreach (range(0, 6) as $i) {
            DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => $week->copy()->addDays($i)->toDateString(), 'network' => 'meta', 'currency' => 'SAR', 'spend' => 100, 'purchases' => 20, 'results' => 20]);
        }

        $r = CodOutcomes::import($client, [
            ['بداية الأسبوع', 'الحملة', 'تم التوصيل', 'مرتجع', 'ملغى'],
            [$week->toDateString(), 'Winter sale', '٧٠', '20', '10'],
            ['not a date', '', '5', '0', '0'],
            [$week->toDateString(), 'Old campaign', '10', '0', '0'],
        ], $owner);
        $this->assertSame(2, $r['saved']);
        $this->assertSame(1, $r['skipped']);
        $this->assertSame(['Old campaign'], $r['unknown_campaigns']);
        $this->assertSame($camp->id, CodWeek::where('delivered', 70)->value('campaign_id'));

        $s = CodOutcomes::summary($client, now()->subWeeks(8)->toDateString(), now()->toDateString());
        $this->assertSame(80, $s['delivered']);
        $this->assertSame(110, $s['placed']);
        $this->assertSame(8.75, $s['cost_per_delivered'], '700 SAR / 80 delivered');

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(ViewClient::class, ['record' => $client->id])->assertSee('Cash on delivery: delivered orders')
            ->callAction('codWeek', ['week_start' => $week->toDateString(), 'campaign_id' => $camp->id, 'delivered' => 75, 'returned' => 20, 'cancelled' => 10])
            ->assertHasNoActionErrors();
        $this->assertSame(75, CodWeek::where('campaign_id', $camp->id)->value('delivered'), 'same week and campaign is updated, not doubled');
    }

    public function test_fx_drift_alert_fires_at_five_percent_and_reads_in_arabic(): void
    {
        [$agency, , $client] = $this->workspace('EGP');
        $first = now()->startOfMonth()->toDateString();
        DB::table('fx_rates')->insert([
            ['date' => $first, 'base' => 'USD', 'quote' => 'EGP', 'rate' => 47.00],
            ['date' => now()->toDateString(), 'base' => 'USD', 'quote' => 'EGP', 'rate' => 50.00],
        ]);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => now()->subDays(2)->toDateString(), 'network' => 'meta', 'currency' => 'USD', 'spend' => 50, 'results' => 3]);

        AlertEngine::run($agency, now()->toDateString());
        $a = Alert::where('rule_key', 'fx_drift')->firstOrFail();
        $this->assertSame('Exchange rate moved 6%: Zad Store', $a->title);
        $this->assertStringContainsString('1 USD = 50.00 EGP now, 47.00 EGP on '.$first, $a->detail);
        $this->assertStringContainsString('تغيّر سعر الصرف بنسبة', AlertText::title($a->title, 'ar'));
        $this->assertStringContainsString('ميزانية الشهر', AlertText::detail($a->detail, 'ar'));

        DB::table('fx_rates')->where('date', now()->toDateString())->update(['rate' => 48.00]);
        Cache::flush();
        $this->refreshFx();
        AlertEngine::run($agency, now()->toDateString());
        $this->assertNotSame('open', $a->fresh()->status, 'a 2 % move closes it');
    }

    private function refreshFx(): void
    {
        $p = new \ReflectionProperty(Fx::class, 'memo');
        $p->setValue(null, []);
    }
}
