<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\Connection;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\DataGaps;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** R4 (D-303): FS-24 / XS-44 days without data are gaps with a reason, never zeros. */
class R4cDataGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_gaps_carry_their_reason_and_zero_spend_days_stay_data(): void
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4c', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        $network = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta', 'category' => 'social']);
        $conn = Connection::create(['agency_id' => $agency->id, 'ad_network_id' => $network->id, 'status' => 'active', 'environment' => 'live']);
        $acc = AdAccount::create(['agency_id' => $agency->id, 'connection_id' => $conn->id, 'client_id' => $client->id, 'ad_network_id' => $network->id, 'external_id' => 'act_1', 'name' => 'Zad', 'currency' => 'SAR', 'is_active' => true]);
        $acc->forceFill(['created_at' => now()->subDays(8)])->save();

        $d = fn (int $ago) => now()->subDays($ago)->toDateString();
        foreach ([7, 6, 2, 1] as $ago) {
            DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'ad_account_id' => $acc->id, 'date' => $d($ago), 'network' => 'meta', 'currency' => 'SAR', 'spend' => $ago === 6 ? 0 : 100, 'results' => 1]);
        }
        DB::table('sync_runs')->insert(['agency_id' => $agency->id, 'ad_account_id' => $acc->id, 'kind' => 'daily', 'from' => $d(5), 'to' => $d(4), 'status' => 'error', 'created_at' => now(), 'updated_at' => now()]);

        $s = DataGaps::series($client, $d(10), $d(1));
        $this->assertSame('not_connected', $s['days'][$d(10)]['gap']);
        $this->assertNull($s['days'][$d(6)]['gap'], 'a 0-spend day with rows is data, not a gap');
        $this->assertSame(0.0, $s['days'][$d(6)]['spend']);
        $this->assertSame('sync_failed', $s['days'][$d(5)]['gap']);
        $this->assertSame('no_data', $s['days'][$d(3)]['gap']);
        $this->assertSame([['from' => $d(5), 'to' => $d(4), 'days' => 2, 'reason' => 'sync_failed']], array_values(array_filter($s['gaps'], fn ($g) => $g['reason'] === 'sync_failed')));

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(ViewClient::class, ['record' => $client->id])->call('setPeriod', '14d')->assertSee('Daily spend')->assertSee('Sync failed')->assertSee('No data for 2 days');

        // The client sees the same gaps on the report link and in the monthly PDF data, never days before data started.
        $link = ClientLink::issue($client);
        auth()->logout();
        $html = $this->get('/r/'.$link->token.'?lang=en&p=7d')->assertOk()->assertSee('Days with no data')->getContent();
        $this->assertStringContainsString($d(5).' → '.$d(4), $html);
        $this->assertStringContainsString('the data sync failed', $html);
        $this->assertStringContainsString('nothing came back from the platform', $html);
        $this->assertStringNotContainsString('read-only', $html);
        $gaps = DataGaps::forReport($client, $d(10), $d(0));
        $this->assertNotContains('not_connected', array_column($gaps, 'reason'));
        $this->assertSame([], DataGaps::forReport(Client::create(['agency_id' => $agency->id, 'name' => 'Empty', 'currency' => 'SAR', 'status' => 'active']), $d(10), $d(1)), 'no data at all: no gap line');
    }
}
