<?php

namespace App\Support;

/** PS-26 / PS-24 (D-290): invisible marks out of names and keys; Eastern digits and Arabic separators to Western on input. */
class TextClean
{
    /** RLM, LRM, ALM, zero-width marks, BOM, bidi embeds / isolates and tatweel. */
    public const INVISIBLE = '/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{061C}\x{FEFF}\x{0640}]/u';

    public static function invisible(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }

        return trim(preg_replace('/\s{2,}/u', ' ', preg_replace(self::INVISIBLE, '', $s)));
    }

    public static function hasInvisible(?string $s): bool
    {
        return $s !== null && (bool) preg_match(self::INVISIBLE, $s);
    }

    public static function western(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }

        return strtr($s, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', '٬' => ',', '،' => ',',
        ]);
    }
}
