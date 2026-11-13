<?php

namespace App\Support\Seo;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * BZ-10 tool abuse guard for the free URL tools. Counts only, never a raw IP or URL path:
 * - a visitor (salted IP hash) that hits the daily / hourly limit AUTO_VISITOR times in a day is refused for 24 h;
 * - a target host the tools fail to read AUTO_HOST times in a day is refused for 24 h;
 * - super admins can block a host by hand (/admin › Tool guard) and lift any block.
 */
class ToolGuard
{
    public const AUTO_VISITOR = 20;

    public const AUTO_HOST = 20;

    public const BLOCK_HOURS = 24;

    public const HOSTS_KEY = 'tools.blocked_hosts';

    public static function who(Request $request): string
    {
        return hash('sha256', config('app.key').'|'.$request->ip());
    }

    public static function host(?string $url): ?string
    {
        $host = is_string($url) ? parse_url(trim($url), PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? strtolower(preg_replace('/^www\./i', '', $host)) : null;
    }

    public static function day(): string
    {
        return now('Asia/Amman')->format('Y-m-d');
    }

    /** @return list<string> */
    public static function manualHosts(): array
    {
        try {
            return array_values(array_filter((array) PlatformSetting::get(self::HOSTS_KEY, []), 'is_string'));
        } catch (\Throwable) {
            // The guard must never take a free tool down: an unreadable block list means "nothing blocked by hand".
            return [];
        }
    }

    /** @return array<string, array{kind: string, label: string, until: string}> keyed by "kind:value" */
    public static function autoBlocks(): array
    {
        return array_filter((array) Cache::get('tool_guard:auto', []), fn ($b) => isset($b['until']) && now()->lt($b['until']));
    }

    public static function refuses(string $who, ?string $host): bool
    {
        $auto = static::autoBlocks();
        if (isset($auto['visitor:'.$who])) {
            return true;
        }
        if ($host !== null && (isset($auto['host:'.$host]) || in_array($host, static::manualHosts(), true))) {
            return true;
        }

        return false;
    }

    public static function recordLimitHit(string $tool, string $who): void
    {
        $day = static::day();
        static::bump("tool_guard:limit:{$day}:{$tool}");
        if (static::bump("tool_guard:visitor:{$day}:{$who}") === self::AUTO_VISITOR) {
            static::autoBlock('visitor', $who, __('Visitor :id', ['id' => substr($who, 0, 8)]));
        }
    }

    public static function recordFailure(string $tool, ?string $host): void
    {
        $day = static::day();
        static::bump("tool_guard:fail:{$day}:{$tool}");
        if ($host !== null && static::bump("tool_guard:host:{$day}:{$host}") === self::AUTO_HOST) {
            static::autoBlock('host', $host, $host);
        }
    }

    public static function recordRefusal(string $tool): void
    {
        static::bump('tool_guard:refused:'.static::day().':'.$tool);
    }

    /** @return array{limit: int, fail: int, refused: int} */
    public static function counts(string $tool, ?string $day = null): array
    {
        $day ??= static::day();

        return [
            'limit' => (int) Cache::get("tool_guard:limit:{$day}:{$tool}", 0),
            'fail' => (int) Cache::get("tool_guard:fail:{$day}:{$tool}", 0),
            'refused' => (int) Cache::get("tool_guard:refused:{$day}:{$tool}", 0),
        ];
    }

    public static function blockHost(string $host): bool
    {
        $host = static::host('https://'.preg_replace('#^https?://#i', '', trim($host)));
        if ($host === null || ! str_contains($host, '.')) {
            return false;
        }
        $hosts = static::manualHosts();
        if (! in_array($host, $hosts, true)) {
            $hosts[] = $host;
            PlatformSetting::put(self::HOSTS_KEY, $hosts);
            AuditLog::record('tools.host_blocked', null, ['host' => $host]);
        }

        return true;
    }

    public static function lift(string $key): void
    {
        if (str_starts_with($key, 'manual:')) {
            $host = substr($key, 7);
            PlatformSetting::put(self::HOSTS_KEY, array_values(array_diff(static::manualHosts(), [$host])));
        } else {
            $auto = static::autoBlocks();
            unset($auto[$key]);
            Cache::put('tool_guard:auto', $auto, now()->addHours(self::BLOCK_HOURS + 1));
        }
        AuditLog::record('tools.block_lifted', null, ['key' => str_starts_with($key, 'visitor:') ? 'visitor' : $key]);
    }

    protected static function autoBlock(string $kind, string $value, string $label): void
    {
        $auto = static::autoBlocks();
        $auto[$kind.':'.$value] = ['kind' => $kind, 'label' => $label, 'until' => now()->addHours(self::BLOCK_HOURS)->toIso8601String()];
        Cache::put('tool_guard:auto', $auto, now()->addHours(self::BLOCK_HOURS + 1));
    }

    protected static function bump(string $key): int
    {
        $n = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $n, now()->addDays(8));

        return $n;
    }
}
