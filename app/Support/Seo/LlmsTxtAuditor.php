<?php

namespace App\Support\Seo;

/**
 * #293 llms.txt builder & AI crawler robots.txt auditor.
 */
class LlmsTxtAuditor
{
    public const AI_BOTS = ['GPTBot', 'OAI-SearchBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended'];

    /**
     * Parse a robots.txt string and return per-bot status (`allowed`, `blocked`, or `unspecified`).
     *
     * @return array<string, string>
     */
    public static function auditRobotsText(string $robotsTxt): array
    {
        $lines = preg_split('/\R/', $robotsTxt) ?: [];
        $groups = [];
        $currentAgents = [];

        foreach ($lines as $rawLine) {
            $line = trim((string) preg_replace('/#.*$/', '', $rawLine));
            if ($line === '' || ! str_contains($line, ':')) {
                if ($line === '') {
                    $currentAgents = [];
                }

                continue;
            }
            [$field, $val] = array_map('trim', explode(':', $line, 2));
            $fLow = strtolower($field);
            if ($fLow === 'user-agent') {
                $agentKey = strtolower($val);
                $currentAgents[] = $agentKey;
                $groups[$agentKey] ??= ['allow' => [], 'disallow' => []];
            } elseif (in_array($fLow, ['allow', 'disallow'], true) && $currentAgents !== []) {
                foreach ($currentAgents as $ag) {
                    $groups[$ag][$fLow][] = $val;
                }
            }
        }

        $out = [];
        $wildcardBlocked = in_array('/', $groups['*']['disallow'] ?? [], true) && ! in_array('/', $groups['*']['allow'] ?? [], true);

        foreach (self::AI_BOTS as $bot) {
            $bKey = strtolower($bot);
            if (isset($groups[$bKey])) {
                $dis = $groups[$bKey]['disallow'];
                $all = $groups[$bKey]['allow'];
                if (in_array('/', $dis, true) && ! in_array('/', $all, true)) {
                    $out[$bot] = 'blocked';
                } else {
                    $out[$bot] = 'allowed';
                }
            } else {
                $out[$bot] = $wildcardBlocked ? 'blocked_wildcard' : 'unspecified';
            }
        }

        return $out;
    }

    /**
     * Audit a live origin's /robots.txt and /llms.txt via SafeFetcher.
     *
     * @return array{ok: bool, error?: string, origin?: string, robots_status?: ?int, llms_status?: ?int, bots?: array<string, string>}
     */
    public static function checkOrigin(string $url): array
    {
        $p = parse_url($url);
        $scheme = strtolower($p['scheme'] ?? '');
        $host = strtolower($p['host'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return ['ok' => false, 'error' => 'blocked'];
        }
        $origin = $scheme.'://'.$host.(isset($p['port']) ? ':'.$p['port'] : '');

        $robots = SafeFetcher::fetchResource($origin.'/robots.txt', ['plain', 'text', 'html']);
        if ($robots['error'] === 'blocked') {
            return ['ok' => false, 'error' => 'blocked'];
        }

        $llms = SafeFetcher::fetchResource($origin.'/llms.txt', ['plain', 'markdown', 'text', 'html']);
        $bots = $robots['status'] === 200 ? static::auditRobotsText($robots['body']) : array_fill_keys(self::AI_BOTS, 'no_robots');

        return [
            'ok' => true,
            'origin' => $origin,
            'robots_status' => $robots['status'],
            'llms_status' => $llms['status'],
            'bots' => $bots,
        ];
    }
}
