<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * XS-21 (R5): a campaign name from parts, with an optional Hijri month token (RMD-1448 for Ramadan 1448) so seasonal
 * campaigns sort and filter by the Islamic month. Latin, upper case, no spaces, so it is safe in UTMs and every network.
 */
class CampaignName
{
    public const HIJRI = [1 => 'MHR', 2 => 'SFR', 3 => 'RB1', 4 => 'RB2', 5 => 'JM1', 6 => 'JM2', 7 => 'RJB', 8 => 'SHB', 9 => 'RMD', 10 => 'SHW', 11 => 'DQD', 12 => 'DHJ'];

    public const OBJECTIVES = ['LEADS', 'SALES', 'TRAFFIC', 'AWARE', 'MSG', 'APP'];

    public static function hijriToken(string $date): string
    {
        [$y, $m] = MenaCalendar::hijriYearMonth($date);

        return self::HIJRI[$m].'-'.$y;
    }

    public static function part(string $text): string
    {
        $t = strtoupper(Str::ascii(trim($text)));

        return trim((string) preg_replace('/[^A-Z0-9]+/', '-', $t), '-');
    }

    /** @param  array{client?: string, objective?: string, market?: string, extra?: string, date?: string, gregorian?: bool, hijri?: bool, sep?: string}  $o */
    public static function build(array $o): string
    {
        $sep = in_array($o['sep'] ?? '_', ['_', '|', '-'], true) ? ($o['sep'] ?? '_') : '_';
        $date = $o['date'] ?? now()->toDateString();
        $parts = [static::part((string) ($o['client'] ?? '')), static::part((string) ($o['objective'] ?? '')), static::part((string) ($o['market'] ?? ''))];
        if (! empty($o['gregorian'])) {
            $parts[] = date('Ym', strtotime($date));
        }
        if (! empty($o['hijri'])) {
            $parts[] = static::hijriToken($date);
        }
        $parts[] = static::part((string) ($o['extra'] ?? ''));

        return implode($sep, array_values(array_filter($parts, fn ($p) => $p !== '')));
    }
}
