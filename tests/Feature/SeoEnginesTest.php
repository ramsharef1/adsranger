<?php

namespace Tests\Feature;

use App\Support\Seo\ArabicKeywordEngine;
use App\Support\Seo\SerpReadiness;
use App\Support\Seo\SiteAuditRunner;
use Tests\TestCase;

class SeoEnginesTest extends TestCase
{
    public function test_site_audit_runner_aggregates_batch_and_detects_duplicates(): void
    {
        $p1 = (string) file_get_contents(base_path("tests/fixtures/arabic-seo/pages/page-01.html"));
        $p2 = (string) file_get_contents(base_path("tests/fixtures/arabic-seo/pages/page-02.html"));

        $batch = SiteAuditRunner::auditBatch([
            "https://example.com/ar/1" => $p1,
            "https://example.com/ar/1-copy" => $p1,
            "https://example.com/ar/2" => $p2,
        ], null, null, true);

        $this->assertSame(3, $batch["pages_audited"]);
        $this->assertTrue($batch["simulated"]);
        $this->assertNotEmpty($batch["duplicate_titles"]);
        $this->assertGreaterThanOrEqual(2, $batch["issue_counts"]["duplicate_title"]);
    }

    public function test_arabic_keyword_engine_expands_variants_questions_and_csv(): void
    {
        $res = ArabicKeywordEngine::expand("حجز فندق");
        $this->assertSame("حجز فندق", $res["seed"]);
        $this->assertFalse($res["volumes_included"]);
        $this->assertNotEmpty($res["variants"]);
        $this->assertCount(5, $res["questions"]);

        $csv = ArabicKeywordEngine::toCsv($res);
        $this->assertStringContainsString("type,term", $csv);
        $this->assertStringContainsString("question,كيف حجز فندق؟", $csv);
    }

    public function test_serp_readiness_scores_arabic_snippet_and_flags_mixed_script_bdi(): void
    {
        $eval = SerpReadiness::evaluateSnippet(
            "دليل تحسين محركات البحث عبر AdsRanger",
            "خطوات عملية لتحسين محركات البحث للمتاجر العربية.",
            "تحسين محركات البحث"
        );

        $this->assertGreaterThanOrEqual(70, $eval["score"]);
        $this->assertTrue($eval["needs_ltr_bdi"]);
    }

    public function test_search_console_analyzer_flags_opportunities_and_tags_dialects(): void
    {
        $res = \App\Support\Seo\SearchConsole::analyze();
        $this->assertSame('simulated', $res['source']);
        $this->assertNotEmpty($res['rows']);
        $opportunities = array_filter($res['rows'], fn (array $r) => $r['opportunity']);
        $this->assertNotEmpty($opportunities);
    }
}
