<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/** SEO slice 0: the 0.2 test-set harness and the two browser tools of step 0.4 (hidden until Rami approves). */
#[Group('arabic-seo')]
class SeoSlice0ToolsTest extends TestCase
{
    public function test_harness_scores_the_test_set_and_marks_missing_files_pending(): void
    {
        $this->artisan('seo:arabic-test-set')
            ->expectsOutputToContain('must_match_spelling 113/113 pass · must_not_match 7/7 pass · arabizi 12/12 pass · dialect_tags 15/15 pass · expected.json 20/20 pass')
            ->assertSuccessful();
        $this->assertFileExists(storage_path('app/arabic-seo/results-'.now('Asia/Amman')->format('Y-m-d').'.json'));
    }

    public function test_harness_scores_arabizi_and_dialects_and_fails_below_the_bar(): void
    {
        $dir = storage_path('framework/testing/arabic-seo-'.uniqid());
        File::ensureDirectoryExists($dir);
        File::put($dir.'/arabizi.csv', "arabizi,expected\n7elw,حلو\nmat3am,مطعم\n8ahwa,قهوة\nhabibi,حبيبي\n");
        File::put($dir.'/dialect_tags.csv', "text,tags\nأبغى جوال,gulf\nالمطعم فين,egypt maghreb\nأين المطعم,none\n");
        $this->artisan('seo:arabic-test-set', ['--dir' => $dir])
            ->expectsOutputToContain('arabizi 4/4 pass · dialect_tags 3/3 pass')->assertSuccessful();

        File::put($dir.'/dialect_tags.csv', "text,tags\nأبغى جوال,levant\n");
        $this->artisan('seo:arabic-test-set', ['--dir' => $dir])->expectsOutputToContain('dialect_tags 0/1 fail')->assertFailed();
        File::deleteDirectory($dir);
    }

    public function test_tools_answer_but_stay_hidden_until_published(): void
    {
        foreach (config('seo_tools.tools') as $tool) {
            foreach (['/tools/'.$tool, '/en/tools/'.$tool] as $url) {
                $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                    ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
                $this->get('/')->assertDontSee($url.'"', false);
                $this->get('/sitemaps/pages.xml')->assertDontSee('/tools/'.$tool);
            }
        }
        $this->get('/tools/serp-preview')->assertSee('window.ArSerp', false);
        $this->get('/tools/arabic-keyword-generator')->assertSee('window.ArNorm = createNormaliser', false)->assertDontSee('export function', false);
        $this->get('/tools/serp-preview')->assertSee('معاينة نتائج البحث')->assertSee('المعاينة تقديرية');
        $this->get('/tools/meta-length-checker')->assertSee('حتى 50 صفحة في كل مرة.');
        $this->get('/')->assertDontSee('/tools/serp-preview')->assertDontSee('/tools/meta-length-checker');
        $this->get('/sitemaps/pages.xml')->assertDontSee('serp-preview')->assertDontSee('meta-length-checker');

        config(['seo_tools.public' => true]);
        $this->get('/tools/serp-preview')->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertDontSee('<meta name="robots"', false);
        $this->get('/tools')->assertOk()->assertSee('الأدوات المجانية')->assertSee('/tools/page-audit', false);
        $this->get('/en/tools')->assertOk()->assertSee('Free tools')->assertSee('/en/tools/page-audit', false);
        $this->get('/sitemaps/pages.xml')->assertSee('https://adsranger.com/tools/page-audit', false);
    }
}
