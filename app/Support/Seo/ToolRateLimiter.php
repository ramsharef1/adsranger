<?php

namespace App\Support\Seo;

use Illuminate\Support\Facades\Cache;

class ToolRateLimiter
{
    public const DEFAULT_HOURLY_LIMIT = 10;
    public const DEFAULT_DAILY_LIMIT = 5;

    public static function visitorHash(string $ip, string $tool): string
    {
        $salt = (string) config("app.key", "adsranger-seo-tools");
        $day = now("Asia/Amman")->format("Y-m-d");
        return hash_hmac("sha256", $tool."|.".$ip."|.".$day, $salt);
    }

    public static function attempt(string $tool, string $ip, int $dailyLimit = self::DEFAULT_DAILY_LIMIT, int $hourlyLimit = self::DEFAULT_HOURLY_LIMIT): array
    {
        $hash = self::visitorHash($ip, $tool);
        $hourKey = "seo_tool_rate:hour:{$tool}:{$hash}:".now("Asia/Amman")->format("YmdH");
        $dayKey = "seo_tool_rate:day:{$tool}:{$hash}:".now("Asia/Amman")->format("Ymd");
        $hourUsed = (int) Cache::get($hourKey, 0);
        $dayUsed = (int) Cache::get($dayKey, 0);
        if ($hourUsed >= $hourlyLimit) {
            return ["allowed" => false, "reason" => "hourly_limit_reached", "remaining_daily" => max(0, $dailyLimit - $dayUsed), "remaining_hourly" => 0];
        }
        if ($dayUsed >= $dailyLimit) {
            return ["allowed" => false, "reason" => "daily_limit_reached", "remaining_daily" => 0, "remaining_hourly" => max(0, $hourlyLimit - $hourUsed)];
        }
        Cache::put($hourKey, $hourUsed + 1, now()->addHours(2));
        Cache::put($dayKey, $dayUsed + 1, now()->addHours(26));
        self::recordToolUse($tool);
        return ["allowed" => true, "reason" => null, "remaining_daily" => max(0, $dailyLimit - ($dayUsed + 1)), "remaining_hourly" => max(0, $hourlyLimit - ($hourUsed + 1))];
    }

    public static function recordToolUse(string $tool): int
    {
        $day = now("Asia/Amman")->format("Y-m-d");
        $key = "seo_tool_use:{$day}:{$tool}";
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addDays(35));
        return $count;
    }

    public static function toolUseCount(string $tool, ?string $date = null): int
    {
        $day = $date ?: now("Asia/Amman")->format("Y-m-d");
        return (int) Cache::get("seo_tool_use:{$day}:{$tool}", 0);
    }
}
