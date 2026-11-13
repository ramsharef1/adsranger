<?php

namespace App\Support\Ops;

use App\Models\PlatformSetting;
use Illuminate\Support\Carbon;

/**
 * EG-03: the scheduler stamps a heartbeat every 5 minutes. /up/heartbeat answers 503 when the stamp is stale, so a watcher
 * outside the server (the hourly scheduled task) notices when cron itself stops: the health check can't, it runs on cron.
 */
class Heartbeat
{
    public const KEY = 'ops.heartbeat';

    public static function beat(): void
    {
        PlatformSetting::put(self::KEY, now()->toIso8601String());
    }

    public static function last(): ?Carbon
    {
        $v = PlatformSetting::get(self::KEY);

        return $v ? Carbon::parse($v) : null;
    }

    public static function fresh(): bool
    {
        $last = self::last();

        return $last !== null && $last->gt(now()->subMinutes((int) config('ops.heartbeat_stale_minutes', 15)));
    }

    /** @return array{ok: bool, last: ?string, age_minutes: ?int} */
    public static function status(): array
    {
        $last = self::last();

        return ['ok' => self::fresh(), 'last' => $last?->toIso8601String(), 'age_minutes' => $last ? (int) $last->diffInMinutes(now()) : null];
    }
}
