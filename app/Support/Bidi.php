<?php

namespace App\Support;

/** Bidi helpers for Latin values inside Arabic copy (EX-13, UX-56). */
class Bidi
{
    /** Plain-text isolate (FSI … PDI) for notifications and other text where <bdi> can't be used. */
    public static function isolate(?string $s): string
    {
        return "\u{2068}".(string) $s."\u{2069}";
    }

    /** Signed percentage for RTL documents: a true minus sign (U+2212) and an explicit plus, e.g. "−4%", "+12%". */
    public static function signedPct(int|float $d): string
    {
        return ($d < 0 ? "\u{2212}" : '+').abs($d).'%';
    }
}
