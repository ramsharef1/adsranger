<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiBrainItem;
use App\Models\AiConnection;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Tests\TestCase;

/** cl104: AI Workspace M1 slice 2 — the AdsRanger connector at mcp.adsranger.com with OAuth 2.1 (D-283, D-284). */
class AiConnectorM1bTest extends TestCase
{
    use RefreshDatabase;

    private const MCP = 'https://mcp.adsranger.com/';

    private static ?string $keys = null;

    protected function setUp(): void
    {
        parent::setUp();
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
    }

    private function workspace(string $slug = 'acme-ai', string $role = 'owner'): array
    {
        $agency = Agency::create(['name' => 'Acme '.$slug, 'slug' => $slug, 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman']);
        $user = User::factory()->create();
        $agency->members()->attach($user->id, ['role' => $role]);
        $a = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR']);
        $b = Client::create(['agency_id' => $agency->id, 'name' => 'Amman Cafe', 'currency' => 'JOD']);
        config(['modules.preview_agencies' => [$slug]]);

        return [$agency, $user, $a, $b];
    }

    /** A connected AI for $user (as if the consent screen was approved) and a token for it. */
    private function connect(Agency $agency, User $user, array $allowed): AiConnection
    {
        $oauth = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude', ['https://claude.ai/api/mcp/auth_callback'], false);
        $connection = AiConnection::create(['agency_id' => $agency->id, 'user_id' => $user->id, 'oauth_client_id' => $oauth->getKey(), 'client_name' => 'Claude',
            'provider' => 'claude', 'status' => 'waiting', 'allowed_client_ids' => $allowed, 'ownership_version' => 'ai-own-v1', 'ownership_at' => now()]);
        Passport::actingAs($user, ['mcp:use'], 'api', $oauth);

        return $connection;
    }

    private function rpc(string $method, array $params = [], int $id = 1): TestResponse
    {
        return $this->postJson(self::MCP, ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params], ['Accept' => 'application/json, text/event-stream']);
    }

    private function tool(string $name, array $args = []): array
    {
        $r = $this->rpc('tools/call', ['name' => $name, 'arguments' => $args])->assertOk()->json('result');

        return ['error' => (bool) ($r['isError'] ?? false), 'text' => $r['content'][0]['text'] ?? '', 'data' => json_decode($r['content'][0]['text'] ?? 'null', true)];
    }

    public function test_discovery_points_ais_to_our_sign_in_and_unsigned_calls_are_refused(): void
    {
        $this->getJson('https://adsranger.com/.well-known/oauth-authorization-server')->assertOk()
            ->assertJsonPath('authorization_endpoint', 'https://adsranger.com/oauth/authorize')
            ->assertJsonPath('code_challenge_methods_supported', ['S256'])
            ->assertJsonStructure(['registration_endpoint', 'token_endpoint']);
        $this->getJson(self::MCP.'.well-known/oauth-protected-resource')->assertOk()->assertJsonPath('resource', 'https://mcp.adsranger.com');

        $r = $this->rpc('tools/list');
        $r->assertUnauthorized();
        $this->assertStringContainsString('resource_metadata', (string) $r->headers->get('WWW-Authenticate'));
        $this->get(self::MCP.'pricing')->assertNotFound();
    }

    public function test_consent_screen_records_workspace_clients_and_ownership_tick(): void
    {
        [$agency, $user, $a, $b] = $this->workspace();
        $reg = $this->postJson('https://adsranger.com/oauth/register', ['client_name' => 'ChatGPT', 'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect']])->assertCreated();
        $clientId = $reg->json('client_id');
        $verifier = Str::random(64);
        $query = http_build_query(['client_id' => $clientId, 'redirect_uri' => 'https://chatgpt.com/connector_platform_oauth_redirect', 'response_type' => 'code',
            'scope' => 'mcp:use', 'state' => 'xyz', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256']);

        $this->get("https://adsranger.com/oauth/authorize?$query")->assertRedirect(route('filament.app.auth.login'));

        $page = $this->actingAs($user)->withSession(['locale' => 'en'])->get("https://adsranger.com/oauth/authorize?$query")->assertOk()
            ->assertSee('Connect')->assertSee('Riyadh Dental')->assertSee('This AI is your own account');
        $token = $page->viewData('authToken');

        $this->from("https://adsranger.com/oauth/authorize?$query")->post('https://adsranger.com/oauth/authorize', ['auth_token' => $token, 'agency_id' => $agency->id, 'clients' => [$agency->id => [$a->id]]])
            ->assertSessionHasErrors('ownership');
        $this->assertSame(0, AiConnection::count());

        $done = $this->post('https://adsranger.com/oauth/authorize', ['auth_token' => $token, 'agency_id' => $agency->id, 'clients' => [$agency->id => [$a->id, 999]], 'ownership' => '1']);
        $this->assertStringStartsWith('https://chatgpt.com/connector_platform_oauth_redirect?code=', $done->headers->get('Location'));
        $c = AiConnection::sole();
        $this->assertSame([$a->id], $c->allowedIds(), 'only real clients of that workspace are kept');
        $this->assertSame(['waiting', 'chatgpt', 'ai-own-v1'], [$c->status, $c->provider, $c->ownership_version]);
        $this->assertNotNull($c->ownership_at);

        // The AI swaps the code for a short-lived token (PKCE) and calls the connector with it.
        parse_str(parse_url($done->headers->get('Location'), PHP_URL_QUERY), $back);
        $this->assertSame('xyz', $back['state']);
        auth()->forgetGuards();
        $tok = $this->postJson('https://adsranger.com/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $clientId,
            'redirect_uri' => 'https://chatgpt.com/connector_platform_oauth_redirect', 'code_verifier' => $verifier, 'code' => $back['code']])->assertOk();
        $this->assertLessThanOrEqual(3600, $tok->json('expires_in'));
        $this->assertNotEmpty($tok->json('refresh_token'));
        $list = $this->withHeaders(['Authorization' => 'Bearer '.$tok->json('access_token')])
            ->postJson(self::MCP, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 'list_clients', 'arguments' => []]], ['Accept' => 'application/json, text/event-stream'])
            ->assertOk()->json('result.content.0.text');
        $this->assertSame(['Riyadh Dental'], array_column(json_decode($list, true)['clients'], 'name'));
        $this->assertSame('verified', $c->fresh()->status);

        $c->fresh()->revoke($user);
        auth()->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$tok->json('access_token')])
            ->postJson(self::MCP, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], ['Accept' => 'application/json, text/event-stream'])->assertUnauthorized();
    }

    public function test_tools_read_only_allowed_clients_and_the_first_call_verifies(): void
    {
        [$agency, $user, $a, $b] = $this->workspace();
        $connection = $this->connect($agency, $user, [$a->id]);

        $names = collect($this->rpc('tools/list')->assertOk()->json('result.tools'))->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['list_clients', 'get_results', 'get_alerts', 'season_calendar', 'get_client_brain', 'save_to_client', 'create_draft', 'check_output'], $names);

        $list = $this->tool('list_clients');
        $this->assertSame(['Riyadh Dental'], array_column($list['data']['clients'], 'name'));
        $this->assertSame('verified', $connection->fresh()->status);

        $this->assertTrue($this->tool('get_results', ['client_id' => $b->id])['error'], 'not shared with this AI');
        $this->assertFalse($this->tool('get_results', ['client_id' => $a->id])['error']);
        $this->assertSame(3, $connection->fresh()->calls_today, 'tool calls count; tools/list does not');
    }

    public function test_saves_go_through_the_gate_and_format_errors_name_the_field(): void
    {
        [$agency, $user, $a] = $this->workspace();
        $this->connect($agency, $user, [$a->id]);

        $bad = $this->tool('create_draft', ['client_id' => $a->id, 'output_type' => 'weekly_summary', 'title' => 'Week 46',
            'output' => ['finding' => 'Spend fell', 'why' => 'Budget cut', 'watch' => 'CPR', 'actions' => [['action' => 'Raise budget']], 'evidence' => [['label' => 'Spend', 'value' => 0, 'source' => 'AdsRanger', 'date' => '2026-11-12']]]]);
        $this->assertTrue($bad['error']);
        $this->assertStringContainsString('missing: actions[0].metric', $bad['text']);

        $ok = $this->tool('save_to_client', ['client_id' => $a->id, 'type' => 'rule', 'title' => 'No prices', 'body' => 'Never mention prices in ads.']);
        $this->assertFalse($ok['error']);
        $this->assertSame('ready', $ok['data']['band']);
        $item = AiBrainItem::sole();
        $this->assertSame(['claude', $user->id, $a->id], [$item->ai_provider, $item->user_id, $item->client_id]);

        $brain = $this->tool('get_client_brain', ['client_id' => $a->id]);
        $this->assertSame('No prices', $brain['data']['saved'][0]['title']);
        $this->assertSame(['No prices'], $brain['data']['context']['rules']);
    }

    public function test_scope_probing_pauses_the_connection_and_revoke_kills_it(): void
    {
        [$agency, $user, $a, $b] = $this->workspace();
        [, , $foreign] = $this->workspace('other-ai');
        config(['modules.preview_agencies' => ['acme-ai', 'other-ai']]);
        $connection = $this->connect($agency, $user, [$a->id]);

        foreach ([$b->id, $foreign->id, $b->id] as $id) {
            $this->assertTrue($this->tool('get_client_brain', ['client_id' => $id])['error']);
        }
        $this->assertSame('paused', $connection->fresh()->status);
        $this->rpc('tools/list')->assertForbidden()->assertJsonFragment(['error' => 'This connection is paused for unusual activity. The workspace owner can restore it.']);

        $connection->fresh()->restore($user);
        $this->rpc('tools/list')->assertOk();
        $connection->fresh()->revoke($user);
        $this->rpc('tools/list')->assertForbidden();
    }

    public function test_module_off_or_member_removed_means_no_access(): void
    {
        [$agency, $user, $a] = $this->workspace();
        $connection = $this->connect($agency, $user, [$a->id]);
        config(['modules.preview_agencies' => []]);
        $this->rpc('tools/list')->assertForbidden();

        config(['modules.preview_agencies' => [$agency->slug]]);
        $agency->members()->detach($user->id);
        $this->rpc('tools/list')->assertForbidden();
        $this->assertSame('revoked', $connection->fresh()->status);
    }

    public function test_connections_screen_lists_states_and_only_the_owner_restores(): void
    {
        [$agency, $owner, $a] = $this->workspace();
        $analyst = User::factory()->create();
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);
        $mine = $this->connect($agency, $owner, [$a->id]);
        $mine->pause('Asked for a client it is not allowed to read.');

        $this->actingAs($owner, 'web')->withSession(['locale' => 'en'])->get("/app/{$agency->slug}/ai-connections")->assertOk()
            ->assertSee('Paused for unusual activity')->assertSee('Restore after review')->assertSee('https://mcp.adsranger.com');
        $this->actingAs($analyst, 'web')->withSession(['locale' => 'en'])->get("/app/{$agency->slug}/ai-connections")->assertOk()
            ->assertDontSee('Paused for unusual activity')->assertSee('No AI connected yet.');
        $this->actingAs($owner, 'web')->withSession(['locale' => 'ar'])->get("/app/{$agency->slug}/ai-connections")->assertOk()->assertSee('متوقف بسبب نشاط غير معتاد');
    }
}
