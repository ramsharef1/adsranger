<?php

namespace Tests\Feature;

use App\Models\WaitlistEntry;
use App\Support\Seo\PublicCatalog;
use App\Support\Seo\ToolRateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_package_c_faq_schema_and_tool_use_counter_and_email_capture(): void
    {
        $before = ToolRateLimiter::toolUseCount('serp-preview');
        $this->get('/tools/serp-preview')
            ->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('كيف تعمل أداة معاينة نتائج البحث');
        $this->assertSame($before + 1, ToolRateLimiter::toolUseCount('serp-preview'));

        $this->get('/tools/page-audit')
            ->assertOk()
            ->assertSee('id="tool-email-capture"', false)
            ->assertSee('"@type":"FAQPage"', false);

        $this->post('/tools/audit-report-request', [
            'tool' => 'page-audit',
            'email' => 'AuditLead@Agency.test',
            'agency_name' => 'Amman Growth Lab',
            'country' => 'JO',
            'consent' => '1',
        ])->assertRedirect('/tools/page-audit')->assertSessionHas('tool_report_saved');

        $this->assertTrue(WaitlistEntry::where('email', 'auditlead@agency.test')->exists());
    }

    public function test_b06_product_pages_b11_platforms_b09_glossary_and_b07_blog_render_in_ar_and_en(): void
    {
        foreach (array_keys(PublicCatalog::products()) as $slug) {
            $this->get('/'.$slug)->assertOk()->assertSee('قيد التطوير');
            $this->get('/en/'.$slug)->assertOk()->assertSee('2027');
        }

        foreach (array_keys(PublicCatalog::platforms()) as $platform) {
            $this->get('/integrations/'.$platform)->assertOk()->assertSee('تكامل مخطط له');
            $this->get('/en/integrations/'.$platform)->assertOk()->assertSee('Planned integration');
        }

        $this->get('/glossary')->assertOk()->assertSee('"@type":"DefinedTermSet"', false);
        $this->get('/en/glossary')->assertOk()->assertSee('Glossary');
        foreach (array_keys(PublicCatalog::glossary()) as $term) {
            $this->get('/glossary/'.$term)->assertOk()->assertSee('"@type":"DefinedTerm"', false);
            $this->get('/en/glossary/'.$term)->assertOk()->assertSee('"@type":"DefinedTerm"', false);
        }

        $this->get('/blog')->assertOk()->assertSee('"@type":"CollectionPage"', false);
        $this->get('/en/blog')->assertOk()->assertSee('Blog & Practical Guides');
        foreach (array_keys(PublicCatalog::posts()) as $postSlug) {
            $this->get('/blog/'.$postSlug)->assertOk()->assertSee('"@type":"Article"', false);
            $this->get('/en/blog/'.$postSlug)->assertOk()->assertSee('"@type":"Article"', false);
        }
    }
}
