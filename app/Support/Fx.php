<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Currency conversion with daily history (rates stored against USD). */
class Fx
{
    protected static array $memo = [];

    /** USD → $quote on $date (nearest earlier stored rate, else nearest later). */
    public static function usdTo(string $quote, string $date): ?float
    {
        $quote = strtoupper($quote);
        if ($quote === 'USD') {
            return 1.0;
        }
        $k = $quote.'|'.$date;
        if (array_key_exists($k, static::$memo)) {
            return static::$memo[$k];
        }
        $r = DB::table('fx_rates')->where('base', 'USD')->where('quote', $quote)->where('date', '<=', $date)->orderByDesc('date')->value('rate')
            ?? DB::table('fx_rates')->where('base', 'USD')->where('quote', $quote)->orderBy('date')->value('rate');

        return static::$memo[$k] = $r ? (float) $r : null;
    }

    public static function rate(string $from, string $to, string $date): float
    {
        $from = strtoupper($from ?: 'USD');
        $to = strtoupper($to ?: 'USD');
        if ($from === $to) {
            return 1.0;
        }
        $a = static::usdTo($from, $date);
        $b = static::usdTo($to, $date);

        return ($a && $b) ? $b / $a : 1.0;
    }

    public static function convert(float $amount, ?string $from, ?string $to, string $date): float
    {
        return $amount * static::rate((string) $from, (string) $to, $date);
    }

    /** Daily pull (open.er-api.com covers JOD, SAR, AED, EGP and the rest). Returns number of rates saved. */
    public static function pull(): int
    {
        $res = Http::timeout(20)->get('https://open.er-api.com/v6/latest/USD');
        $rates = $res->json('rates');
        if (! $res->successful() || ! is_array($rates)) {
            return 0;
        }
        $date = now()->toDateString();
        $rows = [];
        foreach ($rates as $q => $r) {
            if (is_numeric($r) && strlen($q) <= 8) {
                $rows[] = ['date' => $date, 'base' => 'USD', 'quote' => strtoupper($q), 'rate' => $r];
            }
        }
        DB::table('fx_rates')->upsert($rows, ['date', 'base', 'quote'], ['rate']);
        static::$memo = [];

        return count($rows);
    }
}
