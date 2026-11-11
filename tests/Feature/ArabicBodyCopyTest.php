<?php

namespace Tests\Feature;

use App\Filament\Resources\AdAccounts\AdAccountResource;
use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use App\Support\DemoData;
use App\Support\MonthlyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Arabic page body copy (O-108 part 3, UX-50…UX-56) and the auth follow-up (UX-45…UX-49). */
class ArabicBodyCopyTest extends TestCase
{
    use RefreshDatabase;

    private function owner(bool $demo = false): array
    {
        $a = Agency::create(['name' => 'Acme', 'slug' => 'acme', 'plan' => 'agency', 'status' => 'active']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'owner']);
        if ($demo) {
            DemoData::seed($a, 65);
        }

        return [$a, $u];
    }

    public function test_ar_json_is_valid_and_every_value_is_filled(): void
    {
        $raw = file_get_contents(lang_path('ar.json'));
        $ar = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(400, count($ar));
        foreach ($ar as $k => $v) {
            $this->assertIsString($v, $k);
            $this->assertNotSame('', trim($v), "empty Arabic for: $k");
        }
    }

    public function test_term_changes_ux50_to_ux53(): void
    {
        app()->setLocale('ar');
        $this->assertSame('الربط بالمنصات', __('Connections'));
        $this->assertSame('ربط', __('connection'));
        $this->assertSame('سجل التدقيق', __('audit log'));
        $this->assertSame('تقويم المنطقة ومخطِّط السيناريوهات', __('MENA calendar & season planner'));
        $this->assertSame('Google Ads: عبارات البحث، ونقاط الجودة، وحملات Performance Max', __('Google Ads: search terms, Quality Score, PMax'));
    }

    public function test_empty_states_and_bodies_render_in_arabic(): void
    {
        [$a, $u] = $this->owner();
        $base = "/app/{$a->slug}";
        $pages = [
            '/desk' => ['لا يوجد عملاء نشطون بعد.', 'حدّث بيانات اليوم', 'النتيجة الرئيسية'],
            '/alert-rules' => ['احفظ القواعد', 'شغّل القواعد الآن على بيانات أمس'],
            '/budget-plan' => ['لا يوجد عملاء بعد. أضف عميلًا أولًا، ثم خطّط ميزانيته شهرًا بشهر.'],
            '/landing-pages' => ['أضف صفحة', 'لا توجد صفحات هبوط بعد.', 'افحص الكل الآن'],
            '/notifications' => ['أرسل لي التنبيهات الحرجة الجديدة بالبريد', 'الملخص الصباحي الساعة 08:00'],
            '/pacing' => ['الأقل في مؤشر الصحة أولًا', 'أعد حساب المؤشرات'],
        ];
        $english = ['No active clients yet.', 'Save rules', 'Add a page', 'Email me new high alerts', 'Lowest health first', 'Refresh today'];
        foreach ($pages as $path => $strings) {
            $r = $this->actingAs($u)->get($base.$path)->assertOk();
            foreach ($strings as $s) {
                $r->assertSee($s);
            }
            foreach ($english as $s) {
                $r->assertDontSee('>'.$s.'<', false);
            }
        }
    }

    public function test_pages_with_data_render_in_both_languages(): void
    {
        [$a, $u] = $this->owner(true);
        $base = "/app/{$a->slug}";
        $client = Client::where('agency_id', $a->id)->orderBy('id')->first();
        foreach (['/desk', '/pacing', '/budget-plan', '/insights', '/google', '/benchmarks', '/competitors', '/calendar', '/compare', '/landing-pages', '/alert-rules', '/plan', "/clients/{$client->id}"] as $path) {
            $this->actingAs($u)->withSession(['locale' => 'ar'])->get($base.$path)->assertOk();
            $this->actingAs($u)->withSession(['locale' => 'en'])->get($base.$path)->assertOk();
        }
        $this->actingAs($u)->withSession(['locale' => 'ar'])->get("$base/clients/{$client->id}")->assertSee('اتجاه آخر 6 أشهر')->assertDontSee('6-month trend');
        $this->actingAs($u)->withSession(['locale' => 'en'])->get("$base/clients/{$client->id}")->assertSee('6-month trend');
    }

    public function test_arabic_plural_forms(): void
    {
        app()->setLocale('ar');
        $this->assertSame('مشكلة واحدة', trans_choice(AdAccountResource::ISSUES, 1));
        $this->assertSame('مشكلتان', trans_choice(AdAccountResource::ISSUES, 2));
        $this->assertSame('3 مشكلات', trans_choice(AdAccountResource::ISSUES, 3));
        $this->assertSame('11 مشكلة', trans_choice(AdAccountResource::ISSUES, 11));
        $this->assertSame('لا توجد مشكلات', trans_choice(AdAccountResource::ISSUES, 0));
        $plan = '{1} Your plan tracks up to :count ad account.|[2,*] Your plan tracks up to :count ad accounts.';
        $this->assertSame('تتابع باقتك حتى 5 حسابات إعلانية.', trans_choice($plan, 5));
        $this->assertSame('تتابع باقتك حتى 25 حسابًا إعلانيًا.', trans_choice($plan, 25));

        app()->setLocale('en');
        $this->assertSame('1 issue found', trans_choice(AdAccountResource::ISSUES, 1));
        $this->assertSame('11 issues found', trans_choice(AdAccountResource::ISSUES, 11));
        $this->assertSame('Your plan tracks up to 5 ad accounts.', trans_choice($plan, 5));
    }

    public function test_placeholder_sentences(): void
    {
        app()->setLocale('ar');
        $this->assertSame('تعذّر ربط Meta.', __('Could not connect :network.', ['network' => 'Meta']));
        app()->setLocale('en');
        $this->assertSame('Could not connect Meta.', __('Could not connect :network.', ['network' => 'Meta']));
        $this->assertSame("Deletes this import and every data row it added. This can't be undone.", __("Deletes this import and every data row it added. This can't be undone."));
    }

    public function test_auth_inputs_are_ltr_and_tall_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $lang) {
            foreach (['/app/login', '/admin/login'] as $url) {
                $html = $this->get("$url?lang=$lang")->assertOk()->getContent();
                $this->assertMatchesRegularExpression('/<input[^>]*dir="ltr"[^>]*type="email"|<input[^>]*type="email"[^>]*dir="ltr"/s', $html, "$url $lang email");
                $this->assertMatchesRegularExpression('/<input[^>]*dir="ltr"[^>]*type="password"|<input[^>]*type="password"[^>]*dir="ltr"/s', $html, "$url $lang password");
                $this->assertStringContainsString('.fi-input { min-height: 44px; }', $html);
            }
            $html = $this->get("/app/password-reset/request?lang=$lang")->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<input[^>]*dir="ltr"[^>]*type="email"|<input[^>]*type="email"[^>]*dir="ltr"/s', $html);
        }
    }

    public function test_reset_request_copy(): void
    {
        $r = $this->get('/app/password-reset/request?lang=ar')->assertOk();
        $this->assertMatchesRegularExpression('/<title>\s*إعادة تعيين كلمة المرور/u', $r->getContent());
        $r
            ->assertSee('سنرسل إليك رابطًا لإعادة التعيين')
            ->assertSee('أرسل رابط إعادة التعيين')
            ->assertSee('العودة إلى تسجيل الدخول')
            ->assertSee('/app/login', false);
        $this->get('/app/password-reset/request?lang=en')->assertOk()
            ->assertSee('Reset password')->assertSee('Send reset link')->assertSee('Back to sign in');
    }

    public function test_admin_login_title_and_heading(): void
    {
        $ar = $this->get('/admin/login?lang=ar')->assertOk()->assertDontSee('AdsRanger Admin')->getContent();
        $this->assertMatchesRegularExpression('/<title>\s*AdsRanger · مدير النظام\s*<\/title>/u', $ar);
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*AdsRanger · مدير النظام\s*<\/h1>/u', $ar);
        $en = $this->get('/admin/login?lang=en')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<title>\s*AdsRanger · System admin\s*<\/title>/u', $en);
    }

    public function test_english_error_pages_open_the_english_app(): void
    {
        $this->get('/en/does-not-exist')->assertNotFound()->assertSee(url('/app?lang=en'), false);
        $this->get('/does-not-exist')->assertNotFound()->assertDontSee('/app?lang=en', false);
    }

    public function test_pdf_signed_percentages_use_a_true_minus(): void
    {
        [$a] = $this->owner(true);
        $html = '';
        foreach (Client::where('agency_id', $a->id)->get() as $c) {
            $html .= view('reports.monthly-pdf', MonthlyReport::data($c, now()->subMonthNoOverflow()->format('Y-m'), 'ar'))->render();
        }
        $this->assertDoesNotMatchRegularExpression('/<bdo dir="ltr">-\d/', $html);
        $this->assertMatchesRegularExpression('/<bdo dir="ltr">[+\x{2212}]\d+%<\/bdo>/u', $html);
    }
}
