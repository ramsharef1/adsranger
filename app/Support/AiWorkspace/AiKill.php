<?php

namespace App\Support\AiWorkspace;

use App\Models\PlatformSetting;

/** FS-49: one switch per AI tool in /admin, to stop it for everyone at once (bad output, provider trouble). */
class AiKill
{
    public const KEY = 'ai.kill';

    public const TOOLS = ['ai_box' => 'AI box', 'night_shift' => 'Night shift', 'connector' => 'AI connector'];

    public static function off(string $tool): bool
    {
        return (bool) (((array) PlatformSetting::get(self::KEY, []))[$tool] ?? false);
    }

    public static function set(string $tool, bool $off): void
    {
        $all = (array) PlatformSetting::get(self::KEY, []);
        $all[$tool] = $off;
        PlatformSetting::put(self::KEY, $all);
    }
}
