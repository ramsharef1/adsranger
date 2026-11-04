<?php

namespace App\Support;

use Carbon\Carbon;

/** Date ranges used across result screens, in the workspace time zone. */
class Periods
{
    public static function options(): array
    {
        return ['yesterday' => 'Yesterday', 'today' => 'Today', '7d' => 'Last 7 days', '14d' => 'Last 14 days', '30d' => 'Last 30 days',
            '90d' => 'Last 90 days', 'mtd' => 'This month', 'last_month' => 'Last month'];
    }

    /** @return array{0:string,1:string} */
    public static function range(string $p, string $tz = 'UTC', ?string $custom = null): array
    {
        $now = now($tz)->startOfDay();
        if ($p === 'custom' && $custom) {
            return [$custom, $custom];
        }

        return match ($p) {
            'today' => [$now->toDateString(), $now->toDateString()],
            'yesterday' => [$now->copy()->subDay()->toDateString(), $now->copy()->subDay()->toDateString()],
            '7d', '14d', '30d', '90d' => [$now->copy()->subDays((int) $p)->toDateString(), $now->copy()->subDay()->toDateString()],
            'mtd' => [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default => [$now->copy()->subDays(30)->toDateString(), $now->copy()->subDay()->toDateString()],
        };
    }

    /** The same-length range just before. */
    public static function previous(string $from, string $to): array
    {
        $f = Carbon::parse($from);
        $days = $f->diffInDays(Carbon::parse($to)) + 1;

        return [$f->copy()->subDays($days)->toDateString(), $f->copy()->subDay()->toDateString()];
    }
}
