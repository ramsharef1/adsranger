<?php

namespace Tests\Feature;

use App\Support\Site\CopyLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** GB-06 + GB-38: Growth's copy rules on every page in the sitemap (AR + EN); new errors fail, known debt is listed in tests/fixtures/copy_lint_known.php. */
class PublicCopyLintTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function sitemapPaths(): array
    {
        $xml = $this->get('/sitemaps/pages.xml')->assertOk()->getContent();
        preg_match_all('#<loc>https?://[^/<]+(/[^<]*)</loc>#', $xml, $m);

        return array_values(array_unique($m[1]));
    }

    public function test_sitemap_pages_pass_the_copy_rules(): void
    {
        $paths = $this->sitemapPaths();
        $this->assertGreaterThan(20, count($paths));
        $errors = [];
        $warns = 0;

        $known = require base_path('tests/fixtures/copy_lint_known.php');
        $stillThere = [];

        foreach ($paths as $path) {
            // The test client drops the slash of /en/ and gets the 301 to it; the live /en/ answers 200 (Growth checks it).
            if ($path === '/en/') {
                continue;
            }
            $res = $this->get($path);
            if ($res->getStatusCode() !== 200) {
                $errors[] = "$path: HTTP ".$res->getStatusCode();

                continue;
            }
            foreach (CopyLint::check($path, (string) $res->getContent()) as $f) {
                if ($f['severity'] === 'error' && in_array("$path {$f['rule']}", $known, true)) {
                    $stillThere["$path {$f['rule']}"] = true;
                } elseif ($f['severity'] === 'error') {
                    $errors[] = "$path {$f['rule']}: {$f['found']}";
                } else {
                    $warns++;
                }
            }
        }

        if (getenv('COPY_LINT_REPORT')) {
            fwrite(STDERR, implode("\n", $errors)."\nwarnings: $warns\nknown debt left: ".count($stillThere)."\nfixed, remove from the list: ".implode(', ', array_diff($known, array_keys($stillThere)))."\n");
        }
        $this->assertSame([], $errors, 'Public copy breaks Growth\'s rules (claude/site/copy_rules_v1.json)');
    }

    public function test_rules_catch_the_cases_they_are_for(): void
    {
        $page = fn (string $lang, string $title, string $main, string $head = '') => '<html lang="'.$lang.'"><head><title>'.$title.'</title>'
            .'<meta name="description" content="'.str_repeat('وصف ', 25).'">'.$head.'</head><body><main>'.$main.'</main></body></html>';
        $pair = '<link rel="alternate" hreflang="ar" href="https://a/x"><link rel="alternate" hreflang="en" href="https://a/en/x">';
        $rules = fn (array $found) => array_values(array_unique(array_column(array_filter($found, fn ($f) => $f['severity'] === 'error'), 'rule')));

        $this->assertSame([], $rules(CopyLint::check('/x', $page('ar', 'عنوان', '<p>تقارير عبر روابط قراءة فقط للعميل.</p>', $pair))));
        $this->assertSame(['CR-07', 'CR-08', 'CR-20'], $rules(CopyLint::check('/x', $page('ar', 'عنوان', '<p>إعلانات Meta وGoogle على فيسبوك.</p>'))));
        $this->assertSame(['CR-05', 'CR-10', 'CR-17'], $rules(CopyLint::check('/x', $page('ar', str_repeat('Long title ', 7), '<p>نصل إلى حسابك الإعلاني قراءة فقط.</p>', $pair))));
        $this->assertSame(['CR-02', 'CR-19'], $rules(CopyLint::check('/en/x', $page('en', 'Title', '<h2>Frequently asked questions</h2><p>Purpose-built tools.</p>', $pair))));
    }
}
