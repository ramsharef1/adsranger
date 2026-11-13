<?php

namespace App\Support\AiWorkspace;

use App\Support\TextClean;

/**
 * FS-01 (D-299): personal data out of everything sent to the agency's AI — phone numbers, emails and ID-like numbers,
 * found by pattern (Western or Eastern digits, +966 / 05… forms). Names are not guessed. Counts go to the run log.
 */
class Redactor
{
    public const EMAIL = '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u';

    /** International (+ / 00 + country code) or local starting with 0 (05… / 07… / 01…), groups split by spaces, dots or dashes. */
    public const PHONE = '/(?<![\d.,])(?:(?:\+|00)\d[\d\s.\-]{7,16}\d|0\d[\d\s.\-]{6,13}\d)(?!\d|[.,]\d)/u';

    /** 10+ digit runs left after phones, i.e. not starting with 0 or + (national IDs, IBAN-like and card-like numbers). */
    public const LONG_ID = '/(?<![\d.,])\d{10,}(?!\d|[.,]\d)/u';

    /** @return array{text: string, counts: array{phone: int, email: int, id: int}} */
    public static function clean(string $text): array
    {
        $counts = ['phone' => 0, 'email' => 0, 'id' => 0];
        // Eastern digits become Western first so the patterns see them; the text sent is the Western form.
        $text = (string) TextClean::western($text);
        $text = preg_replace_callback(self::EMAIL, function () use (&$counts) {
            $counts['email']++;

            return '[email hidden]';
        }, $text);
        $text = preg_replace_callback(self::PHONE, function (array $m) use (&$counts) {
            // A plain amount or a year is not a phone: need 9+ digits in the match.
            if (strlen(preg_replace('/\D/', '', $m[0])) < 9) {
                return $m[0];
            }
            $counts['phone']++;

            return '[phone hidden]';
        }, $text);
        $text = preg_replace_callback(self::LONG_ID, function () use (&$counts) {
            $counts['id']++;

            return '[number hidden]';
        }, $text);

        return ['text' => $text, 'counts' => $counts];
    }

    public static function total(array $counts): int
    {
        return array_sum($counts);
    }
}
