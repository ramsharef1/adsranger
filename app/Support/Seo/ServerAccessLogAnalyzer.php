<?php

namespace App\Support\Seo;

use App\Models\Client;
use App\Models\ClientSearchConsoleRow;
use App\Models\SeoAudit;
use App\Models\SeoAuditPage;
use App\Models\SeoLogAnalysis;
use RuntimeException;

/**
 * E2-069: Memory-safe streamed access log parser (fgets / gzgets) for .log and .gz files.
 * Categorizes search & AI crawlers (Googlebot, GPTBot, ClaudeBot, PerplexityBot, Bingbot),
 * 3xx/4xx/5xx crawl waste, and 200 OK bot-crawled orphan candidates absent from known site URLs.
 */
class ServerAccessLogAnalyzer
{
    public const BOT_PATTERNS = [
        'Googlebot' => '/googlebot/i',
        'GPTBot' => '/gptbot|oai-searchbot|chatgpt-user/i',
        'ClaudeBot' => '/claudebot|anthropic-ai|claude-web/i',
        'PerplexityBot' => '/perplexitybot/i',
        'Bingbot' => '/bingbot/i',
    ];

    public const ASSET_EXT_PATTERN = '/\.(?:css|js|mjs|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|eot|map|xml|txt|json)$/i';

    public const TRAP_QUERY_PATTERN = '/(?:^|[?&])(?:sort|filter|facet|session|sid|phpsessid|utm_[a-z]+|fbclid|gclid)=/i';

    /**
     * @param  list<string>|null  $knownSiteUrls
     */
    public static function analyzeFile(
        Client $client,
        string $filePath,
        ?string $originalFilename = null,
        ?array $knownSiteUrls = null,
    ): SeoLogAnalysis {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('Log file is not readable.');
        }

        $filename = mb_substr(basename($originalFilename ?: $filePath), 0, 255);
        $isGzip = self::isGzipFile($filePath, $filename);
        $knownPaths = self::buildKnownPathSet($client, $knownSiteUrls);

        if ($isGzip) {
            $gz = @gzopen($filePath, 'rb');
            if ($gz === false) {
                throw new RuntimeException('Unable to open gzipped log file.');
            }
            try {
                return self::consumeLines(
                    $client,
                    $filename,
                    function () use ($gz): string|false {
                        return gzgets($gz, 16384);
                    },
                    $knownPaths
                );
            } finally {
                gzclose($gz);
            }
        }

        $fp = @fopen($filePath, 'rb');
        if ($fp === false) {
            throw new RuntimeException('Unable to open log file.');
        }
        try {
            return self::consumeLines(
                $client,
                $filename,
                function () use ($fp): string|false {
                    return fgets($fp, 16384);
                },
                $knownPaths
            );
        } finally {
            fclose($fp);
        }
    }

    /**
     * Stream-analyzes log lines from an in-memory string via php://temp without duplicating parser logic.
     *
     * @param  list<string>|null  $knownSiteUrls
     */
    public static function analyzeContent(
        Client $client,
        string $rawContent,
        string $filename = 'access.log',
        ?array $knownSiteUrls = null,
    ): SeoLogAnalysis {
        $fp = fopen('php://temp', 'r+b');
        if ($fp === false) {
            throw new RuntimeException('Unable to open temp stream.');
        }
        fwrite($fp, $rawContent);
        rewind($fp);

        try {
            return self::consumeLines(
                $client,
                mb_substr($filename, 0, 255),
                function () use ($fp): string|false {
                    return fgets($fp, 16384);
                },
                self::buildKnownPathSet($client, $knownSiteUrls)
            );
        } finally {
            fclose($fp);
        }
    }

    /**
     * @param  callable(): (string|false)  $readLine
     * @param  array<string, true>  $knownPaths
     */
    private static function consumeLines(
        Client $client,
        string $filename,
        callable $readLine,
        array $knownPaths,
    ): SeoLogAnalysis {
        $totalLines = 0;
        $botHits = [
            'Googlebot' => 0,
            'GPTBot' => 0,
            'ClaudeBot' => 0,
            'PerplexityBot' => 0,
            'Bingbot' => 0,
            'OtherBot' => 0,
            'Human' => 0,
        ];
        $statusBreakdown = [
            '2xx' => 0,
            '3xx' => 0,
            '4xx' => 0,
            '5xx' => 0,
        ];
        $waste = [];
        $orphans = [];

        while (($line = $readLine()) !== false) {
            $parsed = self::parseLine($line);
            if ($parsed === null) {
                continue;
            }
            $totalLines++;

            $status = $parsed['status'];
            $bucket = match (true) {
                $status >= 200 && $status < 300 => '2xx',
                $status >= 300 && $status < 400 => '3xx',
                $status >= 400 && $status < 500 => '4xx',
                $status >= 500 && $status < 600 => '5xx',
                default => null,
            };
            if ($bucket !== null) {
                $statusBreakdown[$bucket]++;
            }

            $actor = self::classifyUserAgent($parsed['ua']);
            $botHits[$actor] = ($botHits[$actor] ?? 0) + 1;

            if ($actor === 'Human') {
                continue;
            }

            $target = $parsed['target'];
            $cleanPath = self::normalisePath($target);
            $queryPart = (string) parse_url($target, PHP_URL_QUERY);
            $isTrap = $queryPart !== '' && (bool) preg_match(self::TRAP_QUERY_PATTERN, '?'.$queryPart);

            if ($status >= 300 || $isTrap) {
                $wKey = mb_substr($target, 0, 300);
                $w = $waste[$wKey] ?? ['path' => $wKey, 'hits' => 0, 'statuses' => [], 'bots' => []];
                $w['hits']++;
                $w['statuses'][(string) $status] = ($w['statuses'][(string) $status] ?? 0) + 1;
                $w['bots'][$actor] = true;
                if (count($waste) < 1000 || isset($waste[$wKey])) {
                    $waste[$wKey] = $w;
                }
            }

            if (
                $status === 200
                && ! $isTrap
                && ! preg_match(self::ASSET_EXT_PATTERN, $cleanPath)
                && ! isset($knownPaths[$cleanPath])
            ) {
                $o = $orphans[$cleanPath] ?? ['path' => $cleanPath, 'bot_hits' => 0, 'bots' => []];
                $o['bot_hits']++;
                $o['bots'][$actor] = true;
                if (count($orphans) < 1000 || isset($orphans[$cleanPath])) {
                    $orphans[$cleanPath] = $o;
                }
            }
        }

        $wasteList = array_values(array_map(function (array $row): array {
            $row['bots'] = array_keys($row['bots']);

            return $row;
        }, $waste));
        usort($wasteList, fn (array $a, array $b): int => $b['hits'] <=> $a['hits']);

        $orphanList = array_values(array_map(function (array $row): array {
            $row['bots'] = array_keys($row['bots']);

            return $row;
        }, $orphans));
        usort($orphanList, fn (array $a, array $b): int => $b['bot_hits'] <=> $a['bot_hits']);

        return SeoLogAnalysis::create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'filename' => $filename,
            'total_lines' => $totalLines,
            'bot_hits' => $botHits,
            'status_breakdown' => $statusBreakdown,
            'wasted_budget_paths' => array_slice($wasteList, 0, 25),
            'orphan_candidates' => array_slice($orphanList, 0, 25),
            'analyzed_at' => now(),
        ]);
    }

    /**
     * @return array{target: string, status: int, ua: string}|null
     */
    public static function parseLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (! preg_match('/"(?:GET|POST|HEAD|OPTIONS|PUT|DELETE)\s+(\S+)\s+HTTP\/[0-9.]+"\s+(\d{3})\s+\S+(?:\s+"[^"]*"\s+"([^"]*)")?/i', $line, $m)) {
            return null;
        }

        return [
            'target' => SafeFetcher::toUtf8($m[1]),
            'status' => (int) $m[2],
            'ua' => SafeFetcher::toUtf8($m[3] ?? ''),
        ];
    }

    public static function classifyUserAgent(string $ua): string
    {
        foreach (self::BOT_PATTERNS as $name => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $name;
            }
        }

        if (preg_match('/bot|crawler|spider|slurp|bytespider|applebot|duckduckbot|yandex/i', $ua)) {
            return 'OtherBot';
        }

        return 'Human';
    }

    /**
     * @param  list<string>|null  $knownSiteUrls
     * @return array<string, true>
     */
    private static function buildKnownPathSet(Client $client, ?array $knownSiteUrls): array
    {
        $set = ['/' => true];

        if ($knownSiteUrls !== null) {
            foreach ($knownSiteUrls as $u) {
                $set[self::normalisePath($u)] = true;
            }

            return $set;
        }

        $auditIds = SeoAudit::where('agency_id', $client->agency_id)
            ->whereHas('site', fn ($q) => $q->where('client_id', $client->id))
            ->pluck('id');

        if ($auditIds->isNotEmpty()) {
            foreach (SeoAuditPage::whereIn('seo_audit_id', $auditIds)->pluck('url') as $url) {
                $set[self::normalisePath((string) $url)] = true;
            }
        }

        foreach (ClientSearchConsoleRow::where('agency_id', $client->agency_id)->where('client_id', $client->id)->distinct()->pluck('page_url') as $url) {
            $set[self::normalisePath((string) $url)] = true;
        }

        return $set;
    }

    private static function normalisePath(string $urlOrPath): string
    {
        $path = (string) parse_url($urlOrPath, PHP_URL_PATH);
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/'.trim($path, '/');
    }

    private static function isGzipFile(string $filePath, string $filename): bool
    {
        if (str_ends_with(strtolower($filename), '.gz')) {
            return true;
        }
        $fp = @fopen($filePath, 'rb');
        if ($fp === false) {
            return false;
        }
        $head = (string) fread($fp, 2);
        fclose($fp);

        return $head === "\x1f\x8b";
    }
}
