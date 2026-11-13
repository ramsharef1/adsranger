<?php

namespace Tests\Feature;

use Tests\TestCase;

/** D-279: with the articles held, glossary terms are noindex and out of the sitemap too; a released page goes back in. */
class ArticlesHeldD279Test extends TestCase
{
    public function test_held_glossary_terms_are_noindex_and_out_of_the_sitemap_but_the_index_stays(): void
    {
        config(['seo_tools.articles_public' => false, 'seo_tools.articles_released' => []]);

        $this->get('/glossary/performance-max')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/en/glossary/performance-max')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/glossary')->assertOk()->assertHeaderMissing('X-Robots-Tag');

        $sitemap = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
        $this->assertStringNotContainsString('/glossary/', $sitemap);
        $this->assertStringContainsString('adsranger.com/glossary<', $sitemap);
        $this->assertStringNotContainsString('/guides/', $sitemap);
    }

    public function test_a_released_page_returns_in_both_languages_while_the_rest_stay_held(): void
    {
        config(['seo_tools.articles_public' => false, 'seo_tools.articles_released' => ['/guides/meta-ads-mena', '/glossary/performance-max']]);

        $this->get('/guides/meta-ads-mena')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/en/guides/meta-ads-mena')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/glossary/performance-max')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->get('/guides/ad-budget-pacing')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $sitemap = $this->get('/sitemaps/pages.xml')->getContent();
        $this->assertStringContainsString('adsranger.com/guides/meta-ads-mena<', $sitemap);
        $this->assertStringContainsString('adsranger.com/en/guides/meta-ads-mena<', $sitemap);
        $this->assertStringContainsString('adsranger.com/glossary/performance-max<', $sitemap);
        $this->assertStringNotContainsString('/guides/ad-budget-pacing<', $sitemap);
        $this->assertStringNotContainsString('adsranger.com/guides<', $sitemap);
    }
}
