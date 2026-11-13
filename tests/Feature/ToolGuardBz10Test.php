<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Seo\ToolGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** BZ-10: free tools refuse auto-blocked visitors / hosts and hand-blocked hosts; /admin shows counts and blocks. */
class ToolGuardBz10Test extends TestCase
{
    use RefreshDatabase;

    private const PAGE = '<html lang="ar" dir="rtl"><head><title>t</title></head><body><h1>t</h1></body></html>';

    public function test_a_visitor_who_keeps_hitting_the_limit_is_refused_for_a_day(): void
    {
        Http::fake(['*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);
        $who = hash('sha256', config('app.key').'|127.0.0.1');

        for ($i = 0; $i < ToolGuard::AUTO_VISITOR; $i++) {
            ToolGuard::recordLimitHit('page-audit', $who);
        }
        $this->assertArrayHasKey('visitor:'.$who, ToolGuard::autoBlocks());

        $this->postJson('/tools/page-audit/run', ['url' => 'https://example.com/'])->assertStatus(429)->assertJsonPath('error', 'limit');
        $this->assertSame(1, ToolGuard::counts('page-audit')['refused']);

        ToolGuard::lift('visitor:'.$who);
        $this->postJson('/tools/page-audit/run', ['url' => 'https://example.com/'])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_hosts_we_cannot_read_are_counted_and_then_refused(): void
    {
        Http::fake(['*' => Http::response('gone', 404)]);
        for ($i = 0; $i < ToolGuard::AUTO_HOST; $i++) {
            ToolGuard::recordFailure('page-audit', 'bad.example');
        }
        $this->assertArrayHasKey('host:bad.example', ToolGuard::autoBlocks());
        $this->postJson('/tools/page-audit/run', ['url' => 'https://www.bad.example/x'])->assertStatus(429);

        $this->postJson('/tools/page-audit/run', ['url' => 'https://other.example/'])->assertOk()->assertJsonPath('ok', false);
        $this->assertSame(ToolGuard::AUTO_HOST + 1, ToolGuard::counts('page-audit')['fail'], 'the 20 recorded above + this unreadable run');
    }

    public function test_limit_replies_are_counted_and_hand_blocks_apply_to_every_url_tool(): void
    {
        $this->assertTrue(ToolGuard::blockHost('https://Spam.example/path'));
        $this->assertFalse(ToolGuard::blockHost('not a host'));
        $this->assertSame(['spam.example'], ToolGuard::manualHosts());
        foreach (['/tools/hreflang-checker/run', '/tools/ad-pixel-checker/run', '/tools/llms-txt-generator/check', '/tools/robots-sitemap-checker/run'] as $path) {
            $this->postJson($path, ['url' => 'https://spam.example/'])->assertStatus(429);
        }

        Cache::flush();
        Http::fake(['*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);
        ToolGuard::lift('manual:spam.example');
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/tools/page-audit/run', ['url' => 'https://ok.example/']);
        }
        $this->assertSame(1, ToolGuard::counts('page-audit')['limit'], 'the 6th run hits the daily limit of 5');
    }

    public function test_admin_board_shows_counts_and_blocks_without_ips(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
        ToolGuard::recordLimitHit('page-audit', str_repeat('a', 64));
        ToolGuard::blockHost('spam.example');

        $this->actingAs($admin)->get('/admin/tool-guard?lang=en')->assertOk()
            ->assertSee('Free tools guard')->assertSee('spam.example')->assertSee('/tools/page-audit')->assertDontSee('127.0.0.1');
        $this->actingAs(User::factory()->create())->get('/admin/tool-guard?lang=en')->assertForbidden();
    }
}
