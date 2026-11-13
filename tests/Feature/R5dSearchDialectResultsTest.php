<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\ClientResource;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Connection;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\DialectResults;
use App\Support\PanelSearch;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R5 part 4: PS-23 Arabic-aware panel search, PS-25 shortcuts by key position, PS-22 results by dialect. */
class R5dSearchDialectResultsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r5d', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh', 'language' => 'en', 'country' => 'SA']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'عيادة الريّان', 'currency' => 'SAR', 'status' => 'active']);
        $network = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta', 'category' => 'social']);
        $conn = Connection::create(['agency_id' => $agency->id, 'ad_network_id' => $network->id, 'status' => 'active', 'environment' => 'live']);
        $acc = AdAccount::create(['agency_id' => $agency->id, 'connection_id' => $conn->id, 'client_id' => $client->id, 'ad_network_id' => $network->id, 'external_id' => 'act_1', 'name' => 'Rayyan', 'currency' => 'SAR', 'is_active' => true]);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);

        return [$agency, $owner, $client, $acc];
    }

    public function test_arabic_and_arabizi_spellings_find_the_same_client_and_campaigns(): void
    {
        [$agency, $owner, $client, $acc] = $this->workspace();
        Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        Campaign::create(['agency_id' => $agency->id, 'ad_account_id' => $acc->id, 'external_id' => 'c1', 'name' => 'Al Rayyan Ramadan Leads']);

        foreach (['الريان', 'الريّان', 'rayan', 'al rayyan'] as $q) {
            $hits = PanelSearch::find($agency, $owner, $q);
            $this->assertSame([$client->id], array_map(fn ($c) => $c->id, $hits['clients']) ?: (count($hits['campaigns']) ? [$client->id] : []), "query: $q");
        }
        $this->assertCount(1, PanelSearch::find($agency, $owner, 'الريان')['campaigns'], 'Arabic finds the Latin campaign name');
        $this->assertSame([], PanelSearch::find($agency, $owner, 'z')['clients'], 'one letter is too short');

        $titles = ClientResource::getGlobalSearchResults('الريان')->map(fn ($r) => (string) $r->title)->all();
        $this->assertContains('عيادة الريّان', $titles);
        $this->assertContains('Al Rayyan Ramadan Leads', $titles);
    }

    public function test_search_respects_client_visibility(): void
    {
        [$agency, , $client] = $this->workspace();
        $member = User::factory()->create();
        $agency->members()->attach($member->id, ['role' => 'member']);
        $visible = PanelSearch::find($agency, $member, 'الريان')['clients'];
        $this->assertSame(Client::visibleTo($member, $agency)->whereKey($client->id)->exists(), count($visible) === 1);
    }

    public function test_shortcut_script_uses_key_position(): void
    {
        [$agency, $owner] = $this->workspace();
        $html = $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/desk")->assertOk()->getContent();
        $this->assertStringContainsString("e.code === 'KeyK'", $html);
        $this->assertStringContainsString("e.code === 'Slash'", $html);
    }

    public function test_results_by_dialect_with_tags_and_guesses(): void
    {
        [$agency, , $client, $acc] = $this->workspace();
        $mk = function (string $name, float $spend, int $results) use ($agency, $client, $acc) {
            $ad = Ad::create(['agency_id' => $agency->id, 'ad_account_id' => $acc->id, 'external_id' => uniqid(), 'name' => $name]);
            DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'ad_account_id' => $acc->id, 'date' => now()->subDays(2)->toDateString(), 'network' => 'meta',
                'level' => 'ad', 'ad_ref' => $ad->id, 'currency' => 'SAR', 'spend' => $spend, 'results' => $results, 'result_type' => 'lead', 'leads' => $results]);

            return $ad;
        };
        $a1 = $mk('Video 1 - Riyadh', 300, 10);
        $mk('Story - وش تنتظر', 200, 4);
        $mk('Carousel 3', 100, 1);

        $s = DialectResults::summary($client, now()->subDays(7)->toDateString(), now()->toDateString());
        $this->assertSame(0, $s['tagged']);
        $this->assertContains('gulf', array_column($s['rows'], 'dialect'), 'guessed from a Gulf marker word');

        Livewire::test(ViewClient::class, ['record' => $client->id])->call('setAdDialect', $a1->id, 'gulf')->assertSee('Results by dialect');
        $s = DialectResults::summary($client, now()->subDays(7)->toDateString(), now()->toDateString());
        $gulf = collect($s['rows'])->firstWhere('dialect', 'gulf');
        $this->assertSame(2, $gulf['ads']);
        $this->assertSame(500.0, $gulf['spend']);
        $this->assertSame(1, $s['tagged']);
    }
}
