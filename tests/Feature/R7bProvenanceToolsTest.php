<?php

namespace Tests\Feature;

use App\Filament\Pages\DailyDesk;
use App\Models\Agency;
use App\Models\AiConnection;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\AiWorkspace\AiKill;
use App\Support\AiWorkspace\Brain;
use App\Support\AiWorkspace\ContextPack;
use App\Support\AiWorkspace\QualityGate;
use App\Support\AiWorkspace\ReportMemory;
use App\Support\AiWorkspace\ToolCatalogue;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Tests\TestCase;

/** R7 part 2: FS-03 number sources, EG-25 report → brain, EG-26 / FS-08 night notes on the Desk, WS-10 / FS-10 tool catalogue. */
class R7bProvenanceToolsTest extends TestCase
{
    use RefreshDatabase;

    private static ?string $keys = null;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r7b', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR']);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => now()->subDays(2)->toDateString(), 'network' => 'meta',
            'currency' => 'SAR', 'result_type' => 'purchase', 'spend' => 300, 'revenue' => 1200, 'purchases' => 6, 'results' => 6, 'impressions' => 20000, 'link_clicks' => 400]);
        config(['modules.preview_agencies' => ['acme-r7b']]);

        return [$agency, $owner, $client];
    }

    public function test_numbers_in_ai_text_carry_their_source(): void
    {
        [, $owner, $client] = $this->workspace();
        $facts = ContextPack::for($client)['facts'];
        $src = QualityGate::provenance('Spend was 300 SAR and revenue 1,200 SAR; 77,777 is made up.', $facts);
        $this->assertSame(['300 SAR', '1,200 SAR'], array_column($src, 'raw'), 'a number with no fact gets no source');
        $this->assertSame('Spend', $src[0]['label']);
        $this->assertSame('Revenue', $src[1]['label']);

        $item = Brain::save($client, ['type' => 'output', 'title' => 'Week', 'body' => 'Spend was 300 SAR for 6 purchases.'], $owner)['item'];
        $this->assertSame('Spend', $item->meta['sources'][0]['label']);
        $this->assertNotEmpty($item->meta['sources'][0]['period']);
    }

    public function test_a_sent_report_becomes_one_dated_brain_item(): void
    {
        [, , $client] = $this->workspace();
        $month = now()->subDays(2)->format('Y-m');
        $item = ReportMemory::remember($client, 'monthly', $month);
        $this->assertSame('learning', $item->type);
        $this->assertStringContainsString('Spend: 300.00 SAR', $item->original);
        $this->assertNull(ReportMemory::remember($client, 'monthly', $month), 'once per period');

        $client->forceFill(['hide_spend' => true])->save();
        $hidden = ReportMemory::remember($client->fresh(), 'weekly', now()->format('o-\WW'));
        $this->assertStringNotContainsString('Spend', $hidden->original, 'hide_spend is respected');
    }

    public function test_night_notes_show_on_the_desk_and_take_feedback(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $item = Brain::save($client, ['type' => 'output', 'title' => 'Cost per result up', 'body' => 'Check the new ad set.', 'playbook' => 'night_shift', 'meta' => ['level' => 'warning']])['item'];

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(DailyDesk::class)->assertSee('Cost per result up')->call('nightFeedback', $item->id, false)->assertDontSee('Cost per result up');
        $this->assertSame('not_useful', $item->fresh()->meta['feedback']);
    }

    public function test_switched_off_tool_is_refused_and_calls_are_logged(): void
    {
        [$agency, $user] = $this->workspace();
        if (self::$keys === null) {
            self::$keys = sys_get_temp_dir().'/adsranger-passport-'.Str::random(6);
            @mkdir(self::$keys);
            $pk = openssl_pkey_new(['private_key_bits' => 2048]);
            openssl_pkey_export($pk, $private);
            file_put_contents(self::$keys.'/oauth-private.key', $private);
            file_put_contents(self::$keys.'/oauth-public.key', openssl_pkey_get_details($pk)['key']);
            chmod(self::$keys.'/oauth-private.key', 0600);
            chmod(self::$keys.'/oauth-public.key', 0600);
        }
        Passport::loadKeysFrom(self::$keys);
        $oauth = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude', ['https://claude.ai/api/mcp/auth_callback'], false);
        AiConnection::create(['agency_id' => $agency->id, 'user_id' => $user->id, 'oauth_client_id' => $oauth->getKey(), 'client_name' => 'Claude',
            'provider' => 'claude', 'status' => 'waiting', 'allowed_client_ids' => [], 'ownership_version' => 'ai-own-v1', 'ownership_at' => now()]);
        Passport::actingAs($user, ['mcp:use'], 'api', $oauth);
        $call = fn () => $this->postJson('https://mcp.adsranger.com/', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list_clients', 'arguments' => []]],
            ['Accept' => 'application/json, text/event-stream']);

        $this->assertArrayHasKey('list_clients', ToolCatalogue::tools());
        $call()->assertOk();
        ToolCatalogue::toggle($agency, 'list_clients');
        $call()->assertForbidden();
        $this->assertSame([403, 200], DB::table('ai_tool_calls')->orderByDesc('id')->pluck('status')->map(fn ($s) => (int) $s)->all());

        AiKill::set('connector', true);
        $call()->assertForbidden();
    }
}
