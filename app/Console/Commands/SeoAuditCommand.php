<?php

namespace App\Console\Commands;

use App\Support\Seo\Audit\Catalog;
use App\Support\Seo\Audit\PageParser;
use App\Support\Seo\Audit\Rules;
use Illuminate\Console\Command;

/**
 * A1 harness (910 BUILD START step 7): runs the audit rules over the FR-095 page fixtures against pages/expected_audit.json.
 * Pass bar: 100 % of expected Critical codes found, 0 unexpected Critical. With --url=<page> it prints the live rules for one page instead.
 */
class SeoAuditCommand extends Command
{
    protected $signature = 'seo:audit-fixtures {--dir=tests/fixtures/arabic-seo/pages} {--write : rewrite expected_audit.json from the current rules}';

    protected $description = 'Check the site-audit rules against the Arabic SEO page fixtures (FR-089 A1 pass bar)';

    public function handle(): int
    {
        $dir = base_path($this->option('dir'));
        $expectedPath = $dir.'/expected_audit.json';
        $expected = is_file($expectedPath) ? (json_decode((string) file_get_contents($expectedPath), true) ?: []) : [];
        $actual = [];
        $pages = [];
        foreach (glob($dir.'/page-*.html') ?: [] as $file) {
            $name = basename($file);
            $html = (string) file_get_contents($file);
            // The fixture's own canonical is its address (Product wrote them as real pages); synthetic path otherwise.
            $lang = preg_match('/<html[^>]*\blang=["\']([a-z]{2})/i', $html, $lm) ? strtolower($lm[1]) : 'ar';
            $url = preg_match('/<link[^>]*hreflang=["\']'.$lang.'(?:[-_][A-Za-z]+)?["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m)
                || preg_match('/<link[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m) ? $m[1] : 'https://example.com/ar/'.pathinfo($name, PATHINFO_FILENAME);
            $parsed = PageParser::parse($html, $url);
            $pages[$url] = ['parsed' => $parsed, 'status' => 200, 'name' => $name];
        }
        $siteIssues = Rules::site(array_map(fn (array $p) => ['parsed' => $p['parsed'], 'status' => 200], $pages));
        foreach ($pages as $url => $p) {
            $codes = array_values(array_unique(array_map(fn (array $i) => $i[0], array_merge(Rules::page($p['parsed'], $url, 200), $siteIssues[$url] ?? []))));
            sort($codes);
            $actual[$p['name']] = $codes;
        }
        ksort($actual);
        if ($this->option('write')) {
            file_put_contents($expectedPath, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            $this->line('expected_audit.json written for '.count($actual).' fixtures');

            return self::SUCCESS;
        }
        $critExpected = 0;
        $critFound = 0;
        $falseCrit = 0;
        $missing = [];
        foreach ($expected as $name => $codes) {
            $got = $actual[$name] ?? [];
            foreach ($codes as $code) {
                if (Catalog::level($code) === Catalog::CRITICAL) {
                    $critExpected++;
                    if (in_array($code, $got, true)) {
                        $critFound++;
                    } else {
                        $missing[] = "$name: $code";
                    }
                }
            }
            foreach ($got as $code) {
                if (Catalog::level($code) === Catalog::CRITICAL && ! in_array($code, $codes, true)) {
                    $falseCrit++;
                    $missing[] = "$name: unexpected $code";
                }
            }
        }
        foreach ($missing as $m) {
            $this->error($m);
        }
        $this->line(sprintf('audit-fixtures %s: critical %d/%d found · %d false critical · %d fixtures', date('Y-m-d'), $critFound, $critExpected, $falseCrit, count($actual)));

        return ($critExpected > 0 && $critFound === $critExpected && $falseCrit === 0) ? self::SUCCESS : self::FAILURE;
    }
}
