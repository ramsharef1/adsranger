<?php

namespace App\Support\Reach;

use App\Support\TextClean;

/** Coupon codes as typed by people and networks: no invisible marks, Western digits, no spaces. */
class TextCleanCode
{
    public static function code(string $code): string
    {
        return mb_substr((string) preg_replace('/\s+/u', '', (string) TextClean::western(TextClean::invisible($code))), 0, 40);
    }
}
