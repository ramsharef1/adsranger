<?php

namespace App\Support;

use App\Models\Agency;
use Illuminate\Support\Facades\Storage;

/**
 * #176 (FR-019, D-105 / D-131): the agency's own logo on client-facing surfaces, all tiers.
 * Files live on the private local disk under agency-logos/ with a random 40-character name,
 * and are served by AgencyLogoController (the random name is the only key, like /r tokens).
 * Rules (experience spec B): SVG or PNG, at most 200 KB, PNG at least 400 px wide; never recoloured or cropped.
 */
class AgencyLogo
{
    public const DIRECTORY = 'agency-logos';

    public const MAX_KB = 200;

    public const MIN_WIDTH = 400;

    /** Path of the stored file on the local disk, or null when the agency has no (readable) logo. */
    public static function storedPath(?Agency $agency): ?string
    {
        $path = $agency?->logo_path;
        if (! is_string($path) || ! preg_match('#^'.self::DIRECTORY.'/[A-Za-z0-9]{40}\.(png|svg)$#', $path)) {
            return null;
        }

        return Storage::disk('local')->exists($path) ? $path : null;
    }

    public static function has(?Agency $agency): bool
    {
        return self::storedPath($agency) !== null;
    }

    public static function isSvg(?Agency $agency): bool
    {
        return str_ends_with((string) self::storedPath($agency), '.svg');
    }

    /** Public URL for web pages and emails. */
    public static function url(?Agency $agency): ?string
    {
        $path = self::storedPath($agency);

        return $path ? route('agency.logo', ['file' => basename($path)]) : null;
    }

    /** Absolute file path for the PDF renderer. */
    public static function absolutePath(?Agency $agency): ?string
    {
        $path = self::storedPath($agency);

        return $path ? Storage::disk('local')->path($path) : null;
    }

    /** Email clients don't show SVG; emails fall back to the agency name for SVG logos. */
    public static function emailUrl(?Agency $agency): ?string
    {
        return self::isSvg($agency) ? null : self::url($agency);
    }
}
