<?php

namespace App\Support;

use App\Models\Client;
use Carbon\Carbon;

/** MENA calendar with Hijri dates (#40), seasonality from a client's own history, and the Ramadan / Eid scenario planner (#96). */
class MenaCalendar
{
    public static function hasIntl(): bool
    {
        return class_exists(\IntlCalendar::class) && class_exists(\IntlDateFormatter::class);
    }

    /** Gregorian date for a Hijri (Umm al-Qura) date, or null when it can't be computed. */
    public static function fromHijri(int $y, int $m, int $d): ?string
    {
        if (! static::hasIntl()) {
            return null;
        }
        $cal = \IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-umalqura');
        $cal->clear();
        $cal->setDateTime($y, $m - 1, $d, 12, 0, 0);

        return Carbon::createFromTimestampMs((int) $cal->getTime(), 'UTC')->toDateString();
    }

    /** Hijri year number for a Gregorian date. */
    public static function hijriYear(string $date): ?int
    {
        if (! static::hasIntl()) {
            return null;
        }
        $cal = \IntlCalendar::createInstance('UTC', 'en_US@calendar=islamic-umalqura');
        $cal->setTime(Carbon::parse($date, 'UTC')->setTime(12, 0)->getTimestampMs());

        return $cal->get(\IntlCalendar::FIELD_YEAR);
    }

    /** "12 Ramadan 1448" (en) or «١٢ رمضان ١٤٤٨» (ar). */
    public static function hijriLabel(string $date, string $locale = 'en'): string
    {
        if (! static::hasIntl()) {
            return '';
        }
        $f = new \IntlDateFormatter(($locale === 'ar' ? 'ar_SA' : 'en_US').'@calendar=islamic-umalqura', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE,
            'UTC', \IntlDateFormatter::TRADITIONAL, 'd MMMM y');

        return (string) $f->format(Carbon::parse($date, 'UTC')->setTime(12, 0));
    }

    /**
     * Events overlapping [$from, $to], optionally for one market (fixed national days are market-specific).
     * @return array<int, array{key:string,label:string,ar:string,start:string,end:string,days:int,kind:string,markets:array,tip:string,estimated:bool}>
     */
    public static function events(string $from, string $to, ?string $market = null): array
    {
        $out = [];
        $f = Carbon::parse($from);
        $t = Carbon::parse($to);
        $cfg = config('planning');

        $hy = static::hijriYear($from);
        foreach ($cfg['hijri_events'] as $key => $e) {
            $starts = [];
            if ($hy) {
                for ($y = $hy - 1; $y <= (static::hijriYear($to) ?? $hy) + 1; $y++) {
                    $starts[] = static::fromHijri($y, $e['m'], $e['d']);
                }
            } else {
                $starts = $cfg['hijri_fallback'][$key] ?? [];
            }
            foreach (array_filter($starts) as $s) {
                $out[] = static::row($key, $e, $s, 'hijri', [], ! $hy);
            }
        }
        for ($y = $f->year - 1; $y <= $t->year; $y++) {
            foreach ($cfg['fixed_events'] as $key => $e) {
                $s = isset($e['rule']) && $e['rule'] === 'last_friday_nov'
                    ? Carbon::create($y, 11, 30)->modify('last friday of november')->toDateString()
                    : "$y-{$e['md']}";
                $out[] = static::row($key, $e, $s, 'fixed', $e['markets'], false);
            }
        }

        $out = array_values(array_filter($out, fn ($e) => $e['end'] >= $from && $e['start'] <= $to
            && (! $market || ! $e['markets'] || in_array($market, $e['markets'], true))));
        usort($out, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $out;
    }

    protected static function row(string $key, array $e, string $start, string $kind, array $markets, bool $estimated): array
    {
        return ['key' => $key, 'label' => $e['label'], 'ar' => $e['ar'], 'start' => $start,
            'end' => Carbon::parse($start)->addDays(max(1, (int) $e['days']) - 1)->toDateString(), 'days' => max(1, (int) $e['days']),
            'kind' => $kind, 'markets' => $markets, 'tip' => $e['tip'] ?? '', 'estimated' => $estimated];
    }

    /**
     * How this client performed in the latest past occurrence of an event vs the 28 days before it.
     * @return array{spend:float,cpr:?float,results:?float,start:string,end:string,source:string}|null
     */
    public static function seasonality(Client $c, string $key): ?array
    {
        $today = now($c->agency?->timezone ?: 'UTC')->toDateString();
        $past = array_reverse(array_filter(static::events(now()->subMonths(25)->toDateString(), $today),
            fn ($e) => $e['key'] === $key && $e['end'] < $today));
        foreach ($past as $e) {
            $base = MetricsQuery::totals($c, Carbon::parse($e['start'])->subDays(28)->toDateString(), Carbon::parse($e['start'])->subDay()->toDateString());
            $ev = MetricsQuery::totals($c, $e['start'], $e['end']);
            if ($base['spend'] <= 0 || $ev['spend'] <= 0) {
                continue;
            }
            $bSpend = $base['spend'] / 28;
            $eSpend = $ev['spend'] / $e['days'];

            return ['spend' => $eSpend / $bSpend,
                'cpr' => $base['cpr'] && $ev['cpr'] && $base['main_type'] === $ev['main_type'] ? $ev['cpr'] / $base['cpr'] : null,
                'results' => $base['main_results'] > 0 ? ($ev['main_results'] / $e['days']) / ($base['main_results'] / 28) : null,
                'start' => $e['start'], 'end' => $e['end'], 'source' => 'history'];
        }

        return null;
    }

    /**
     * Scenario for an upcoming event (#96): baseline = last 28 complete days; budget change in %.
     * CPR moves with the event's seasonality and with diminishing returns on extra budget.
     */
    public static function scenario(Client $c, array $event, float $budgetChangePct): array
    {
        $today = now($c->agency?->timezone ?: 'UTC')->startOfDay();
        $base = MetricsQuery::totals($c, $today->copy()->subDays(28)->toDateString(), $today->copy()->subDay()->toDateString());
        $hist = static::seasonality($c, $event['key']);
        $def = config('planning.seasonality_defaults')[$event['key']] ?? config('planning.seasonality_defaults._default');
        $cprIdx = $hist['cpr'] ?? $def['cpr'];
        $days = $event['days'];
        $daySpend = $base['spend'] / 28;
        $change = max(-0.9, $budgetChangePct / 100);
        $spend = $daySpend * (1 + $change) * $days;
        $cpr = $base['cpr'] ? $base['cpr'] * $cprIdx * (1 + $change) ** (float) config('planning.elasticity') : null;

        return [
            'baseline_day_spend' => $daySpend, 'baseline_cpr' => $base['cpr'], 'main_type' => $base['main_type'],
            'normal_spend' => $daySpend * $days, 'normal_results' => $base['cpr'] ? $daySpend * $days / ($base['cpr'] * $cprIdx) : null,
            'spend' => $spend, 'cpr' => $cpr, 'results' => $cpr ? $spend / $cpr : null,
            'cpr_index' => $cprIdx, 'spend_index' => $hist['spend'] ?? $def['spend'],
            'source' => $hist ? 'history' : 'default', 'history' => $hist,
        ];
    }
}
