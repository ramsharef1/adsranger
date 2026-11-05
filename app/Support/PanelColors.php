<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/** Panel colour palettes (EX-35, EX-54, UX-6). */
class PanelColors
{
    /** Teal shifted one step so filled buttons (shade 600) are #0F766E with white text in light and dark. */
    public static function primary(): array
    {
        $t = Color::Teal;

        return [50 => $t[50], 100 => $t[100], 200 => $t[200], 300 => $t[300], 400 => $t[500], 500 => $t[600], 600 => $t[700], 700 => $t[800], 800 => $t[900], 900 => $t[950], 950 => $t[950]];
    }
}
