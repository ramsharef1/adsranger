<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiBrainItem;
use App\Models\AiKey;
use App\Models\AiNightShift;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\AiWorkspace\AiBox;
use App\Support\AiWorkspace\NightShift;
use App\Support\AiWorkspace\OwnKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** cl105: AI Workspace M1 slice 4 — own API keys (#159), the AI box, night shift v0. Provider calls are faked. */
class AiWorkspaceM1dTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $slug = 'acme-box'): array
    {
        $agency = Agency::create(['name' => 'Acme '.$slug, 'slug' => $slug, 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR']);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => now()->subDays(2)->toDateString(), 'network' => 'meta',
            'currency' => 'SAR', 'result_type' => 'purchase', 'spend' => 300, 'revenue' => 1200, 'purchases' => 6, 'results' => 6, 'impressions' => 20000, 'link_clicks' => 400]);
        config(['modules.preview_agencies' => [$slug]]);

        return [$agency, $owner, $client];
    }

    private function key(Agency $agency, float $cap = 10): AiKey
    {
        return AiKey::create(['agency_id' => $agency->id, 'provider' => 'openai', 'api_key' => 'sk-test-1234567890abcdefWXYZ', 'last4' => 'WXYZ',
            'model' => 'gpt-4.1-mini', 'monthly_cap_usd' => $cap, 'test_ok' => true, 'tested_at' => now()]);
    }

    private function openai(array $replies): void
    {
        $seq = Http::sequence();
        foreach ($replies as $r) {
            is_int($r) ? $seq->push(['error' => ['message' => 'You exceeded your current quota', 'type' => 'insufficient_quota']], $r)
                : $seq->push(['choices' => [['message' => ['content' => is_array($r) ? json_encode($r, JSON_UNESCAPED_UNICODE) : $r]]], 'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 500]]);
        }
        Http::fake(['api.openai.com/*' => $seq]);
    }

    public function test_key_is_stored_encrypted_and_never_rendered(): void
    {
        [$agency, $owner] = $this->workspace();
        $key = $this->key($agency);
        $this->assertStringNotContainsString('sk-test', (string) \DB::table('ai_keys')->value('api_key'));
        $this->assertSame('sk-test-1234567890abcdefWXYZ', $key->fresh()->api_key);

        $this->withSession(['locale' => 'en'])->actingAs($owner)->get("/app/{$agency->slug}/ai-keys")->assertOk()
            ->assertSee('•••• WXYZ')->assertDontSee('sk-test-1234567890');
    }

    public function test_ai_box_repairs_the_format_once_then_saves_a_checked_card_and_counts_spend(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $key = $this->key($agency);
        $this->openai([
            ['finding' => 'Spend steady', 'why' => 'Same budget', 'watch' => 'CPR', 'actions' => [['action' => 'Raise budget 10%']], 'evidence' => [['label' => 'Spend', 'value' => 300, 'source' => 'AdsRanger', 'date' => '2026-11-12']]],
            ['finding' => 'Spend was 300 SAR for 6 purchases', 'why' => 'Same budget', 'watch' => 'Cost per result', 'actions' => [['action' => 'Raise the budget on the winning ad', 'metric' => 'Purchases']],
                'evidence' => [['label' => 'Spend', 'value' => 300, 'source' => 'AdsRanger', 'date' => '2026-11-12']]],
        ]);

        $r = AiBox::run($client, $key, 'weekly_summary', 'focus on purchases', $owner);
        $this->assertTrue($r['ok']);
        Http::assertSentCount(2);
        $this->assertStringContainsString('missing: actions[0].metric', Http::recorded()[1][0]->data()['messages'][1]['content']);
        $item = $r['item'];
        $this->assertSame(['ok', 'weekly_summary', 'chatgpt', 'weekly_summary'], [$item->state, $item->output_type, $item->ai_provider, $item->playbook]);
        $this->assertSame('ready', $item->band);
        // 2 calls × (2000 in × 0.40 + 500 out × 1.60) / 1M = 0.0032 USD
        $this->assertEqualsWithDelta(0.0032, $key->fresh()->spentThisMonth(), 0.00001);
    }

    public function test_the_monthly_cap_stops_a_run_before_any_call(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $tiny = $this->key($agency, 1);
        $tiny->forceFill(['spent_usd' => 0.999, 'spent_month' => now()->format('Y-m')])->save();
        Http::fake();
        $r = AiBox::run($client, $tiny, 'weekly_summary', '', $owner);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('monthly cap', $r['error']);
        Http::assertNothingSent();
    }

    public function test_a_billing_error_is_told_as_it_is_and_costs_nothing(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $key = $this->key($agency);
        $this->openai([429]);
        $r = AiBox::run($client, $key, 'weekly_summary', '', $owner);
        $this->assertFalse($r['ok']);
        $this->assertTrue($r['billing']);
        $this->assertSame(0.0, $key->fresh()->spentThisMonth());
        $this->assertSame(0, AiBrainItem::count());
    }

    public function test_night_shift_runs_once_at_the_workspace_hour_and_lists_gated_items(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $this->key($agency);
        $hour = (int) now('Asia/Riyadh')->format('G');
        $shift = AiNightShift::create(['agency_id' => $agency->id, 'enabled' => true, 'run_hour' => $hour, 'provider' => 'openai', 'client_ids' => [$client->id]]);
        $this->openai([['items' => [
            ['level' => 'warning', 'title' => 'Cost per purchase rising', 'why' => 'Spend was 300 SAR for 6 purchases.', 'action' => 'Pause ad set Broad 2.', 'campaign' => 'Ramadan'],
            ['level' => 'critical', 'title' => 'Made up', 'why' => 'Spend jumped to 9,999 SAR.', 'action' => 'Pause everything.', 'campaign' => null],
        ]]]);

        $this->assertCount(1, NightShift::due());
        $run = NightShift::run($shift->fresh('agency'));
        $this->assertSame(['ok', 1, 2], [$run->status, $run->clients_checked, $run->items_found]);
        $this->assertCount(0, NightShift::due(), 'once a day');
        $items = AiBrainItem::where('playbook', 'night_shift')->get()->keyBy('title');
        $this->assertSame('warning', $items['Cost per purchase rising']->meta['level']);
        $this->assertSame('ready', $items['Cost per purchase rising']->band);
        $this->assertSame('blocked', $items['Made up']->band, 'a number not in the data is caught');

        $this->withSession(['locale' => 'ar'])->actingAs($owner)->get("/app/{$agency->slug}/ai-night-shift")->assertOk()
            ->assertSee('تحذير')->assertSee('Cost per purchase rising')->assertDontSee('approve');
    }

    public function test_night_shift_without_a_key_fails_with_a_reason_and_screens_are_held_back(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $shift = AiNightShift::create(['agency_id' => $agency->id, 'enabled' => true, 'run_hour' => 3, 'provider' => 'anthropic', 'client_ids' => [$client->id]]);
        $run = NightShift::run($shift->fresh('agency'));
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('No key', $run->error);

        config(['modules.preview_agencies' => []]);
        foreach (['ai-keys', 'ai-night-shift'] as $page) {
            $this->actingAs($owner)->get("/app/{$agency->slug}/$page")->assertForbidden();
        }
    }

    public function test_estimates_weigh_arabic_more_and_json_is_pulled_from_fences(): void
    {
        $this->assertGreaterThan(OwnKey::tokens(str_repeat('a', 100)), OwnKey::tokens(str_repeat('ع', 100)));
        $this->assertSame(['a' => 1], OwnKey::json("```json\n{\"a\":1}\n```"));
        $this->assertNull(OwnKey::json('no json here'));
    }
}
