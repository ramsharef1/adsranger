<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\CodWeek;
use App\Models\User;
use App\Support\Import\ImportSheet;
use Carbon\Carbon;

/**
 * FS-16 / XS-14 / WK-01 (R4, D-304): cash-on-delivery outcomes as weekly counts (delivered, returned, cancelled), per
 * client or per campaign, typed in or from a simple sheet. Turns ad cost into cost per delivered order. No order IDs and
 * no customer data are ever stored.
 */
class CodOutcomes
{
    public const COLUMNS = [
        'week_start' => ['week', 'week start', 'week starting', 'date', 'الأسبوع', 'بداية الأسبوع', 'التاريخ'],
        'campaign' => ['campaign', 'campaign name', 'الحملة', 'اسم الحملة'],
        'delivered' => ['delivered', 'delivered orders', 'تم التوصيل', 'المسلّمة', 'المسلمة', 'طلبات مسلمة'],
        'returned' => ['returned', 'returns', 'مرتجع', 'المرتجعة', 'مرتجعات'],
        'cancelled' => ['cancelled', 'canceled', 'ملغى', 'الملغاة', 'ملغاة'],
    ];

    /** @return list<list<string>> */
    public static function template(bool $ar): array
    {
        return [
            $ar ? ['بداية الأسبوع', 'الحملة', 'تم التوصيل', 'مرتجع', 'ملغى'] : ['Week start', 'Campaign', 'Delivered', 'Returned', 'Cancelled'],
            ['2026-11-01', '', '84', '12', '6'],
        ];
    }

    public static function save(Client $client, string $weekStart, ?int $campaignId, int $delivered, int $returned, int $cancelled, string $source, ?User $by): CodWeek
    {
        $week = Carbon::parse($weekStart)->toDateString();
        $row = CodWeek::where('client_id', $client->id)->whereDate('week_start', $week)
            ->when($campaignId, fn ($q) => $q->where('campaign_id', $campaignId), fn ($q) => $q->whereNull('campaign_id'))->first()
            ?? new CodWeek(['client_id' => $client->id, 'week_start' => $week, 'campaign_id' => $campaignId]);
        $row->fill(['agency_id' => $client->agency_id, 'delivered' => max(0, $delivered), 'returned' => max(0, $returned), 'cancelled' => max(0, $cancelled),
            'source' => $source, 'created_by' => $by?->id])->save();

        return $row;
    }

    /** @return array<int, string> this client's campaigns (id → name), for the picker and for matching sheet rows by name */
    public static function campaigns(Client $client): array
    {
        return Campaign::whereIn('ad_account_id', $client->adAccounts()->pluck('id'))->whereNotNull('name')->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{saved: int, skipped: int, unknown_campaigns: list<string>, missing_columns: bool}
     */
    public static function import(Client $client, array $rows, ?User $by): array
    {
        $header = array_map(fn ($h) => mb_strtolower(trim(TextClean::invisible((string) $h))), $rows[0] ?? []);
        $col = [];
        foreach (self::COLUMNS as $field => $names) {
            foreach ($header as $i => $h) {
                if (in_array($h, $names, true)) {
                    $col[$field] = $i;
                    break;
                }
            }
        }
        if (! isset($col['week_start'], $col['delivered'])) {
            return ['saved' => 0, 'skipped' => 0, 'unknown_campaigns' => [], 'missing_columns' => true];
        }
        $byName = array_change_key_case(array_flip(static::campaigns($client)), CASE_LOWER);
        $int = fn ($v) => (int) str_replace(',', '', trim(TextClean::western((string) $v)));
        $saved = 0;
        $skipped = 0;
        $unknown = [];
        foreach (array_slice($rows, 1, 520) as $r) {
            $date = trim(TextClean::western((string) ($r[$col['week_start']] ?? '')));
            try {
                $week = $date !== '' ? Carbon::parse($date)->toDateString() : null;
            } catch (\Throwable) {
                $week = null;
            }
            if (! $week) {
                $skipped++;

                continue;
            }
            $campaignId = null;
            $name = isset($col['campaign']) ? trim((string) ($r[$col['campaign']] ?? '')) : '';
            if ($name !== '') {
                $campaignId = $byName[mb_strtolower($name)] ?? null;
                if ($campaignId === null) {
                    $unknown[$name] = true; // kept at client level rather than lost
                }
            }
            static::save($client, $week, $campaignId, $int($r[$col['delivered']] ?? 0), $int(isset($col['returned']) ? ($r[$col['returned']] ?? 0) : 0),
                $int(isset($col['cancelled']) ? ($r[$col['cancelled']] ?? 0) : 0), 'sheet', $by);
            $saved++;
        }

        return ['saved' => $saved, 'skipped' => $skipped, 'unknown_campaigns' => array_keys($unknown), 'missing_columns' => false];
    }

    public static function importFile(Client $client, string $path, string $name, ?User $by): array
    {
        return static::import($client, ImportSheet::fromFile($path, $name), $by);
    }

    /**
     * Totals for weeks that start inside [from, to], with ad spend over the same days.
     *
     * @return array{weeks: int, delivered: int, returned: int, cancelled: int, placed: int, delivery_rate: ?float, spend: float, cost_per_delivered: ?float, cost_per_placed: ?float, from: string, to: string}|null
     */
    public static function summary(Client $client, string $from, string $to): ?array
    {
        $rows = CodWeek::where('client_id', $client->id)->whereBetween('week_start', [$from, $to])->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $first = $rows->min('week_start')->toDateString();
        $last = $rows->max('week_start')->copy()->addDays(6)->toDateString();
        $spend = (float) MetricsQuery::totals($client, $first, min($last, $to))['spend'];
        $d = (int) $rows->sum('delivered');
        $placed = $d + (int) $rows->sum('returned') + (int) $rows->sum('cancelled');

        return ['weeks' => $rows->pluck('week_start')->unique()->count(), 'delivered' => $d, 'returned' => (int) $rows->sum('returned'), 'cancelled' => (int) $rows->sum('cancelled'),
            'placed' => $placed, 'delivery_rate' => $placed > 0 ? round($d / $placed * 100, 1) : null, 'spend' => $spend,
            'cost_per_delivered' => $d > 0 ? round($spend / $d, 2) : null, 'cost_per_placed' => $placed > 0 ? round($spend / $placed, 2) : null,
            'from' => $first, 'to' => min($last, $to)];
    }
}
