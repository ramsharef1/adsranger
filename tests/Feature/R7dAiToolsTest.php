<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiKey;
use App\Models\AiNightShift;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\SeoAudit;
use App\Models\SeoAuditPage;
use App\Models\SeoIssue;
use App\Models\SeoSite;
use App\Models\User;
use App\Support\AiWorkspace\AiBox;
use App\Support\AiWorkspace\Glossary;
use App\Support\AiWorkspace\NightShift;
use App\Support\AiWorkspace\Playbooks;
use App\Support\Seo\Audit\PageParser;
use App\Support\Seo\Audit\Rules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** R7 part 4: FS-06 glossary-locked translation, FS-13 alt text, FS-14 audit summary, FS-15 dialect negatives, FS-07 night shift reads the site audit. */
class R7dAiToolsTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r7d', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Riyadh Dental', 'currency' => 'SAR']);
        DailyMetric::create(['agency_id' => $agency->id, 'client_id' => $client->id, 'date' => now()->subDays(2)->toDateString(), 'network' => 'meta',
            'currency' => 'SAR', 'result_type' => 'purchase', 'spend' => 300, 'revenue' => 1200, 'purchases' => 6, 'results' => 6, 'impressions' => 20000, 'link_clicks' => 400]);
        config(['modules.preview_agencies' => ['acme-r7d']]);
        $key = AiKey::create(['agency_id' => $agency->id, 'provider' => 'openai', 'api_key' => 'sk-test-1234567890abcdefWXYZ', 'last4' => 'WXYZ',
            'model' => 'gpt-4.1-mini', 'monthly_cap_usd' => 10, 'test_ok' => true, 'tested_at' => now()]);

        return [$agency, $owner, $client, $key];
    }

    private function reply(string $text): array
    {
        return ['choices' => [['message' => ['content' => $text]]], 'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 200]];
    }

    private function audit(Agency $agency, Client $client, array $issues, ?string $finished = null): SeoAudit
    {
        $site = SeoSite::firstOrCreate(['agency_id' => $agency->id, 'client_id' => $client->id, 'host' => 'riyadh-dental.sa'], ['url' => 'https://riyadh-dental.sa']);
        $audit = SeoAudit::create(['agency_id' => $agency->id, 'seo_site_id' => $site->id, 'status' => 'done', 'pages_done' => 12, 'score' => 71,
            'critical_count' => count(array_filter($issues, fn ($i) => $i[1] === 'critical')), 'finished_at' => $finished ?? now()]);
        $page = SeoAuditPage::create(['seo_audit_id' => $audit->id, 'url' => 'https://riyadh-dental.sa/ar/implants', 'title' => 'زراعة الأسنان في الرياض', 'h1' => 'زراعة الأسنان']);
        foreach ($issues as [$code, $level, $detail]) {
            SeoIssue::create(['seo_audit_id' => $audit->id, 'seo_audit_page_id' => $page->id, 'code' => $code, 'level' => $level, 'detail' => $detail]);
        }

        return $audit;
    }

    public function test_glossary_check_counts_kept_terms_and_flags_arabic_brand_rules(): void
    {
        $src = 'Spend rose on Meta while Cost per result fell.';
        $this->assertSame('ar', Glossary::target($src));
        $good = Glossary::check($src, 'ارتفع الإنفاق على Meta بينما انخفضت تكلفة النتيجة.', 'ar');
        $this->assertSame(['kept' => 3, 'total' => 3, 'missing' => [], 'lint' => []], $good);

        $bad = Glossary::check($src, 'ارتفعت المصاريف على ميتا ولMeta تكلفة النتيجة.', 'ar');
        $this->assertSame(['Spend → الإنفاق'], array_values(array_filter($bad['missing'], fn ($m) => str_contains($m, '→'))));
        $this->assertCount(2, $bad['lint'], 'joined letter + platform in Arabic letters');

        $this->assertSame(['kept' => 2, 'total' => 2, 'missing' => [], 'lint' => []], Glossary::check('زاد الإنفاق والإيراد', 'Spend and revenue went up', 'en'));
    }

    public function test_translation_sends_only_the_glossary_and_keeps_the_numbers_of_the_source(): void
    {
        [, $owner, $client, $key] = $this->workspace();
        Http::fake(['api.openai.com/*' => Http::response($this->reply('ارتفع الإنفاق إلى 4,750 SAR على Meta.'), 200)]);

        $r = AiBox::run($client, $key, 'translate', 'Spend rose to 4,750 SAR on Meta.', $owner);
        $this->assertTrue($r['ok']);
        $sent = json_decode(Http::recorded()[0][0]->data()['messages'][1]['content'], true);
        $this->assertSame([['en' => 'Spend', 'ar' => 'الإنفاق']], $sent['context']['glossary']);
        $this->assertArrayNotHasKey('facts', $sent['context'], 'a translation does not need the client numbers');
        $this->assertSame(2, $r['item']->meta['glossary']['kept']);
        $this->assertNotSame('blocked', $r['item']->band, 'numbers from the person\'s own text are known');
    }

    public function test_clear_negatives_cost_nothing_and_only_unclear_terms_go_to_the_ai(): void
    {
        [, $owner, $client, $key] = $this->workspace();
        Http::fake(['api.openai.com/*' => Http::response($this->reply('{"negatives":[{"term":"عيادة اسنان القاهرة","reason":"City the client does not serve"},{"term":"invented","reason":"x"}]}'), 200)]);
        $r = AiBox::run($client, $key, 'dialect_negatives', "وظائف عيادة اسنان\nكورس تبييض مجاني", $owner);
        $this->assertTrue($r['ok']);
        $this->assertSame(0.0, $r['cost']);
        Http::assertNothingSent();
        $this->assertSame(['وظائف عيادة اسنان', 'كورس تبييض مجاني'], array_column($r['item']->output['negatives'], 'term'));
        $this->assertContains('وظايف عياده اسنان', $r['item']->output['negatives'][0]['forms']);

        $r = AiBox::run($client, $key, 'dialect_negatives', "تحميل صور اسنان\nعيادة اسنان القاهرة\nزراعة اسنان الرياض", $owner);
        $this->assertTrue($r['ok'], (string) ($r['error'] ?? ''));
        $sent = json_decode(Http::recorded()[0][0]->data()['messages'][1]['content'], true);
        $this->assertSame(['عيادة اسنان القاهرة', 'زراعة اسنان الرياض'], $sent['context']['terms']);
        $this->assertSame(['تحميل صور اسنان', 'عيادة اسنان القاهرة'], array_column($r['item']->output['negatives'], 'term'), 'a term the AI invented is dropped');
    }

    public function test_alt_text_and_audit_summary_use_the_last_audit_and_stop_without_one(): void
    {
        [$agency, $owner, $client, $key] = $this->workspace();
        Http::fake();
        $this->assertSame(__('Run a site audit for this client first.'), AiBox::run($client, $key, 'alt_text', '', $owner)['error']);
        Http::assertNothingSent();

        $p = PageParser::parse('<html lang="ar" dir="rtl"><head><title>t</title></head><body><img src="/img/implant-1.jpg"><img src="data:image/png;base64,AA"><img src="x.png" alt="x"></body></html>', 'https://riyadh-dental.sa/ar/implants');
        $img = collect(Rules::page($p, 'https://riyadh-dental.sa/ar/implants', 200))->firstWhere(0, 'IMG-01');
        $this->assertSame('2 img: https://riyadh-dental.sa/img/implant-1.jpg', $img[1]);

        $this->audit($agency, $client, [['IMG-01', 'info', $img[1]], ['AR-RTL-01', 'critical', '']]);
        $env = Playbooks::envelope($client, 'alt_text');
        $this->assertSame(['https://riyadh-dental.sa/img/implant-1.jpg'], $env['context']['images'][0]['images']);
        $sum = Playbooks::envelope($client, 'audit_summary')['context']['audit'];
        $this->assertSame('critical', $sum['top'][0]['level']);
        $this->assertSame(71, $sum['score']);
    }

    public function test_night_shift_gets_new_critical_site_issues_once(): void
    {
        [$agency, , $client] = $this->workspace();
        $this->audit($agency, $client, [['AR-RTL-01', 'critical', '']], now()->subDays(8));
        $this->audit($agency, $client, [['AR-RTL-01', 'critical', ''], ['STATUS-01', 'critical', '500']]);
        Http::fake(['api.openai.com/*' => Http::response($this->reply('{"items": []}'), 200)]);
        $shift = AiNightShift::create(['agency_id' => $agency->id, 'provider' => 'openai', 'enabled' => true, 'run_hour' => 3, 'client_ids' => [$client->id]]);

        NightShift::run($shift);
        $sent = json_decode(Http::recorded()[0][0]->data()['messages'][1]['content'], true);
        $this->assertCount(1, $sent['context']['new_site_issues'], 'only the issue the audit before did not have');
    }
}
