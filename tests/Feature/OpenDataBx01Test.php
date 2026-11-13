<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

/** cl107: BX-01 /data hub on our own domain + BX-03 "cite this" blocks and stable anchors. */
class OpenDataBx01Test extends TestCase
{
    public function test_data_hub_lists_the_calendar_with_files_licence_schema_and_citation(): void
    {
        $this->get('/en/data')->assertOk()
            ->assertSee('MENA ad calendar 2027')->assertSee('/data/mena-ad-calendar-2027.csv', false)->assertSee('/data/mena-ad-calendar-2027.json', false)
            ->assertSee('"@type":"Dataset"', false)->assertSee('"license":"https://creativecommons.org/licenses/by/4.0/"', false)->assertSee('"contentUrl":"https://adsranger.com/data/mena-ad-calendar-2027.csv"', false)
            ->assertSee('id="cite"', false)->assertSee('Cite this page')->assertSee('Licence: CC BY 4.0.');
        $this->get('/data')->assertOk()->assertSee('تقويم المواسم الإعلانية 2027')->assertSee('استشهد بهذه الصفحة')->assertSee('hreflang="en"', false);
        foreach (config('datasets.mena-ad-calendar-2027.files') as [, $path]) {
            $this->assertFileExists(public_path(ltrim($path, '/')), $path);
        }
        $this->assertStringContainsString('/data', $this->get('/sitemap.xml')->getContent().$this->get('/sitemaps/pages.xml')->getContent());
    }

    /** The published files must match the calendar the site shows: a config change needs a new release in public/data. */
    public function test_published_calendar_matches_the_site_config(): void
    {
        $json = json_decode(file_get_contents(public_path('data/mena-ad-calendar-2027.json')), true);
        $this->assertSame(config('datasets.mena-ad-calendar-2027.rows'), count($json['rows']));
        $this->assertSame('CC BY 4.0', $json['license']);
        $csvRows = count(file(public_path('data/mena-ad-calendar-2027.csv'), FILE_SKIP_EMPTY_LINES)) - 1;
        $this->assertSame(count($json['rows']), $csvRows, 'CSV and JSON carry the same rows');

        $config = collect(config('mena_calendar_2027.rows'))->filter(fn ($r) => $r['date'] !== null);
        $published = [];
        foreach ($json['rows'] as $r) {
            $this->assertMatchesRegularExpression('/(\d{1,2})(?:\D+?\d{1,2})?\s+([A-Za-z]+)\s+(\d{4})/', $r['date_en']);
            preg_match('/(\d{1,2})(?:\D+?\d{1,2})?\s+([A-Za-z]+)\s+(\d{4})/', $r['date_en'], $m);
            $published[$r['event_en']] = Carbon::parse("$m[1] $m[2] $m[3]")->toDateString();
        }
        foreach ($config as $c) {
            $this->assertArrayHasKey($c['event_en'], $published, "{$c['id']} is on the site but not in the published files");
            $this->assertSame($c['date'], $published[$c['event_en']], "{$c['id']} date changed: publish a new release in public/data + config/datasets.php");
        }
        $this->assertCount($config->count(), $published, 'no published row that the site no longer shows');
    }

    public function test_calendar_and_glossary_have_stable_anchors_and_a_citation(): void
    {
        $this->get('/en/mena-calendar')->assertOk()->assertSee('<tr id="A2"', false)->assertSee('id="cite"', false)->assertSee('/en/data', false);
        $this->get('/mena-calendar')->assertOk()->assertSee('استشهد بهذه الصفحة');
        $this->get('/en/glossary/roas')->assertOk()->assertSee('id="formula"', false)->assertSee('id="example"', false)->assertSee('Copy citation');
    }
}
