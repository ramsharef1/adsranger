<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\DailyMetric;
use App\Models\DataImport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Imports CSV / Google Sheets reports into daily_metrics, mapping column names automatically (EN + AR). */
class MetricsImporter
{
    public const SYNONYMS = [
        'date' => ['date', 'day', 'reporting starts', 'reporting date', 'start date', 'التاريخ', 'اليوم', 'تاريخ البدء'],
        'campaign_name' => ['campaign', 'campaign name', 'اسم الحملة', 'الحملة'],
        'campaign_id' => ['campaign id', 'معرف الحملة'],
        'spend' => ['spend', 'cost', 'amount spent', 'amount', 'budget spent', 'الإنفاق', 'المبلغ المنفق', 'التكلفة'],
        'impressions' => ['impressions', 'impr', 'impr.', 'views', 'مرات الظهور', 'الظهور'],
        'clicks' => ['clicks', 'link clicks', 'all clicks', 'النقرات', 'نقرات الرابط'],
        'conversions' => ['conversions', 'results', 'leads', 'purchases', 'installs', 'actions', 'النتائج', 'التحويلات', 'العملاء المحتملون'],
        'revenue' => ['revenue', 'conversion value', 'purchase value', 'purchases conversion value', 'sales', 'الإيرادات', 'قيمة التحويل'],
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
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
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
        $v = trim((string) $v);
        $v = strtr($v, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => ',']);
        $v = preg_replace('/[^0-9.,\-]/', '', $v);
        if (substr_count($v, ',') && ! substr_count($v, '.')) {
            $v = substr_count($v, ',') === 1 && strlen(substr(strrchr($v, ','), 1)) <= 2 ? str_replace(',', '.', $v) : str_replace(',', '', $v);
        }

        return (float) str_replace(',', '', $v);
    }

    protected static function date($v): ?string
    {
        $v = trim((string) $v);
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

    public static function import(Agency $agency, User $user, string $network, string $csv, string $filename, string $source = 'file', ?int $clientId = null): DataImport
    {
        $csv = str_replace(["\r\n", "\r"], "\n", $csv);
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            throw new RuntimeException('The file has no data rows.');
        }
        $delim = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : (substr_count($lines[0], "\t") > substr_count($lines[0], ',') ? "\t" : ',');
        $headers = str_getcsv($lines[0], $delim, "\"", "");
        $map = static::map($headers);
        foreach (['date', 'spend'] as $req) {
            if (! isset($map[$req])) {
                throw new RuntimeException('Could not find a "'.$req.'" column. Columns found: '.Str::limit(implode(', ', $headers), 200));
            }
        }
        if (count($lines) - 1 > self::MAX_ROWS) {
            throw new RuntimeException('Too many rows (max '.self::MAX_ROWS.' per import).');
        }

        return DB::transaction(function () use ($agency, $user, $network, $lines, $delim, $map, $headers, $filename, $source, $clientId) {
            $import = DataImport::create(['agency_id' => $agency->id, 'client_id' => $clientId, 'user_id' => $user->id, 'network' => $network, 'source' => $source,
                'filename' => Str::limit($filename, 190), 'mapping' => collect($map)->map(fn ($i) => $headers[$i])->all()]);
            $get = fn ($row, $f) => isset($map[$f]) ? ($row[$map[$f]] ?? null) : null;
            $batch = [];
            $count = 0;
            $now = now();
            foreach (array_slice($lines, 1) as $line) {
                $r = str_getcsv($line, $delim, "\"", "");
                $d = static::date($get($r, 'date'));
                if (! $d) {
                    continue; // skip totals / blank rows
                }
                $batch[] = [
                    'agency_id' => $agency->id, 'client_id' => $clientId, 'level' => 'campaign', 'result_type' => 'conversion', 'data_import_id' => $import->id, 'network' => $network, 'date' => $d,
                    'campaign_id' => Str::limit((string) $get($r, 'campaign_id'), 190, '') ?: null,
                    'campaign_name' => Str::limit((string) $get($r, 'campaign_name'), 250, '') ?: null,
                    'spend' => static::num($get($r, 'spend')), 'impressions' => (int) static::num($get($r, 'impressions')),
                    'clicks' => (int) static::num($get($r, 'clicks')), 'conversions' => static::num($get($r, 'conversions')), 'results' => static::num($get($r, 'conversions')),
                    'revenue' => static::num($get($r, 'revenue')),
                    'currency' => Str::upper(Str::limit((string) ($get($r, 'currency') ?: $agency->currency), 8, '')),
                    'source' => 'import', 'created_at' => $now, 'updated_at' => $now,
                ];
                $count++;
                if (count($batch) >= 500) {
                    DailyMetric::insert($batch);
                    $batch = [];
                }
            }
            if ($batch) {
                DailyMetric::insert($batch);
            }
            if ($count === 0) {
                throw new RuntimeException('No rows had a readable date.');
            }
            $import->update(['rows' => $count]);
            AuditLog::record('data.imported', $agency, ['import_id' => $import->id, 'network' => $network, 'rows' => $count]);

            return $import;
        });
    }
}
