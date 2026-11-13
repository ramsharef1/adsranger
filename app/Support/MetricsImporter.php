<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\DailyMetric;
use App\Models\DataImport;
use App\Models\User;
use App\Support\Import\ImportSheet;
use App\Support\Import\NetworkGuess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Imports CSV / Google Sheets reports into daily_metrics, mapping column names automatically (EN + AR). */
class MetricsImporter
{
    public const SYNONYMS = [
        'date' => ['date', 'day', 'by day', 'reporting starts', 'reporting date', 'start date', 'start time', 'التاريخ', 'اليوم', 'تاريخ البدء', 'بدء إعداد التقارير', 'بداية إعداد التقارير'],
        'campaign_name' => ['campaign', 'campaign name', 'اسم الحملة', 'الحملة'],
        'campaign_id' => ['campaign id', 'معرف الحملة'],
        'spend' => ['spend', 'cost', 'amount spent', 'amount', 'budget spent', 'total cost', 'الإنفاق', 'المبلغ المنفق', 'المبلغ الذي تم إنفاقه', 'التكلفة'],
        'impressions' => ['impressions', 'impr', 'impr.', 'paid impressions', 'views', 'مرات الظهور', 'الظهور'],
        'reach' => ['reach', 'unique users', 'الوصول'],
        'clicks' => ['clicks', 'all clicks', 'النقرات'],
        'link_clicks' => ['link clicks', 'inline link clicks', 'outbound clicks', 'clicks (destination)', 'swipe ups', 'نقرات الرابط', 'نقرات على الرابط'],
        'conversions' => ['conversions', 'results', 'conv.', 'installs', 'actions', 'النتائج', 'التحويلات'],
        'leads' => ['leads', 'lead', 'العملاء المحتملون'],
        'purchases' => ['purchases', 'purchase', 'orders', 'المشتريات', 'الطلبات'],
        'messages' => ['messages', 'conversations', 'المحادثات', 'الرسائل'],
        'calls' => ['calls', 'phone calls', 'المكالمات'],
        'landing_views' => ['landing page views', 'landing views', 'مشاهدات الصفحة'],
        'add_to_cart' => ['add to cart', 'adds to cart', 'الإضافة إلى السلة'],
        'checkouts' => ['checkouts', 'checkouts initiated', 'initiate checkout', 'بدء الدفع'],
        'revenue' => ['revenue', 'conversion value', 'conv. value', 'purchase value', 'purchases conversion value', 'sales', 'الإيرادات', 'قيمة التحويل', 'قيمة تحويل المشتريات'],
        'result_type' => ['result type', 'result indicator', 'optimization goal', 'objective', 'نوع النتيجة', 'مؤشر النتائج', 'الهدف'],
        'currency' => ['currency', 'العملة'],
    ];

    public const MAX_ROWS = 50000;

    public static function sheetCsvUrl(string $url): string
    {
        if (! preg_match('~docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]+)~', $url, $m)) {
            throw new RuntimeException('That is not a Google Sheets link.');
        }
        $gid = preg_match('~[#&?]gid=(\d+)~', $url, $g) ? $g[1] : '0';

        return "https://docs.google.com/spreadsheets/d/{$m[1]}/export?format=csv&gid={$gid}";
    }

    public static function fetchSheet(string $url): string
    {
        $res = Http::timeout(30)->get(static::sheetCsvUrl($url));
        if ($res->failed() || str_contains((string) $res->header('Content-Type'), 'text/html')) {
            throw new RuntimeException('Could not read the sheet. Share it as "Anyone with the link can view" and try again.');
        }

        return $res->body();
    }

    protected static function norm(string $h): string
    {
        $h = (string) TextClean::invisible(preg_replace('/^\xEF\xBB\xBF/', '', $h));
        $h = mb_strtolower(trim($h));
        $h = preg_replace('/\s*\((usd|jod|sar|aed|eur|gbp|[a-z]{3})\)\s*$/u', '', $h);

        return trim(preg_replace('/\s+/u', ' ', $h));
    }

    /** @return array<string,int> field => column index */
    public static function map(array $headers): array
    {
        $map = [];
        $norm = array_map([static::class, 'norm'], $headers);
        foreach (self::SYNONYMS as $field => $words) {
            foreach ($norm as $i => $h) {
                if (in_array($h, $words, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
            if (! isset($map[$field])) {
                foreach ($norm as $i => $h) {
                    foreach ($words as $w) {
                        if (mb_strlen($w) > 3 && str_starts_with($h, $w) && ! in_array($i, $map, true)) {
                            $map[$field] = $i;
                            break 2;
                        }
                    }
                }
            }
        }

        return $map;
    }

    protected static function num($v): float
    {
        $v = (string) TextClean::western(trim((string) $v)); // PS-24
        $v = preg_replace('/[^0-9.,\-]/', '', $v);
        if (substr_count($v, ',') && ! substr_count($v, '.')) {
            $v = substr_count($v, ',') === 1 && strlen(substr(strrchr($v, ','), 1)) <= 2 ? str_replace(',', '.', $v) : str_replace(',', '', $v);
        }

        return (float) str_replace(',', '', $v);
    }

    protected static function date($v): ?string
    {
        $v = (string) TextClean::western(TextClean::invisible(trim((string) $v)));
        if ($v === '') {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y', 'M j, Y', 'j M Y'] as $f) {
            try {
                $d = Carbon::createFromFormat($f, $v);
                if ($d && $d->format($f) === $v) {
                    return $d->toDateString();
                }
            } catch (\Throwable) {
            }
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /** PS-01: the header is the first of the top rows that names a date and a spend column (exports often start with a title). */
    public static function headerRow(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 10, true) as $i => $row) {
            $map = static::map($row);
            if (isset($map['date'], $map['spend'])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * PS-01: what an import would do, before saving: the network it looks like, the matched columns and the first rows.
     *
     * @param  list<list<string>>  $rows
     * @return array{network: ?string, header: ?int, mapping: array<string, string>, missing: list<string>, sample: list<array<string, mixed>>, rows: int}
     */
    public static function preview(array $rows, int $limit = 5): array
    {
        $h = static::headerRow($rows);
        if ($h === null) {
            $first = $rows[0] ?? [];
            $map = static::map($first);

            return ['network' => null, 'header' => null, 'mapping' => collect($map)->map(fn ($i) => (string) $first[$i])->all(),
                'missing' => array_values(array_diff(['date', 'spend'], array_keys($map))), 'sample' => [], 'rows' => 0];
        }
        $headers = $rows[$h];
        $map = static::map($headers);
        $sample = [];
        $count = 0;
        foreach (array_slice($rows, $h + 1) as $r) {
            $d = static::date($r[$map['date']] ?? null);
            if (! $d) {
                continue;
            }
            $count++;
            if (count($sample) < $limit) {
                $sample[] = ['date' => $d, 'campaign' => isset($map['campaign_name']) ? TextClean::invisible($r[$map['campaign_name']] ?? '') : null,
                    'spend' => static::num($r[$map['spend']] ?? 0), 'results' => isset($map['conversions']) ? static::num($r[$map['conversions']] ?? 0) : null];
            }
        }

        return ['network' => NetworkGuess::guess(array_map([static::class, 'norm'], $headers)), 'header' => $h,
            'mapping' => collect($map)->map(fn ($i) => (string) $headers[$i])->all(), 'missing' => [], 'sample' => $sample, 'rows' => $count];
    }

    public static function import(Agency $agency, User $user, string $network, string $csv, string $filename, string $source = 'file', ?int $clientId = null): DataImport
    {
        return static::importRows($agency, $user, $network, ImportSheet::fromCsv($csv, self::MAX_ROWS + 11), $filename, $source, $clientId);
    }

    /**
     * Saves the rows. PS-07: a row already imported for the same network, client, campaign and day is skipped when nothing
     * changed and updated when a number changed, so next month's export can be dropped in whole. PS-26: invisible marks are
     * stripped from campaign names and counted.
     *
     * @param  list<list<string>>  $rows
     */
    public static function importRows(Agency $agency, User $user, ?string $network, array $rows, string $filename, string $source = 'file', ?int $clientId = null): DataImport
    {
        $h = static::headerRow($rows);
        if ($h === null) {
            $map = static::map($rows[0] ?? []);
            foreach (['date', 'spend'] as $req) {
                if (! isset($map[$req])) {
                    throw new RuntimeException('Could not find a "'.$req.'" column. Columns found: '.Str::limit(implode(', ', $rows[0] ?? []), 200));
                }
            }
        }
        $headers = $rows[$h];
        $map = static::map($headers);
        $data = array_slice($rows, $h + 1);
        if ($data === []) {
            throw new RuntimeException('The file has no data rows.');
        }
        if (count($data) > self::MAX_ROWS) {
            throw new RuntimeException('Too many rows (max '.self::MAX_ROWS.' per import).');
        }
        $network = $network ?: (NetworkGuess::guess(array_map([static::class, 'norm'], $headers)) ?? 'other');

        return DB::transaction(function () use ($agency, $user, $network, $data, $map, $headers, $filename, $source, $clientId) {
            $import = DataImport::create(['agency_id' => $agency->id, 'client_id' => $clientId, 'user_id' => $user->id, 'network' => $network, 'source' => $source,
                'filename' => Str::limit($filename, 190), 'mapping' => collect($map)->map(fn ($i) => $headers[$i])->all()]);
            $get = fn ($row, $f) => isset($map[$f]) ? ($row[$map[$f]] ?? null) : null;
            $parsed = [];
            $cleaned = 0;
            $now = now();
            foreach ($data as $r) {
                $d = static::date($get($r, 'date'));
                if (! $d) {
                    continue; // skip totals / blank rows
                }
                $rawName = (string) $get($r, 'campaign_name');
                if (TextClean::hasInvisible($rawName)) {
                    $cleaned++;
                }
                $clicksVal = (int) static::num($get($r, 'clicks'));
                $linkClicksVal = (int) static::num($get($r, 'link_clicks')) ?: $clicksVal;
                $leadsVal = static::num($get($r, 'leads'));
                $purchasesVal = static::num($get($r, 'purchases'));
                $messagesVal = static::num($get($r, 'messages'));
                $callsVal = static::num($get($r, 'calls'));
                $convVal = static::num($get($r, 'conversions'));
                $rawType = strtolower(trim((string) $get($r, 'result_type')));
                $inferredType = $rawType ?: ($purchasesVal > 0 ? 'purchase' : ($leadsVal > 0 ? 'lead' : ($messagesVal > 0 ? 'message' : ($callsVal > 0 ? 'call' : 'conversion'))));
                $norm = ResultResolver::normalizeRow([
                    'result_type' => $inferredType,
                    'spend' => static::num($get($r, 'spend')),
                    'impressions' => (int) static::num($get($r, 'impressions')),
                    'reach' => (int) static::num($get($r, 'reach')),
                    'clicks' => $clicksVal ?: $linkClicksVal,
                    'link_clicks' => $linkClicksVal,
                    'results' => $convVal,
                    'leads' => $leadsVal,
                    'purchases' => $purchasesVal,
                    'messages' => $messagesVal,
                    'calls' => $callsVal,
                    'landing_views' => static::num($get($r, 'landing_views')),
                    'add_to_cart' => static::num($get($r, 'add_to_cart')),
                    'checkouts' => static::num($get($r, 'checkouts')),
                    'revenue' => static::num($get($r, 'revenue')),
                ]);
                $parsed[] = [
                    'agency_id' => $agency->id, 'client_id' => $clientId, 'level' => 'campaign', 'result_type' => $norm['result_type'], 'roas_eligible' => $norm['roas_eligible'],
                    'data_import_id' => $import->id, 'network' => $network, 'date' => $d,
                    'campaign_id' => Str::limit((string) TextClean::invisible((string) $get($r, 'campaign_id')), 190, '') ?: null,
                    'campaign_name' => Str::limit((string) TextClean::invisible($rawName), 250, '') ?: null,
                    'spend' => $norm['spend'], 'impressions' => $norm['impressions'], 'reach' => $norm['reach'],
                    'clicks' => $norm['clicks'], 'link_clicks' => $norm['link_clicks'], 'conversions' => $norm['results'], 'results' => $norm['results'],
                    'leads' => $norm['leads'], 'purchases' => $norm['purchases'], 'messages' => $norm['messages'], 'calls' => $norm['calls'],
                    'landing_views' => $norm['landing_views'], 'add_to_cart' => $norm['add_to_cart'], 'checkouts' => $norm['checkouts'],
                    'revenue' => $norm['revenue'],
                    'currency' => Str::upper(Str::limit((string) ($get($r, 'currency') ?: $agency->currency), 8, '')),
                    'source' => 'import', 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            if ($parsed === []) {
                throw new RuntimeException('No rows had a readable date.');
            }

            // PS-07: rows already imported for the same days.
            $key = fn ($row) => implode('|', [$row['client_id'] ?? '', $row['campaign_id'] ?: mb_strtolower((string) $row['campaign_name']), substr((string) $row['date'], 0, 10)]);
            $dates = array_unique(array_column($parsed, 'date'));
            $existing = DailyMetric::query()->where('agency_id', $agency->id)->where('network', $network)->where('source', 'import')
                ->where('client_id', $clientId)->whereIn('date', $dates)->get()->keyBy(fn ($m) => $key($m->getAttributes()));
            $fields = ['spend', 'impressions', 'reach', 'clicks', 'link_clicks', 'results', 'leads', 'purchases', 'messages', 'calls', 'landing_views', 'add_to_cart', 'checkouts', 'revenue'];
            $added = $updated = $skipped = 0;
            $batch = [];
            $seen = [];
            foreach ($parsed as $row) {
                $k = $key($row);
                $old = $existing[$k] ?? null;
                if ($old && ! isset($seen[$k])) {
                    $seen[$k] = true;
                    $same = collect($fields)->every(fn ($f) => abs((float) $old->$f - (float) $row[$f]) < 0.0001);
                    if ($same) {
                        $skipped++;
                    } else {
                        $old->forceFill(collect($row)->only([...$fields, 'conversions', 'result_type', 'roas_eligible', 'data_import_id', 'currency'])->all())->save();
                        $updated++;
                    }

                    continue;
                }
                $batch[] = $row;
                $added++;
                if (count($batch) >= 500) {
                    DailyMetric::insert($batch);
                    $batch = [];
                }
            }
            if ($batch) {
                DailyMetric::insert($batch);
            }
            $import->update(['rows' => $added, 'rows_updated' => $updated, 'rows_skipped' => $skipped, 'names_cleaned' => $cleaned]);
            AuditLog::record('data.imported', $agency, ['import_id' => $import->id, 'network' => $network, 'rows' => $added, 'updated' => $updated, 'skipped' => $skipped]);

            return $import;
        });
    }
}
