<?php

namespace App\Support\Seo\Audit;

use App\Models\SeoAudit;
use App\Models\SeoAuditPage;
use App\Models\SeoIssue;
use App\Support\Seo\SafeFetcher;
use Closure;
use Throwable;

/**
 * Runs one SeoAudit end to end (A1): robots → crawl → page rules → site rules → persist → score.
 * Score (Rami 13:41, G-4): max(0, 100 − (10 × errors + 3 × warnings) ÷ pages crawled), rounded; not a ranking claim.
 */
class SiteAuditor
{
    public function __construct(public ?Closure $sleeper = null) {}

    public function run(SeoAudit $audit, ?string $keyword = null): SeoAudit
    {
        $site = $audit->site;
        $max = (int) config('seo_audit.max_pages_per_run', 200);
        $audit->forceFill(['status' => 'running', 'started_at' => now(), 'pages_planned' => $max, 'pages_done' => 0, 'error' => null])->save();

        try {
            $crawler = new Crawler($site->url, $max, (int) config('seo_audit.request_interval_ms', 1000), $this->sleeper);
            if (! $crawler->readRobots()) {
                return $this->finish($audit, 'blocked', 'robots_disallow');
            }

            /** @var array<string, array{parsed: array, status: ?int, html_len: int, ms: int, error: ?string}> $pages */
            $pages = [];
            $status = $crawler->run(function (string $url, ?int $code, string $html, int $ms, ?string $error) use (&$pages, $audit): void {
                $parsed = PageParser::parse($html, $url);
                $pages[$url] = ['parsed' => $parsed, 'status' => $code, 'ms' => $ms, 'error' => $error];
                $audit->forceFill(['pages_done' => count($pages)])->save();
            }, fn (): bool => $audit->fresh()?->status === 'stopped');

            if ($pages === []) {
                return $this->finish($audit, 'site_down', 'no_pages');
            }
            $root = reset($pages);
            if (($root['status'] ?? null) === null || $root['status'] >= 500) {
                return $this->finish($audit, 'site_down', (string) ($root['error'] ?? $root['status']));
            }

            // Internal links not crawled (over the page cap) are checked only for status, lightly: up to 50 per audit.
            $linkStatus = [];
            $checked = 0;
            foreach ($pages as $page) {
                foreach ($page['parsed']['links'] as $link) {
                    $n = PageParser::normalise($link);
                    if (isset($pages[$n]) || isset($status[$n]) || isset($linkStatus[$n]) || ! PageParser::sameHost($n, $site->url) || $checked >= 50) {
                        continue;
                    }
                    if (! $crawler->allowed($n) || preg_match('/\.(jpe?g|png|gif|webp|svg|pdf|zip|mp4|mp3|css|js|ico|woff2?)(\?|$)/i', $n)) {
                        continue;
                    }
                    $res = SafeFetcher::fetch($n);
                    $checked++;
                    $linkStatus[$n] = $res['status'] ?? (($res['error'] ?? null) === 'blocked' ? null : 599);
                    if ($this->sleeper) {
                        ($this->sleeper)((int) config('seo_audit.request_interval_ms', 1000));
                    } else {
                        usleep((int) config('seo_audit.request_interval_ms', 1000) * 1000);
                    }
                }
            }
            $linkStatus += $status;

            $siteIssues = Rules::site(array_map(fn (array $p) => ['parsed' => $p['parsed'], 'status' => $p['status']], $pages), $linkStatus);
            $counts = ['critical' => 0, 'warning' => 0, 'info' => 0];
            foreach ($pages as $url => $page) {
                $p = $page['parsed'];
                $row = SeoAuditPage::create([
                    'seo_audit_id' => $audit->id,
                    'url' => mb_substr($url, 0, 1000),
                    'status' => $page['status'],
                    'lang' => mb_substr($p['lang'], 0, 16) ?: null,
                    'dir' => $p['dir'] ?: null,
                    'title' => $p['title'] ?: null,
                    'title_px' => $p['title_px'],
                    'h1' => $p['h1s'][0] ?? null,
                    'canonical' => $p['canonical'] ? mb_substr($p['canonical'], 0, 1000) : null,
                    'hreflang' => $p['hreflang'] ?: null,
                    'word_count' => $p['word_count'],
                    'noindex' => $p['noindex'],
                    'response_ms' => $page['ms'],
                ]);
                $issues = array_merge(Rules::page($p, $url, $page['status'], $keyword), $siteIssues[$url] ?? []);
                $seenCodes = [];
                foreach ($issues as [$code, $detail]) {
                    if (isset($seenCodes[$code.'|'.$detail])) {
                        continue;
                    }
                    $seenCodes[$code.'|'.$detail] = true;
                    $level = Catalog::level($code);
                    $counts[$level]++;
                    SeoIssue::create(['seo_audit_id' => $audit->id, 'seo_audit_page_id' => $row->id, 'code' => $code, 'level' => $level, 'detail' => mb_substr($detail, 0, 500) ?: null]);
                }
            }
            $audit->forceFill([
                'critical_count' => $counts['critical'],
                'warning_count' => $counts['warning'],
                'info_count' => $counts['info'],
                'score' => self::score($counts, count($pages)),
                'pages_done' => count($pages),
            ])->save();

            return $this->finish($audit, 'done');
        } catch (Throwable $e) {
            report($e);

            return $this->finish($audit, 'failed', mb_substr($e->getMessage(), 0, 120));
        }
    }

    /** @param  array{critical: int, warning: int, info: int}  $counts */
    public static function score(array $counts, int $pages): int
    {
        if ($pages <= 0) {
            return 0;
        }
        $w = config('seo_audit.score_weights', ['critical' => 10, 'warning' => 3, 'info' => 0]);
        $penalty = ($w['critical'] * $counts['critical'] + $w['warning'] * $counts['warning'] + $w['info'] * $counts['info']) / $pages;

        return (int) max(0, min(100, round(100 - $penalty)));
    }

    private function finish(SeoAudit $audit, string $status, ?string $error = null): SeoAudit
    {
        $audit->forceFill(['status' => $status, 'error' => $error, 'finished_at' => now()])->save();

        return $audit;
    }
}
