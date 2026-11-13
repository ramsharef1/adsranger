<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\AiScorecardBoard;
use App\Filament\Pages\ClientBrainPage;
use App\Models\Agency;
use App\Models\AiBrainItem;
use App\Models\AiKey;
use App\Models\Client;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\AiWorkspace\Brain;
use App\Support\AiWorkspace\ContextPack;
use App\Support\AiWorkspace\GoldenSet;
use App\Support\AiWorkspace\StyleNotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** R7 part 3 (D-300): WS-09 / FS-12 golden set + provider scorecard, FS-05 style notes from edits. */
class R7cGoldenSetStyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_saved_golden_answer_keeps_its_expected_result(): void
    {
        $results = GoldenSet::offline();
        $this->assertGreaterThanOrEqual(20, count($results));
        $this->assertSame([], array_values(array_filter($results, fn ($r) => ! $r['ok'])), 'the quality gate changed its verdict on a golden answer');
        $dialects = array_unique(array_column(GoldenSet::load()['items'], 'dialect'));
        foreach (['msa', 'gulf', 'levant', 'egypt', 'arabizi', 'en'] as $d) {
            $this->assertContains($d, $dialects);
        }
    }

    public function test_live_run_on_a_key_fills_the_scorecard(): void
    {
        $agency = Agency::create(['name' => 'Lab', 'slug' => 'lab', 'plan' => 'pro', 'status' => 'active']);
        $key = AiKey::create(['agency_id' => $agency->id, 'provider' => 'openai', 'api_key' => 'sk-test-1234567890abcdefWXYZ', 'last4' => 'WXYZ',
            'model' => 'gpt-4.1-mini', 'monthly_cap_usd' => 50, 'test_ok' => true, 'tested_at' => now()]);
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => 'Spend was 1,250.50 SAR for 42 results.']]], 'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 100]])
            ->push(['choices' => [['message' => ['content' => 'Results were 999 at 1.00 SAR.']]], 'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 100]])
            ->whenEmpty(Http::response(['choices' => [['message' => ['content' => 'CTR was 1.86% over the last 7 days.']]], 'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 100]]))]);

        $this->assertGreaterThan(0, GoldenSet::estimate($key));
        $r = GoldenSet::live($key);
        $this->assertSame($r['total'] - 1, $r['passed'], 'the invented-number answer fails');
        $this->assertCount(1, $r['failed']);
        $this->assertArrayHasKey('openai|gpt-4.1-mini', PlatformSetting::get(GoldenSet::SCORECARD_KEY));

        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);
        Livewire::test(AiScorecardBoard::class)->assertSee('gpt-4.1-mini')->set('keyId', $key->id)->call('estimateRun')->assertSet('estimate', GoldenSet::estimate($key));
    }

    public function test_word_swaps_become_style_notes_but_numbers_do_not(): void
    {
        $this->assertSame([['from' => 'الزبائن', 'to' => 'العملاء']], StyleNotes::swaps('زاد عدد الزبائن هذا الأسبوع', 'زاد عدد العملاء هذا الأسبوع'));
        $this->assertSame([], StyleNotes::swaps('Spend was 300 SAR', 'Spend was 310 SAR'));
        $this->assertSame([['from' => 'buy now', 'to' => 'order today']], StyleNotes::swaps('Tap buy now to start', 'Tap order today to start'));
    }

    public function test_editing_a_draft_learns_a_rule_the_ai_will_get(): void
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-edit', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR']);
        config(['modules.preview_agencies' => ['acme-edit']]);
        $item = Brain::save($client, ['type' => 'output', 'title' => 'Draft', 'body' => 'نشكر الزبائن على ثقتهم'], $owner)['item'];

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(ClientBrainPage::class)->set('clientId', $client->id)->call('startEdit', $item->id)
            ->set('editText', 'نشكر العملاء على ثقتهم')->call('saveEdit');

        $this->assertSame('نشكر العملاء على ثقتهم', $item->fresh()->clean);
        $rule = AiBrainItem::where('client_id', $client->id)->where('type', 'rule')->firstOrFail();
        $this->assertSame('«العملاء» ← «الزبائن»', $rule->title);
        $this->assertContains($rule->title, ContextPack::forAi($client)['rules']);
    }
}
