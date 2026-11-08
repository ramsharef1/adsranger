<?php

namespace Tests\Feature;

use App\Support\Seo\PageAudit;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PageAuditTest extends TestCase
{
    public function test_well_formed_arabic_page_scores_100(): void
    {
        $html = (string) file_get_contents(base_path('tests/fixtures/arabic-seo/pages/page-01.html'));
        $res = PageAudit::auditHtml($html);

        $this->assertSame(100, $res['score']);
        $this->assertTrue($res['checks']['ar_lang']);
        $this->assertTrue($res['checks']['rtl_dir']);
        $this->assertTrue($res['checks']['single_h1']);
        $this->assertTrue($res['checks']['has_canonical']);
        $this->assertTrue($res['checks']['has_json_ld']);
        $this->assertSame([], $res['issues']);
    }

    public function test_unoptimised_page_flags_missing_rtl_h1_and_alt(): void
    {
        $html = (string) file_get_contents(base_path('tests/fixtures/arabic-seo/pages/page-02.html'));
        $res = PageAudit::auditHtml($html);

        $this->assertFalse($res['checks']['ar_lang']);
        $this->assertFalse($res['checks']['rtl_dir']);
        $this->assertFalse($res['checks']['has_h1']);
        $this->assertFalse($res['checks']['images_have_alt']);
        $this->assertContains('missing_ar_lang', $res['issues']);
        $this->assertContains('missing_rtl_dir', $res['issues']);
        $this->assertContains('missing_h1', $res['issues']);
        $this->assertContains('image_missing_alt', $res['issues']);
    }

    public function test_arabic_seo_test_set_command_passes_all_five_bars(): void
    {
        $exit = Artisan::call('seo:arabic-test-set');
        $out = Artisan::output();

        $this->assertSame(0, $exit, $out);
        $this->assertStringContainsString('must_match_spelling 113/113 pass', $out);
        $this->assertStringContainsString('expected.json 20/20 pass', $out);
    }
}
