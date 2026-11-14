<?php

namespace App\Support;

use App\Models\Client;
use App\Models\ClientLicence;
use App\Models\CreativeBrief;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Step 1a (D-308 / DS-20..23): light JSON sector-pack presets under resources/templates/sector_packs/*.json,
 * Charity donation labels & donation-per-1,000-impressions metric, per-ad neutral permit matching,
 * Real-Estate off-plan launch countdown, and MenaCalendar season highlighting.
 */
class SectorPacks
{
    /** @var array<string, array{key: string, kind: string, labels: array<string, string>, extra_metrics: list<array<string, string>>, seasons: list<string>, features: list<string>}>|null */
    protected static ?array $memo = null;

    /**
     * @return array<string, array{key: string, kind: string, labels: array<string, string>, extra_metrics: list<array<string, string>>, seasons: list<string>, features: list<string>}>
     */
    public static function all(): array
    {
        if (static::$memo !== null) {
            return static::$memo;
        }

        $packs = [];
        foreach (glob(resource_path('templates/sector_packs/*.json')) ?: [] as $file) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (! is_array($decoded)) {
                continue;
            }
            $key = (string) ($decoded['key'] ?? pathinfo($file, PATHINFO_FILENAME));
            $packs[$key] = static::normalize($key, $decoded);
        }

        return static::$memo = $packs;
    }

    public static function flushCache(): void
    {
        static::$memo = null;
    }

    /**
     * @return array{key: string, kind: string, labels: array<string, string>, extra_metrics: list<array<string, string>>, seasons: list<string>, features: list<string>}
     */
    public static function for(?string $vertical): array
    {
        $all = static::all();
        $key = strtolower(trim((string) $vertical));

        if ($key !== '' && isset($all[$key])) {
            return $all[$key];
        }

        return $all['other'] ?? static::normalize($key !== '' ? $key : 'other', []);
    }

    /**
     * @return array{key: string, kind: string, labels: array<string, string>, extra_metrics: list<array<string, string>>, seasons: list<string>, features: list<string>}
     */
    public static function forClient(?Client $client): array
    {
        return static::for($client?->vertical);
    }

    public static function resultLabel(?Client $client, ?string $type, ?string $locale = null): string
    {
        $loc = $locale ?: app()->getLocale();
        $pack = static::forClient($client);
        $normalizedType = strtolower(trim((string) $type));

        if ($normalizedType !== '' && isset($pack['labels'][$normalizedType])) {
            return __($pack['labels'][$normalizedType], [], $loc);
        }

        return ResultResolver::label($type, $loc);
    }

    public static function revenueLabel(?Client $client, ?string $locale = null): string
    {
        $loc = $locale ?: app()->getLocale();
        $pack = static::forClient($client);
        $key = $pack['labels']['revenue'] ?? 'Purchase value';

        return __($key, [], $loc);
    }

    public static function purchasesLabel(?Client $client, ?string $locale = null): string
    {
        $loc = $locale ?: app()->getLocale();
        $pack = static::forClient($client);
        $key = $pack['labels']['purchases'] ?? 'Purchases';

        return __($key, [], $loc);
    }

    /**
     * DS-20: Donation per 1,000 impressions = revenue / impressions * 1000, using MetricsQuery totals.
     * Safe when impressions == 0 (returns null so ResultResolver::money renders '–' without division by zero).
     *
     * @param  array<string, mixed>  $totals
     */
    public static function donationPerThousandImpressions(array $totals): ?float
    {
        $impressions = (float) ($totals['impressions'] ?? 0);
        if ($impressions <= 0) {
            return null;
        }

        $revenue = (float) ($totals['revenue'] ?? 0);

        return ($revenue / $impressions) * 1000.0;
    }

    /**
     * Computed extra metrics defined by the client's JSON sector pack, formatted through ResultResolver::money/decimals.
     *
     * @param  array<string, mixed>  $totals
     * @return list<array{key: string, label: string, value: ?float, decimals: int, formatted: string}>
     */
    public static function extraMetrics(Client $client, array $totals, ?string $locale = null): array
    {
        $loc = $locale ?: app()->getLocale();
        $pack = static::forClient($client);
        $out = [];

        foreach ($pack['extra_metrics'] as $def) {
            $formula = $def['formula'] ?? '';
            $val = match ($formula) {
                'revenue_per_1k_impressions' => static::donationPerThousandImpressions($totals),
                default => null,
            };
            $decimals = ResultResolver::decimals($val, $client->currency);
            $formatted = ($def['format'] ?? 'money') === 'money'
                ? ResultResolver::money($val, $client->currency)
                : ($val !== null ? number_format($val, $decimals) : '–');

            $out[] = [
                'key' => (string) ($def['key'] ?? $formula),
                'label' => __((string) ($def['label'] ?? ''), [], $loc),
                'value' => $val,
                'decimals' => $decimals,
                'formatted' => $formatted,
            ];
        }

        return $out;
    }

    /**
     * Highlighted MenaCalendar seasons for a client (or a set of season keys), including derived Last Ten Nights
     * (Ramadan start + 20 days via MenaCalendar).
     *
     * @param  list<string>|null  $onlyKeys
     * @return list<array{key: string, label: string, ar: string, start: string, end: string, days: int, status: string, highlighted: bool}>
     */
    public static function seasonsBetween(string $from, string $to, ?string $market = null, ?array $onlyKeys = null): array
    {
        $keys = $onlyKeys !== null ? array_values(array_unique($onlyKeys)) : null;
        if ($keys === []) {
            return [];
        }

        $lookbackFrom = Carbon::parse($from)->subDays(35)->toDateString();
        $rawEvents = MenaCalendar::events($lookbackFrom, $to, $market);
        $out = [];

        foreach ($rawEvents as $e) {
            if ($e['end'] >= $from && $e['start'] <= $to && ($keys === null || in_array($e['key'], $keys, true))) {
                $out[] = [
                    'key' => $e['key'],
                    'label' => $e['label'],
                    'ar' => $e['ar'],
                    'start' => $e['start'],
                    'end' => $e['end'],
                    'days' => (int) $e['days'],
                    'status' => (string) ($e['status'] ?? 'expected'),
                    'highlighted' => true,
                ];
            }

            if ($e['key'] === 'ramadan' && ($keys === null || in_array('last_ten_nights', $keys, true))) {
                $lastTenStart = Carbon::parse($e['start'])->addDays(20)->toDateString();
                $lastTenEnd = $e['end'];
                if ($lastTenEnd >= $from && $lastTenStart <= $to) {
                    $days = max(1, (int) Carbon::parse($lastTenStart)->diffInDays(Carbon::parse($lastTenEnd)) + 1);
                    $out[] = [
                        'key' => 'last_ten_nights',
                        'label' => 'Last ten nights of Ramadan',
                        'ar' => 'العشر الأواخر من رمضان',
                        'start' => $lastTenStart,
                        'end' => $lastTenEnd,
                        'days' => $days,
                        'status' => (string) ($e['status'] ?? 'expected'),
                        'highlighted' => true,
                    ];
                }
            }
        }

        usort($out, fn (array $a, array $b): int => [$a['start'], $a['key']] <=> [$b['start'], $b['key']]);

        return $out;
    }

    /**
     * Upcoming/active highlighted seasons for a client page (365-day horizon so annual seasons like Ramadan & Dhul Hijjah show).
     *
     * @return list<array{key: string, label: string, ar: string, start: string, end: string, days: int, status: string, highlighted: bool}>
     */
    public static function highlightedSeasonsForClient(Client $client, ?string $today = null): array
    {
        $pack = static::forClient($client);
        if ($pack['seasons'] === []) {
            return [];
        }

        $tz = $client->agency?->timezone ?: 'UTC';
        $start = $today ?: now($tz)->toDateString();
        $end = Carbon::parse($start)->addDays(365)->toDateString();
        $market = $client->market ?: ($client->agency?->country ?: null);

        return static::seasonsBetween($start, $end, $market, $pack['seasons']);
    }

    /**
     * DS-22: Real-estate off-plan launch countdown using all 5 Arabic plural forms + TextClean::western.
     *
     * @return array{project: string, date: string, days: int, text: string}|null
     */
    public static function launchCountdown(Client $client, ?string $today = null, ?string $locale = null): ?array
    {
        if (! $client->launch_date) {
            return null;
        }

        $loc = $locale ?: app()->getLocale();
        $tz = $client->agency?->timezone ?: 'UTC';
        $now = Carbon::parse($today ?: now($tz)->toDateString())->startOfDay();
        $target = $client->launch_date->copy()->startOfDay();
        $days = (int) $now->diffInDays($target, false);
        $project = trim((string) TextClean::invisible((string) ($client->launch_project_name ?? ''))) ?: __('Off-plan launch', [], $loc);

        if ($days < 0) {
            $dateLabel = (string) TextClean::western($target->toDateString());

            return [
                'project' => $project,
                'date' => $target->toDateString(),
                'days' => $days,
                'text' => (string) TextClean::western(__('Launched on :date', ['date' => $dateLabel], $loc)),
            ];
        }

        $westernDays = (string) TextClean::western((string) $days);
        $text = trans_choice(
            '{0} Launch today|{1} Launch in :count day|[2,*] Launch in :count days',
            $days,
            ['count' => $westernDays],
            $loc,
        );

        return [
            'project' => $project,
            'date' => $target->toDateString(),
            'days' => $days,
            'text' => (string) TextClean::western($text),
        ];
    }

    /**
     * DS-21 / DS-22: Licences matching a creative brief or ad text/name. General licences (empty ad_name) match all ads;
     * permits with an ad_name match when ad_name appears in the given ad name or brief/ad copy (neutral reminder only).
     *
     * @param  iterable<int, string|null>  $haystacks
     * @return Collection<int, ClientLicence>
     */
    public static function matchingLicences(Client $client, iterable $haystacks = [], bool $includeGeneral = true): Collection
    {
        $licences = ClientLicence::where('client_id', $client->id)->orderBy('expires_on')->get();
        if ($licences->isEmpty()) {
            return collect();
        }

        $normalizedHaystacks = [];
        foreach ($haystacks as $h) {
            $clean = mb_strtolower(trim((string) TextClean::invisible((string) $h)));
            if ($clean !== '') {
                $normalizedHaystacks[] = $clean;
            }
        }

        return $licences->filter(function (ClientLicence $licence) use ($normalizedHaystacks, $includeGeneral): bool {
            $adName = mb_strtolower(trim((string) TextClean::invisible((string) ($licence->ad_name ?? ''))));
            if ($adName === '') {
                return $includeGeneral;
            }

            foreach ($normalizedHaystacks as $text) {
                if (str_contains($text, $adName) || str_contains($adName, $text)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * Matching licences for a CreativeBrief (matches brief title, headline, hook, or primary_text, plus general licences).
     *
     * @return Collection<int, ClientLicence>
     */
    public static function licencesForBrief(CreativeBrief $brief): Collection
    {
        $client = $brief->relationLoaded('client') ? $brief->client : Client::find($brief->client_id);
        if (! $client) {
            return collect();
        }

        return static::matchingLicences($client, [
            $brief->title,
            $brief->headline,
            $brief->hook,
            $brief->primary_text,
        ], true);
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{key: string, kind: string, labels: array<string, string>, extra_metrics: list<array<string, string>>, seasons: list<string>, features: list<string>}
     */
    protected static function normalize(string $key, array $raw): array
    {
        $labels = [];
        foreach ((array) ($raw['labels'] ?? []) as $k => $v) {
            if (is_string($k) && is_string($v)) {
                $labels[$k] = $v;
            }
        }

        $extraMetrics = [];
        foreach ((array) ($raw['extra_metrics'] ?? []) as $m) {
            if (is_array($m) && ! empty($m['key'])) {
                $extraMetrics[] = [
                    'key' => (string) $m['key'],
                    'label' => (string) ($m['label'] ?? $m['key']),
                    'formula' => (string) ($m['formula'] ?? ''),
                    'format' => (string) ($m['format'] ?? 'money'),
                ];
            }
        }

        $seasons = array_values(array_filter(array_map('strval', (array) ($raw['seasons'] ?? []))));
        $features = array_values(array_filter(array_map('strval', (array) ($raw['features'] ?? []))));

        return [
            'key' => $key,
            'kind' => (string) ($raw['kind'] ?? 'sector_packs'),
            'labels' => $labels,
            'extra_metrics' => $extraMetrics,
            'seasons' => $seasons,
            'features' => $features,
        ];
    }
}
