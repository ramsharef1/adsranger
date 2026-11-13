<?php

namespace App\Support\Ops;

use App\Models\PlatformSetting;
use App\Support\Seo\Hreflang;
use App\Support\Seo\PageAudit;
use App\Support\Seo\RobotsSitemapChecker;
use App\Support\Seo\SafeFetcher;
use App\Support\Site\CopyLint;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WS-01 + LV-17 + WS-02 (D-289): nightly, read every public page in our sitemap and every URL in /llms.txt from the live
 * site, run Growth's copy rules (CopyLint) on each page, and run the server-side free tools once on our own site. Errors
 * (not warnings) become one health line; the full list is on /admin › Operations. Reads public pages only.
 */
class LiveSiteCheck
{
    public const LAST_KEY = 'ops.live_site.last';

    /** @var (\Closure(string): array{status: int, body: string})|null tests swap the fetcher */
    public static ?\Closure $fetch = null;

    /** @return array{at: string, pages: int, errors: list<string>, warnings: int, tools: array<string, string>} */
    public static function run(): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $get = self::$fetch ?? function (string $url): array {
            try {
                $r = Http::timeout(15)->withHeaders(['User-Agent' => 'AdsRangerBot/1.0 (+https://adsranger.com/bot)'])->get($url);

                return ['status' => $r->status(), 'body' => (string) $r->body()];
            } catch (Throwable) {
                return ['status' => 0, 'body' => ''];
            }
        };
        $known = is_file(base_path('tests/fixtures/copy_lint_known.php')) ? (array) require base_path('tests/fixtures/copy_lint_known.php') : [];

        $paths = [];
        $index = $get($base.'/sitemap.xml')['body'];
        foreach (self::locs($index) as $loc) {
            $paths = array_merge($paths, str_ends_with($loc, '.xml') ? self::locs($get($loc)['body']) : [$loc]);
        }
        $paths = array_values(array_unique(array_map(fn ($u) => (string) (parse_url($u, PHP_URL_PATH) ?: '/'), $paths)));

        $errors = [];
        $warnings = 0;
        foreach ($paths as $path) {
            $r = $get($base.$path);
            if ($r['status'] !== 200) {
                $errors[] = "$path: HTTP {$r['status']}";

                continue;
            }
            foreach (CopyLint::check($path, $r['body']) as $f) {
                if ($f['severity'] !== 'error') {
                    $warnings++;
                } elseif (! in_array("$path {$f['rule']}", $known, true)) {
                    $errors[] = "$path {$f['rule']}: {$f['found']}";
                }
            }
        }

        // LV-17: every URL listed in /llms.txt answers 200.
        preg_match_all('~\((https?://[^)\s]+)\)~', $get($base.'/llms.txt')['body'], $m);
        foreach (array_unique($m[1] ?? []) as $url) {
            $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
            if (! in_array($path, $paths, true) && ($s = $get($base.$path)['status']) !== 200) {
                $errors[] = "/llms.txt → $path: HTTP $s";
            }
        }

        $result = ['at' => now()->toIso8601String(), 'pages' => count($paths), 'errors' => array_slice($errors, 0, 100), 'warnings' => $warnings, 'tools' => self::tools($base)];
        PlatformSetting::put(self::LAST_KEY, $result);

        return $result;
    }

    /** WS-02: the server-side free tools, run in-process on our own home page (no CSRF, no visitor limits used). */
    protected static function tools(string $base): array
    {
        if (self::$fetch) {
            return ['hreflang-checker' => 'skipped', 'page-audit' => 'skipped', 'robots-sitemap-checker' => 'skipped'];
        }
        $url = $base.'/';
        $run = function (callable $fn): string {
            try {
                $r = $fn();

                return is_array($r) && empty($r['error']) && ($r['ok'] ?? true) !== false ? 'ok' : 'error: '.mb_substr((string) ($r['error'] ?? 'no result'), 0, 60);
            } catch (Throwable $e) {
                return 'error: '.mb_substr($e->getMessage(), 0, 60);
            }
        };

        return [
            'hreflang-checker' => $run(fn () => Hreflang::check($url)),
            'page-audit' => $run(function () use ($url) {
                $f = SafeFetcher::fetch($url);

                return ($f['ok'] ?? false) ? PageAudit::auditHtml($f['html']) : ['error' => $f['error'] ?? 'fetch'];
            }),
            'robots-sitemap-checker' => $run(fn () => RobotsSitemapChecker::check($url)),
        ];
    }

    protected static function locs(string $xml): array
    {
        preg_match_all('~<loc>([^<]+)</loc>~', $xml, $m);

        return $m[1] ?? [];
    }

    /** @return list<string> health lines */
    public static function problems(): array
    {
        $last = PlatformSetting::get(self::LAST_KEY);
        if (! is_array($last)) {
            return [];
        }
        $p = [];
        if (($n = count($last['errors'] ?? [])) > 0) {
            $p[] = __('Copy or link errors found by the nightly live-site check: :n', ['n' => $n]);
        }
        $bad = collect($last['tools'] ?? [])->reject(fn ($s) => in_array($s, ['ok', 'skipped'], true))->keys()->all();
        if ($bad !== []) {
            $p[] = __('Free tools that did not answer the nightly check: :list', ['list' => implode(', ', $bad)]);
        }

        return $p;
    }
}
