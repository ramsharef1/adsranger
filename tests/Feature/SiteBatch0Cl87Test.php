<?php

namespace Tests\Feature;

use App\Support\Seo\PageAudit;
use Tests\TestCase;

/** cl87 (Rami's ADSR-920 picks, Batch 0): GB-01 articles + glossary terms listed (switch can hold them), UX-253 page-audit notes. */
class SiteBatch0Cl87Test extends TestCase
{
    public function test_held_articles_answer_with_noindex_and_stay_out_of_sitemap_and_llms(): void
    {
        config(['seo_tools.articles_public' => false]);

        $this->get('/guides/meta-ads-mena')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/en/blog')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('We are rewriting these guides')
            ->assertDontSee('/en/blog/eid-al-fitr-2027-campaign-checklist', false);

        $sitemap = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('adsranger.com/guides<', $sitemap);
        $this->assertStringNotContainsString('adsranger.com/blog<', $sitemap);
        $this->get('/llms.txt')->assertOk()->assertDontSee('[Guides]', false);

        $this->get('/glossary/performance-max')->assertOk()->assertDontSee('/guides/', false);
    }

    public function test_published_articles_are_listed_again(): void
    {
        config(['seo_tools.articles_public' => true]);

        $this->get('/guides/meta-ads-mena')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/guides')->assertOk()->assertSee('/guides/meta-ads-mena', false);
        $sitemap = $this->get('/sitemaps/pages.xml')->getContent();
        $this->assertStringContainsString('adsranger.com/guides<', $sitemap);
        $this->assertStringContainsString('adsranger.com/guides/meta-ads-mena<', $sitemap);
        $this->assertStringContainsString('adsranger.com/en/blog/eid-al-fitr-2027-campaign-checklist<', $sitemap);
        $this->assertStringContainsString('adsranger.com/glossary/performance-max<', $sitemap);
    }

    public function test_page_audit_names_the_small_checks_it_deducts_for(): void
    {
        $html = '<html lang="ar" dir="rtl"><head><title>عنوان</title><meta name="description" content="وصف">'
            .'<link rel="canonical" href="https://a.example/"></head><body><h1>عنوان</h1></body></html>';
        $r = PageAudit::auditHtml($html);

        $this->assertSame(90, $r['score']);
        $this->assertSame([], $r['issues']);
        $this->assertSame(['no_json_ld', 'no_question_heading'], $r['notes']);
    }
}
