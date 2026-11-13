<?php

namespace App\Support\Seo;

/**
 * FR-REACH R-3: free "AI Bot Traffic & Unmonetized Revenue Checker". Reads a site's robots.txt (SafeFetcher, SSRF
 * pinned), works out for each AI crawler whether it may read the given page (its own group, else `*`; longest match,
 * `*` and `$` wildcards, Allow wins a tie), and checks whether the site gives those agents anything to monetise:
 * a /llms.txt file and schema.org Offer markup on the page. No traffic or money figure is guessed: the tool reports
 * exposure only, and the visitor may add their own monthly AI-bot hits from their logs.
 */
class AiCrawlerChecker
{
    /** Crawler user-agent token → who runs it and what for (shown on the page). */
    public const BOTS = [
        'GPTBot' => ['OpenAI', 'training'],
        'OAI-SearchBot' => ['OpenAI', 'search'],
        'ChatGPT-User' => ['OpenAI', 'user'],
        'ClaudeBot' => ['Anthropic', 'training'],
        'Claude-User' => ['Anthropic', 'user'],
        'PerplexityBot' => ['Perplexity', 'search'],
        'Google-Extended' => ['Google', 'training'],
        'Applebot-Extended' => ['Apple', 'training'],
        'CCBot' => ['Common Crawl', 'training'],
        'meta-externalagent' => ['Meta', 'training'],
        'Bytespider' => ['ByteDance', 'training'],
    ];

    /**
     * @return array{ok: bool, error?: string, url?: string, robots_status?: ?int, bots?: list<array{bot: string, owner: string, purpose: string, status: string, rule: string}>,
     *   allowed?: int, blocked?: int, has_llms_txt?: bool, has_offer_schema?: bool, findings?: list<string>}
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
        $groups = $robots['status'] === 200 ? static::parse((string) $robots['body']) : [];

        $bots = [];
        foreach (self::BOTS as $bot => [$owner, $purpose]) {
            [$allowed, $rule] = static::allowed($groups, $bot, $path);
            $bots[] = ['bot' => $bot, 'owner' => $owner, 'purpose' => $purpose, 'status' => $allowed ? 'allowed' : 'blocked', 'rule' => $rule];
        }
        $allowedCount = count(array_filter($bots, fn ($b) => $b['status'] === 'allowed'));

        $llms = SafeFetcher::fetchResource($origin.'/llms.txt', ['plain', 'text', 'markdown']);
        $hasLlms = $llms['status'] === 200 && trim((string) $llms['body']) !== '' && ! str_contains(strtolower(substr((string) $llms['body'], 0, 200)), '<html');
        $page = SafeFetcher::fetch($url);
        $hasOffer = (bool) preg_match('/"@type"\s*:\s*(\[[^\]]*)?"(Offer|AggregateOffer)"/i', (string) ($page['html'] ?? ''));

        $findings = [];
        if ($robots['status'] !== 200) {
            $findings[] = 'no_robots';
        }
        if ($allowedCount > 0 && ! $hasOffer && ! $hasLlms) {
            $findings[] = 'read_not_monetised';
        } elseif ($allowedCount > 0 && ! $hasOffer) {
            $findings[] = 'no_offer_schema';
        }
        if ($allowedCount > 0 && ! $hasLlms) {
            $findings[] = 'no_llms_txt';
        }
        if ($allowedCount === 0) {
            $findings[] = 'all_blocked';
        }

        return [
            'ok' => true, 'url' => $url, 'robots_status' => $robots['status'], 'bots' => $bots,
            'allowed' => $allowedCount, 'blocked' => count($bots) - $allowedCount,
            'has_llms_txt' => $hasLlms, 'has_offer_schema' => $hasOffer, 'findings' => $findings,
        ];
    }

    /** @return list<array{agents: list<string>, rules: list<array{0: string, 1: string}>}> */
    public static function parse(string $robots): array
    {
        $groups = [];
        $current = null;
        $lastWasAgent = false;
        foreach (preg_split('/\R/', $robots) ?: [] as $raw) {
            $line = trim((string) preg_replace('/#.*$/', '', $raw));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            $k = strtolower($k);
            if ($k === 'user-agent') {
                if (! $lastWasAgent || $current === null) {
                    $groups[] = ['agents' => [], 'rules' => []];
                    $current = array_key_last($groups);
                }
                $groups[$current]['agents'][] = strtolower($v);
                $lastWasAgent = true;
            } elseif (in_array($k, ['allow', 'disallow'], true) && $current !== null) {
                $groups[$current]['rules'][] = [$k, $v];
                $lastWasAgent = false;
            } else {
                $lastWasAgent = false;
            }
        }

        return $groups;
    }

    /** @return array{0: bool, 1: string} allowed?, which group decided: own · wildcard · none */
    public static function allowed(array $groups, string $bot, string $path): array
    {
        $own = array_values(array_filter($groups, fn ($g) => in_array(strtolower($bot), $g['agents'], true)));
        $which = $own !== [] ? 'own' : 'wildcard';
        $use = $own !== [] ? $own : array_values(array_filter($groups, fn ($g) => in_array('*', $g['agents'], true)));
        if ($use === []) {
            return [true, 'none'];
        }
        $best = ['allow', -1];
        foreach ($use as $g) {
            foreach ($g['rules'] as [$kind, $pattern]) {
                if ($pattern === '') {
                    continue; // "Disallow:" with nothing = allow all
                }
                if (static::matches($pattern, $path) && (strlen($pattern) > $best[1] || (strlen($pattern) === $best[1] && $kind === 'allow'))) {
                    $best = [$kind, strlen($pattern)];
                }
            }
        }

        return [$best[0] === 'allow', $which];
    }

    public static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $regex = '#^'.str_replace('\*', '.*', preg_quote(rtrim($pattern, '$'), '#')).($anchored ? '$' : '').'#';

        return (bool) preg_match($regex, $path);
    }
}
