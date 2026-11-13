<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\Client;
use App\Models\MarketDate;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * PS-21 / XS-19 / XS-20 (R5, D-307): paydays and school / exam periods per country, entered or confirmed by the agency
 * and always labelled "usually". Used for chart markers, the client calendar and pacing context. Nothing is shown as a
 * fact: the agency's own entry (and its source, if given) is what we show.
 */
class MarketDates
{
    /** @return array<string, string> */
    public static function kindLabels(): array
    {
        return ['payday' => __('Payday (usually)'), 'school_holiday' => __('School holiday'), 'exams' => __('Exams')];
    }

    public static function countryOf(Client $c): ?string
    {
        $m = strtoupper((string) $c->market);
        if (strlen($m) === 2 && array_key_exists($m, config('adsranger.countries', []))) {
            return $m;
        }
        $a = strtoupper((string) $c->agency?->country);

        return $a !== '' ? $a : null;
    }

    /** The payday date in a month: day 0 or a day past the month's end means the last day of the month. */
    public static function paydayIn(int $year, int $month, int $day): string
    {
        $last = Carbon::create($year, $month, 1)->daysInMonth;

        return Carbon::create($year, $month, ($day === 0 || $day > $last) ? $last : $day)->toDateString();
    }

    /**
     * @param  list<string>  $countries
     * @return list<array{date: string, end: string, kind: string, label: string, country: string}>
     */
    public static function between(Agency $a, array $countries, string $from, string $to): array
    {
        $countries = array_values(array_filter(array_unique($countries)));
        if (! $countries) {
            return [];
        }
        $rows = MarketDate::where('agency_id', $a->id)->whereIn('country', $countries)->get();
        $labels = static::kindLabels();
        $out = [];
        foreach ($rows->where('kind', 'payday') as $r) {
            $months = [];
            foreach (CarbonPeriod::create(Carbon::parse($from)->startOfMonth(), '1 month', Carbon::parse($to)->startOfMonth()) as $m) {
                $months[] = [$m->year, $m->month];
            }
            foreach ($months as [$y, $mo]) {
                $d = static::paydayIn($y, $mo, (int) $r->day_of_month);
                if ($d >= $from && $d <= $to) {
                    $out[] = ['date' => $d, 'end' => $d, 'kind' => 'payday', 'label' => $r->label ?: $labels['payday'], 'country' => $r->country];
                }
            }
        }
        foreach ($rows->whereIn('kind', ['school_holiday', 'exams']) as $r) {
            $s = $r->starts_on?->toDateString();
            $e = ($r->ends_on ?? $r->starts_on)?->toDateString();
            if ($s && $e >= $from && $s <= $to) {
                $out[] = ['date' => $s, 'end' => $e, 'kind' => $r->kind, 'label' => $r->label ?: $labels[$r->kind], 'country' => $r->country];
            }
        }
        usort($out, fn ($x, $y) => $x['date'] <=> $y['date']);

        return $out;
    }

    /** @return list<array{date: string, end: string, kind: string, label: string, country: string}> */
    public static function forClient(Client $c, string $from, string $to): array
    {
        $country = static::countryOf($c);

        return $country && $c->agency ? static::between($c->agency, [$country], $from, $to) : [];
    }
}
