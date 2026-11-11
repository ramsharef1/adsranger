<?php

namespace App\Support\Seo;

/**
 * #300 Robots.txt, XML Sitemap & Canonical conflict inspector via SafeFetcher.
 */
class RobotsSitemapChecker
{
    /**
     * @return array{
     *   ok: bool,
     *   error?: string,
     *   url?: string,
     *   page_status?: ?int,
     *   canonical?: ?string,
     *   robots_status?: ?int,
     *   blocked_by_robots?: bool,
     *   sitemaps?: list<array{url: string, status: ?int, valid_xml: bool}>,
     *   issues?: list<string>
     * }
     */
    public static function check(string $url): array
    {
        $p = parse_url($url);
        $scheme = strtolower($p['scheme'] ?? '');
        $host = strtolower($p['host'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return ['ok' => false, 'error' => 'blocked'];
        }
        $origin = $scheme.'://'.$host.(isset($p['port']) ? ':'.$p['port'] : '');
        $path = ($p['path'] ?? '/') ?: '/';

        $robots = SafeFetcher::fetchResource($origin.'/robots.txt', ['plain', 'text', 'html']);
        if ($robots['error'] === 'blocked') {
            return ['ok' => false, 'error' => 'blocked'];
        }

        $page = SafeFetcher::fetch($url);
        if ($page['error'] === 'blocked') {
            return ['ok' => false, 'error' => 'blocked'];
        }

        $issues = [];
        $declaredSitemaps = [];
        $disallows = [];

        if ($robots['status'] === 200) {
            $inWildcard = false;
            foreach (preg_split('/\R/', $robots['body']) ?: [] as $raw) {
                $line = trim((string) preg_replace('/#.*$/', '', $raw));
                if ($line === '' || ! str_contains($line, ':')) {
                    if ($line === '') {
                        $inWildcard = false;
                    }

                    continue;
                }
                [$k, $v] = array_map('trim', explode(':', $line, 2));
                $kl = strtolower($k);
                if ($kl === 'sitemap' && preg_match('#^https?://#i', $v)) {
                    $declaredSitemaps[] = $v;
                } elseif ($kl === 'user-agent') {
                    $inWildcard = ($v === '*');
                } elseif ($kl === 'disallow' && $inWildcard && $v !== '') {
                    $disallows[] = $v;
                }
            }
        } else {
            $issues[] = 'missing_robots';
        }

        $blockedByRobots = false;
        foreach ($disallows as $rule) {
            if ($rule === '/' || str_starts_with($path, $rule)) {
                $blockedByRobots = true;
                break;
            }
        }
        if ($blockedByRobots) {
            $issues[] = 'url_disallowed';
        }

        $declaredSitemaps = array_slice(array_values(array_unique($declaredSitemaps)), 0, 2);
        if ($declaredSitemaps === []) {
            $issues[] = 'no_sitemap_in_robots';
        }

        $checkedSitemaps = [];
        foreach ($declaredSitemaps as $smUrl) {
            $sm = SafeFetcher::fetchResource($smUrl, ['xml', 'text', 'plain', 'html']);
            $validXml = $sm['status'] === 200 && (str_contains($sm['body'], '<urlset') || str_contains($sm['body'], '<sitemapindex'));
            if ($sm['status'] !== 200 || ! $validXml) {
                $issues[] = 'sitemap_error';
            }
            $checkedSitemaps[] = [
                'url' => $smUrl,
                'status' => $sm['status'],
                'valid_xml' => $validXml,
            ];
        }

        $canonical = null;
        if ($page['status'] === 200 && $page['html'] !== '') {
            if (preg_match('/<link\b[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']+)["\']/i', $page['html'], $m)
                || preg_match('/<link\b[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']canonical["\']/i', $page['html'], $m)) {
                $canonical = trim($m[1]);
            }
            if (! $canonical) {
                $issues[] = 'missing_canonical';
            } elseif (rtrim($canonical, '/') !== rtrim($page['url'], '/')) {
                $issues[] = 'canonical_points_elsewhere';
            }
        } elseif ($page['status'] !== 200) {
            $issues[] = 'page_not_200';
        }

        return [
            'ok' => true,
            'url' => $page['url'] ?: $url,
            'page_status' => $page['status'],
            'canonical' => $canonical,
            'robots_status' => $robots['status'],
            'blocked_by_robots' => $blockedByRobots,
            'sitemaps' => $checkedSitemaps,
            'issues' => array_values(array_unique($issues)),
        ];
    }
}
