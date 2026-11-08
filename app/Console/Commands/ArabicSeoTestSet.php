<?php

namespace App\Console\Commands;

use App\Support\Arabic\Normaliser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * SEO slice 0 step 0.2 harness (FR-095): scores the Arabic SEO test set in tests/fixtures/arabic-seo against its
 * pass bars, writes storage/app/arabic-seo/results-<date>.json and prints the one summary line E1 copies into the
 * build log. Exits with 1 when a file that exists is below its bar; files not delivered yet show as "pending".
 */
class ArabicSeoTestSet extends Command
{
    protected $signature = 'seo:arabic-test-set {--dir= : Fixture folder (default tests/fixtures/arabic-seo)}';

    protected $description = 'Score the Arabic SEO test set (FR-095) against its pass bars';

    /** @var array<string, float> file => pass bar (share of rows that must pass) */
    public const BARS = [
        'must_match_spelling.csv' => 1.0,
        'must_not_match.csv' => 1.0,
        'arabizi.csv' => 0.90,
        'dialect_tags.csv' => 0.85,
        'pages/expected.json' => 1.0,
    ];

    public function handle(): int
    {
        $dir = rtrim($this->option('dir') ?: base_path('tests/fixtures/arabic-seo'), '/');
        $results = [];
        foreach (self::BARS as $file => $bar) {
            $results[$file] = $this->score($dir, $file, $bar);
        }

        $date = now('Asia/Amman')->format('Y-m-d');
        $out = ['date' => now('Asia/Amman')->format('Y-m-d H:i'), 'rules_version' => Normaliser::rules()['version'], 'files' => $results];
        File::ensureDirectoryExists(storage_path('app/arabic-seo'));
        File::put(storage_path("app/arabic-seo/results-{$date}.json"), json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $line = collect($results)->map(fn ($r, $f) => basename($f, '.csv').' '.($r['status'] === 'pending' ? 'pending' : $r['passed'].'/'.$r['rows'].' '.$r['status']))->implode(' · ');
        $this->line('arabic-seo '.$date.': '.$line);

        return collect($results)->contains(fn ($r) => $r['status'] === 'fail') ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{rows: int, passed: int, rate: float, bar: float, status: string, failures: list<string>} */
    private function score(string $dir, string $file, float $bar): array
    {
        $path = $dir.'/'.$file;
        if (! is_file($path)) {
            return ['rows' => 0, 'passed' => 0, 'rate' => 0.0, 'bar' => $bar, 'status' => 'pending', 'failures' => []];
        }
        if ($file === 'pages/expected.json') {
            $spec = json_decode((string) file_get_contents($path), true) ?: [];
            $passed = 0;
            $failures = [];
            foreach ($spec as $pageFile => $exp) {
                $pPath = $dir.'/pages/'.$pageFile;
                if (! is_file($pPath)) {
                    $failures[] = $pageFile.': missing file';
                    continue;
                }
                $html = (string) file_get_contents($pPath);
                $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
                $actual = [
                    'ar_lang' => (bool) preg_match('/<html[^>]*\blang=["\']ar/i', $html),
                    'rtl_dir' => (bool) preg_match('/<html[^>]*\bdir=["\']rtl/i', $html),
                    'has_title' => (bool) preg_match('/<title>([^<]+)<\/title>/iu', $html, $tm) && mb_strlen(trim($tm[1])) > 0,
                    'has_meta_description' => (bool) preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']+)["\']/iu', $html, $dm) && mb_strlen(trim($dm[1])) > 0,
                    'has_h1' => (bool) preg_match('/<h1[^>]*>([^<]+)<\/h1>/iu', $html),
                    'has_canonical' => (bool) preg_match('/<link[^>]*rel=["\']canonical["\']/i', $html),
                    'has_json_ld' => (bool) preg_match('/application\/ld\+json/i', $html),
                    'has_question_heading' => (bool) preg_match('/<h[23][^>]*>[^<]*(؟|\?)[^<]*<\/h[23]>/iu', $html),
                    'dialect_tags' => Normaliser::dialectTags($plain),
                ];
                if ($actual === $exp) {
                    $passed++;
                } else {
                    $failures[] = $pageFile;
                }
            }
            $total = count($spec);
            $rate = $total > 0 ? round($passed / $total, 4) : 0.0;
            return [
                'rows' => $total,
                'passed' => $passed,
                'rate' => $rate,
                'bar' => $bar,
                'status' => ($total > 0 && $rate >= $bar) ? 'pass' : 'fail',
                'failures' => array_slice($failures, 0, 10),
            ];
        }

        $rows = $this->csv($path);
        $failures = [];
        foreach ($rows as $row) {
            $ok = match ($file) {
                'must_match_spelling.csv' => Normaliser::sameKeyword($row['a'], $row['b']),
                'must_not_match.csv' => ! Normaliser::sameKeyword($row['a'], $row['b'], ($row['country'] ?? '') ?: null),
                'arabizi.csv' => in_array(Normaliser::key($row['expected']), array_map(Normaliser::key(...), Normaliser::arabiziToArabic($row['arabizi'])), true),
                'dialect_tags.csv' => $this->tags($row['tags'] ?? '') === Normaliser::dialectTags($row['text']),
                default => false,
            };
            if (! $ok) {
                $failures[] = implode(' | ', array_slice($row, 0, 2));
            }
        }
        $total = count($rows);
        $passed = $total - count($failures);
        $rate = $total > 0 ? round($passed / $total, 4) : 0.0;

        return ['rows' => $total, 'passed' => $passed, 'rate' => $rate, 'bar' => $bar, 'status' => $total > 0 && $rate >= $bar ? 'pass' : 'fail', 'failures' => array_slice($failures, 0, 50)];
    }

    /** @return list<string> tags in the fixed dialect order; "none" or empty = no tag */
    private function tags(string $cell): array
    {
        $tags = array_filter(array_map('trim', preg_split('/[\s,;|]+/', strtolower($cell))), fn ($t) => $t !== '' && $t !== 'none');

        return array_values(array_filter(Normaliser::DIALECTS, fn ($d) => in_array($d, $tags, true)));
    }

    /** @return list<array<string, string>> */
    private function csv(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $head = array_map(fn ($h) => trim($h, "\u{FEFF} "), str_getcsv(array_shift($lines)));

        return array_map(fn ($l) => array_combine($head, array_pad(str_getcsv($l), count($head), '')), $lines);
    }
}
