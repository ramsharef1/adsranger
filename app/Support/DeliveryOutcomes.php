<?php

namespace App\Support;

use App\Models\Client;
use App\Models\DeliveryWeek;
use App\Models\User;
use App\Support\Import\ImportSheet;
use Carbon\Carbon;

/**
 * DS-23 (Step 1a, D-308): weekly delivery-app orders (Talabat, HungerStation, Jahez, Careem Food, etc.),
 * entered manually or imported from CSV/Excel with Arabic + English headers and Eastern-Arabic digits.
 */
class DeliveryOutcomes
{
    /** @var array<string, list<string>> */
    public const COLUMNS = [
        'week_start' => ['week', 'week start', 'week starting', 'week_start', 'date', 'الأسبوع', 'بداية الأسبوع', 'التاريخ'],
        'app_name' => ['app', 'app name', 'app_name', 'delivery app', 'platform', 'التطبيق', 'اسم التطبيق', 'تطبيق التوصيل', 'المنصة'],
        'orders_count' => ['orders', 'orders count', 'orders_count', 'order count', 'delivered', 'الطلبات', 'عدد الطلبات', 'طلبات التوصيل'],
        'orders_value' => ['value', 'order value', 'orders value', 'orders_value', 'revenue', 'amount', 'قيمة الطلبات', 'القيمة', 'المبيعات', 'الإيراد'],
    ];

    /** @return list<list<string>> */
    public static function template(bool $ar): array
    {
        return [
            $ar ? ['بداية الأسبوع', 'التطبيق', 'عدد الطلبات', 'قيمة الطلبات'] : ['Week start', 'App', 'Orders', 'Order value'],
            ['2026-11-01', 'Talabat', '120', '1450.00'],
        ];
    }

    public static function save(
        Client $client,
        string $weekStart,
        string $appName,
        int|string $ordersCount,
        float|int|string|null $ordersValue = null,
        string $source = 'manual',
        ?User $by = null,
    ): DeliveryWeek {
        $week = Carbon::parse(trim((string) TextClean::western($weekStart)))->toDateString();
        $app = mb_substr(trim((string) TextClean::invisible($appName)), 0, 80);
        $count = max(0, (int) str_replace(',', '', trim((string) TextClean::western((string) $ordersCount))));

        $parsedValue = null;
        if ($ordersValue !== null && trim((string) $ordersValue) !== '') {
            $cleanValue = str_replace(',', '', trim((string) TextClean::western((string) $ordersValue)));
            if (is_numeric($cleanValue)) {
                $parsedValue = max(0.0, round((float) $cleanValue, 3));
            }
        }

        $row = DeliveryWeek::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('week_start', $week)
            ->where('app_name', $app)
            ->first() ?? new DeliveryWeek([
                'agency_id' => $client->agency_id,
                'client_id' => $client->id,
                'week_start' => $week,
                'app_name' => $app,
            ]);

        $row->fill([
            'agency_id' => $client->agency_id,
            'orders_count' => $count,
            'orders_value' => $parsedValue,
            'source' => $source,
            'created_by' => $by?->id,
        ])->save();

        return $row;
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{saved: int, skipped: int, missing_columns: bool}
     */
    public static function import(Client $client, array $rows, ?User $by = null): array
    {
        $header = array_map(fn ($h): string => mb_strtolower(trim((string) TextClean::invisible((string) $h))), $rows[0] ?? []);
        $col = [];
        foreach (self::COLUMNS as $field => $names) {
            foreach ($header as $i => $h) {
                if (in_array($h, $names, true)) {
                    $col[$field] = $i;
                    break;
                }
            }
        }

        if (! isset($col['week_start'], $col['app_name'], $col['orders_count'])) {
            return ['saved' => 0, 'skipped' => 0, 'missing_columns' => true];
        }

        $saved = 0;
        $skipped = 0;
        foreach (array_slice($rows, 1, 520) as $r) {
            $dateRaw = trim((string) TextClean::western((string) ($r[$col['week_start']] ?? '')));
            $appRaw = trim((string) TextClean::invisible((string) ($r[$col['app_name']] ?? '')));
            try {
                $week = $dateRaw !== '' ? Carbon::parse($dateRaw)->toDateString() : null;
            } catch (\Throwable) {
                $week = null;
            }

            if (! $week || $appRaw === '') {
                $skipped++;

                continue;
            }

            $valueRaw = isset($col['orders_value']) ? ($r[$col['orders_value']] ?? null) : null;
            static::save($client, $week, $appRaw, (string) ($r[$col['orders_count']] ?? '0'), $valueRaw, 'sheet', $by);
            $saved++;
        }

        return ['saved' => $saved, 'skipped' => $skipped, 'missing_columns' => false];
    }

    /**
     * @return array{saved: int, skipped: int, missing_columns: bool}
     */
    public static function importCsv(Client $client, string $csv, ?User $by = null): array
    {
        return static::import($client, ImportSheet::fromCsv($csv), $by);
    }

    /**
     * @return array{saved: int, skipped: int, missing_columns: bool}
     */
    public static function importFile(Client $client, string $path, string $name, ?User $by = null): array
    {
        return static::import($client, ImportSheet::fromFile($path, $name), $by);
    }

    /**
     * Totals for delivery weeks starting inside [from, to], paired with MetricsQuery spend over the same days.
     *
     * @return array{weeks: int, orders: int, value: ?float, spend: float, cost_per_order: ?float, cost_per_order_formatted: string, value_formatted: ?string, by_app: array<string, int>, from: string, to: string}|null
     */
    public static function summary(Client $client, string $from, string $to): ?array
    {
        $rows = DeliveryWeek::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->whereDate('week_start', '>=', $from)
            ->whereDate('week_start', '<=', $to)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $first = $rows->min('week_start')->toDateString();
        $last = min($rows->max('week_start')->copy()->addDays(6)->toDateString(), $to);
        $spend = (float) MetricsQuery::totals($client, $first, $last)['spend'];
        $orders = (int) $rows->sum('orders_count');
        $valuedRows = $rows->whereNotNull('orders_value');
        $value = $valuedRows->isNotEmpty() ? (float) $valuedRows->sum('orders_value') : null;
        $costPerOrder = $orders > 0 ? $spend / $orders : null;
        $currency = $client->currency;

        $byApp = $rows->groupBy('app_name')
            ->map(fn ($group): int => (int) $group->sum('orders_count'))
            ->sortDesc()
            ->all();

        return [
            'weeks' => $rows->pluck('week_start')->map(fn ($d): string => $d->toDateString())->unique()->count(),
            'orders' => $orders,
            'value' => $value,
            'spend' => $spend,
            'cost_per_order' => $costPerOrder,
            'cost_per_order_formatted' => $client->hide_spend ? '–' : ResultResolver::money($costPerOrder, $currency),
            'value_formatted' => $value !== null ? ResultResolver::money($value, $currency) : null,
            'by_app' => $byApp,
            'from' => $first,
            'to' => $last,
        ];
    }
}
