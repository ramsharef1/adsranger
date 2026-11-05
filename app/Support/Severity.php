<?php

namespace App\Support;

/**
 * D-88 alert levels: Critical / Warning / Info (AR حرج / تحذير / للعلم), red / amber / sky, always icon + text (EX-52).
 * Replaces high / medium / low; legacy values are still read (normalize) so old payloads never break.
 */
class Severity
{
    public const LEVELS = ['critical', 'warning', 'info'];

    public const LEGACY = ['high' => 'critical', 'medium' => 'warning', 'low' => 'info'];

    public static function normalize(?string $s): string
    {
        $s = strtolower((string) $s);

        return in_array($s, self::LEVELS, true) ? $s : (self::LEGACY[$s] ?? 'info');
    }

    /** Translated label ("Critical" → «حرج»). */
    public static function label(?string $s): string
    {
        return __(['critical' => 'Critical', 'warning' => 'Warning', 'info' => 'Info'][static::normalize($s)]);
    }

    /** Filament colour name. */
    public static function color(?string $s): string
    {
        return ['critical' => 'danger', 'warning' => 'warning', 'info' => 'info'][static::normalize($s)];
    }

    public static function icon(?string $s): string
    {
        return ['critical' => 'heroicon-m-exclamation-circle', 'warning' => 'heroicon-m-exclamation-triangle', 'info' => 'heroicon-m-information-circle'][static::normalize($s)];
    }

    /** Hex for emails (no theme variables there). */
    public static function hex(?string $s): string
    {
        return ['critical' => '#dc2626', 'warning' => '#d97706', 'info' => '#0284c7'][static::normalize($s)];
    }

    public static function rank(?string $s): int
    {
        return ['critical' => 0, 'warning' => 1, 'info' => 2][static::normalize($s)];
    }

    /** @return array<string, string> level => translated label, for selects and filters */
    public static function options(): array
    {
        return collect(self::LEVELS)->mapWithKeys(fn ($l) => [$l => static::label($l)])->all();
    }
}
