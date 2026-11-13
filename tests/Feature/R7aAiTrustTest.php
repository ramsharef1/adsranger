<?php

namespace Tests\Feature;

use App\Jobs\ResumeNightShift;
use App\Models\Agency;
use App\Models\AiKey;
use App\Models\AiNightShift;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\AiWorkspace\AiBox;
use App\Support\AiWorkspace\AiKill;
use App\Support\AiWorkspace\AiScope;
use App\Support\AiWorkspace\ContextPack;
use App\Support\AiWorkspace\NightShift;
use App\Support\AiWorkspace\OwnKey;
use App\Support\AiWorkspace\Redactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** R7 part 1 (D-297, D-299): FS-01 redaction, FS-02 data scope, FS-09 outage queue, FS-49 kill switch, EG-24 cache, EG-23 80 % notice, FS-38 page. */
class R7aAiTrustTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-trust', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $agency->forceFill(['owner_id' => $owner->id])->save();
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR']);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => now()->subDays(2)->toDateString(), 'network' => 'meta',
            'currency' => 'SAR', 'result_type' => 'purchase', 'spend' => 300, 'revenue' => 1200, 'purchases' => 6, 'results' => 6, 'impressions' => 20000, 'link_clicks' => 400]);
        config(['modules.preview_agencies' => ['acme-trust']]);
        $key = AiKey::create(['agency_id' => $agency->id, 'provider' => 'openai', 'api_key' => 'sk-test-1234567890abcdefWXYZ', 'last4' => 'WXYZ',
            'model' => 'gpt-4.1-mini', 'monthly_cap_usd' => 10, 'test_ok' => true, 'tested_at' => now()]);

        return [$agency, $owner, $client, $key];
    }

    private function reply(string $text): array
    {
        return ['choices' => [['message' => ['content' => $text]]], 'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 500]];
    }

    public function test_redactor_hides_phones_emails_and_long_ids_but_not_amounts(): void
    {
        $r = Redactor::clean('اتصل على +966 50 123 4567 أو ٠٥٠١٢٣٤٥٦٧ وراسل sara@clinic.sa · الهوية 1098765432 · الميزانية 3,000 SAR في 2026');
        $this->assertSame(['phone' => 2, 'email' => 1, 'id' => 1], $r['counts']);
        $this->assertStringNotContainsString('sara@clinic.sa', $r['text']);
        $this->assertStringNotContainsString('1098765432', $r['text']);
        $this->assertStringContainsString('3,000 SAR', $r['text']);
        $this->assertStringContainsString('2026', $r['text']);
    }

    public function test_provider_never_receives_personal_data_and_the_box_reports_the_count(): void
    {
        [, $owner, $client, $key] = $this->workspace();
        Http::fake(['api.openai.com/*' => Http::response($this->reply('Short note.'), 200)]);

        $r = OwnKey::complete($key, 'system', 'Lead: Sara, 0501234567, sara@x.com', false);
        $this->assertSame(2, $r['redacted']);
        $sent = json_encode(Http::recorded()[0][0]->data(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('0501234567', $sent);
        $this->assertStringNotContainsString('sara@x.com', $sent);
        $this->assertStringContainsString('[phone hidden]', $sent);
    }

    public function test_scope_limits_what_the_ai_gets_but_not_the_local_checks(): void
    {
        [, , $client] = $this->workspace();
        $full = ContextPack::for($client);
        $this->assertNotEmpty(array_filter($full['facts'], fn ($f) => $f['label'] === 'Spend'));

        $client->forceFill(['ai_scope' => ['results']])->save();
        $ai = ContextPack::forAi($client->fresh());
        $labels = array_unique(array_column($ai['facts'], 'label'));
        $this->assertNotContains('Spend', $labels);
        $this->assertNotContains('ROAS', $labels);
        $this->assertContains('Results', $labels);
        $this->assertSame(['results'], $ai['scope']);
        $this->assertSame(['spend', 'results', 'rules'], AiScope::allowed(new Client), 'everything on by default (D-299)');
    }

    public function test_night_shift_waits_for_a_down_provider_then_finishes(): void
    {
        [$agency, , $client, $key] = $this->workspace();
        Queue::fake();
        $shift = AiNightShift::create(['agency_id' => $agency->id, 'provider' => 'openai', 'enabled' => true, 'run_hour' => 3, 'client_ids' => [$client->id]]);
        Http::fake(['api.openai.com/*' => Http::sequence()->push(['error' => ['message' => 'Service unavailable']], 503)->push($this->reply('{"items": []}'), 200)]);

        $run = NightShift::run($shift);
        $this->assertSame('waiting', $run->status);
        $this->assertSame(1, (int) $run->retries);
        Queue::assertPushed(ResumeNightShift::class, fn ($job) => $job->runId === $run->id && $job->clientIds === [$client->id]);

        (new ResumeNightShift($run->id, [$client->id]))->handle();
        $this->assertSame('ok', $run->fresh()->status);
        $this->assertSame(1, (int) $run->fresh()->clients_checked);
    }

    public function test_billing_errors_are_not_retried(): void
    {
        [$agency, , $client] = $this->workspace();
        Queue::fake();
        $shift = AiNightShift::create(['agency_id' => $agency->id, 'provider' => 'openai', 'enabled' => true, 'run_hour' => 3, 'client_ids' => [$client->id]]);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'You exceeded your current quota']], 429)]);

        $this->assertSame('failed', NightShift::run($shift)->status);
        Queue::assertNothingPushed();
    }

    public function test_kill_switch_stops_the_box_and_the_night_shift(): void
    {
        [$agency, $owner, $client, $key] = $this->workspace();
        Http::fake();
        AiKill::set('ai_box', true);
        $r = AiBox::run($client, $key, 'weekly_summary', '', $owner);
        $this->assertFalse($r['ok']);
        Http::assertNothingSent();

        AiKill::set('night_shift', true);
        $shift = AiNightShift::create(['agency_id' => $agency->id, 'provider' => 'openai', 'enabled' => true, 'run_hour' => 3, 'client_ids' => [$client->id]]);
        $this->assertSame('failed', NightShift::run($shift)->status);
        Http::assertNothingSent();
    }

    public function test_same_box_request_within_4_hours_is_served_from_the_saved_answer(): void
    {
        [, $owner, $client, $key] = $this->workspace();
        Http::fake(['api.openai.com/*' => Http::response($this->reply('{"finding":"Spend was 300 SAR for 6 purchases","why":"Same budget","watch":"Cost per result","actions":[{"action":"Raise the budget on the winning ad","metric":"Purchases"}],"evidence":[{"label":"Spend","value":300,"source":"AdsRanger","date":"2026-11-12"}]}'), 200)]);

        $first = AiBox::run($client, $key, 'weekly_summary', 'focus on purchases', $owner);
        $second = AiBox::run($client, $key, 'weekly_summary', 'focus on purchases', $owner);
        $this->assertTrue($second['cached'] ?? false);
        $this->assertSame($first['item']->id, $second['item']->id);
        $this->assertSame(0.0, $second['cost']);
        Http::assertSentCount(1);
    }

    public function test_cap_notice_once_a_month_at_80_percent(): void
    {
        [, , , $key] = $this->workspace();
        $key->forceFill(['spent_usd' => 7.9, 'spent_month' => now()->format('Y-m')])->save();
        OwnKey::capNotice($key);
        $this->assertNull($key->fresh()->cap_noticed_month);

        $key->addSpend(0.2);
        OwnKey::capNotice($key);
        $this->assertSame(now()->format('Y-m'), $key->fresh()->cap_noticed_month);
    }

    public function test_transparency_page_lists_tools_and_limits(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $client->forceFill(['ai_scope' => ['results']])->save();
        AiKill::set('connector', true);
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/ai-transparency")->assertOk()
            ->assertSee('data-ai-tool="night_shift"', false)->assertSee('Paused for everyone for now')->assertSee('Riyadh Dental');
    }
}
