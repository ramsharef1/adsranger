<?php

namespace Tests\Feature;

use App\Filament\Pages\AiKeysPage;
use App\Filament\Pages\ClientBrainPage;
use App\Filament\Pages\DailyDesk;
use App\Models\Agency;
use App\Models\AiKey;
use App\Models\AiPrompt;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\AiWorkspace\AiBox;
use App\Support\AiWorkspace\Brain;
use App\Support\AiWorkspace\ContextPack;
use App\Support\AiWorkspace\GoldenSet;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** R7 end (D-301): DS-29 / NM-03 brand rules, DS-30 model check, NM-07 short mode, WS-33 one-tap fix, WS-32 prompts, WS-34 brain review, NM-04 why. */
class R7eAiEndTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r7e', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR', 'status' => 'active']);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => now()->subDays(2)->toDateString(), 'network' => 'meta',
            'currency' => 'SAR', 'result_type' => 'purchase', 'spend' => 300, 'revenue' => 1200, 'purchases' => 6, 'results' => 6, 'impressions' => 20000, 'link_clicks' => 400]);
        config(['modules.preview_agencies' => ['acme-r7e']]);
        $key = AiKey::create(['agency_id' => $agency->id, 'provider' => 'openai', 'api_key' => 'sk-test-1234567890abcdefWXYZ', 'last4' => 'WXYZ',
            'model' => 'gpt-4.1-mini', 'monthly_cap_usd' => 10, 'test_ok' => true, 'tested_at' => now()]);

        return [$agency, $owner, $client, $key];
    }

    private function reply(string $text): array
    {
        return ['choices' => [['message' => ['content' => $text]]], 'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 200]];
    }

    private function panel(Agency $agency, User $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
    }

    public function test_brand_guide_rules_reach_the_ai_only_after_a_person_adds_them(): void
    {
        [$agency, $owner, $client, $key] = $this->workspace();
        Http::fake(['api.openai.com/*' => Http::response($this->reply('{"rules":[{"rule":"Never mention prices","kind":"avoid"},{"rule":"Address patients formally","kind":"voice"}]}'), 200)]);

        $r = AiBox::run($client, $key, 'brand_rules', 'Brand guide: we never mention prices. Speak to patients formally.', $owner);
        $this->assertTrue($r['ok']);
        $this->assertSame([], ContextPack::forAi($client)['rules'], 'proposals are not rules yet');

        $this->panel($agency, $owner);
        Livewire::test(ClientBrainPage::class)->set('clientId', $client->id)->assertSee('Never mention prices')->call('approveRule', $r['item']->id, 0);
        $this->assertSame(['Never mention prices'], ContextPack::forAi($client)['rules']);
        $this->assertSame([0], $r['item']->fresh()->meta['approved']);
    }

    public function test_model_change_shows_a_notice_and_the_quick_check_runs_five_tasks_on_the_key_only(): void
    {
        [$agency, $owner, , $key] = $this->workspace();
        Http::fake(['api.openai.com/v1/models' => Http::response(['data' => []], 200),
            'api.openai.com/*' => Http::response($this->reply('Spend was 1,250.50 SAR for 42 results.'), 200)]);
        $this->panel($agency, $owner);
        Livewire::test(AiKeysPage::class)->set('provider', 'openai')->set('apiKey', 'sk-test-0000000000newkeyABCD')->set('model', 'gpt-4o-mini')->set('cap', 10)->call('save')
            ->assertSee('Run the 5-task check');
        $this->assertSame('gpt-4.1-mini', $key->fresh()->previous_model);

        $res = GoldenSet::quick($key->fresh());
        $this->assertSame(5, $res['total']);
        $this->assertSame(5, $key->fresh()->model_check['total']);
        $this->assertNull(PlatformSetting::get(GoldenSet::SCORECARD_KEY), 'an agency check never fills the admin scorecard');
    }

    public function test_short_mode_asks_for_less(): void
    {
        [, $owner, $client, $key] = $this->workspace();
        Http::fake(['api.openai.com/*' => Http::response($this->reply('Ramadan note.'), 200)]);
        AiBox::run($client, $key, 'ramadan_plan', '', $owner, null, null, true);
        $sent = Http::recorded()[0][0]->data();
        $this->assertSame(AiBox::SHORT_TOKENS, $sent['max_tokens']);
        $this->assertStringContainsString('at most 120 words', $sent['messages'][0]['content']);
    }

    public function test_one_tap_fix_removes_the_invented_number_and_checks_again(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $item = Brain::save($client, ['type' => 'output', 'title' => 'Week', 'body' => 'Spend was 300 SAR. Revenue reached 98,765 SAR.'], $owner)['item'];
        $this->assertSame('blocked', $item->band);
        $idx = collect($item->checks)->search(fn ($c) => $c['rule'] === 'number_not_in_data');

        $this->panel($agency, $owner);
        Livewire::test(ClientBrainPage::class)->set('clientId', $client->id)->assertSee('Remove this sentence')->call('fixCheck', $item->id, $idx);
        $item->refresh();
        $this->assertSame('Spend was 300 SAR.', $item->clean);
        $this->assertNotSame('blocked', $item->band);
        $this->assertStringContainsString('98,765', $item->original, 'the original reply is kept');
    }

    public function test_team_prompts_owner_saves_member_uses_and_starters_fill_the_box(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        $this->panel($agency, $owner);
        Livewire::test(ClientBrainPage::class)->set('clientId', $client->id)->set('promptKey', 's2')
            ->assertSet('boxPlaybook', 'dialect_ad_copy')->assertSet('boxNotes', 'العرض: خصم 20% حتى نهاية الشهر. لا تذكر الأسعار. الدعوة: احجز موعدك عبر واتساب.')
            ->set('promptTitle', 'Clinic offer')->call('savePrompt');
        $this->assertSame(1, AiPrompt::where('agency_id', $agency->id)->count());

        $manager = User::factory()->create();
        $agency->members()->attach($manager->id, ['role' => 'manager']);
        $this->panel($agency, $manager);
        Livewire::test(ClientBrainPage::class)->set('clientId', $client->id)->assertSee('Clinic offer')->set('promptTitle', 'x')->call('savePrompt')->assertForbidden();
    }

    public function test_brain_review_shows_on_the_desk_until_a_person_checks_it(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        Brain::save($client, ['type' => 'rule', 'title' => 'Never mention prices'], $owner);
        $this->panel($agency, $owner);
        Livewire::test(DailyDesk::class)->assertSee('Client brains to check');

        Livewire::test(ClientBrainPage::class)->set('clientId', $client->id)->call('markChecked');
        $this->assertNotNull($client->fresh()->brain_checked_at);
        Livewire::test(DailyDesk::class)->assertDontSee('Client brains to check');
    }

    public function test_night_note_shows_why(): void
    {
        [$agency, $owner, $client] = $this->workspace();
        Brain::save($client, ['type' => 'output', 'title' => 'Spend check', 'body' => 'Spend was 300 SAR.', 'playbook' => 'night_shift', 'meta' => ['level' => 'info']]);
        $this->panel($agency, $owner);
        Livewire::test(DailyDesk::class)->assertSee('Show me why')->assertSee('300 SAR');
    }
}
