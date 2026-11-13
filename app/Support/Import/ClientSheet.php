<?php

namespace App\Support\Import;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\PlanLimits;
use App\Support\TextClean;
use Illuminate\Support\Facades\DB;

/**
 * WS-03 / H-14 / NB-15 (D-297): many clients at once from a sheet (CSV or .xlsx), Arabic or English headers. A preview
 * first; names already in the workspace are skipped; the plan's client limit is respected.
 */
class ClientSheet
{
    public const COLUMNS = [
        'name' => ['name', 'client', 'client name', 'الاسم', 'العميل', 'اسم العميل'],
        'monthly_budget' => ['monthly budget', 'budget', 'الميزانية', 'الميزانية الشهرية'],
        'target_cpr' => ['target cost per result', 'target cpr', 'cost per result target', 'تكلفة النتيجة المستهدفة', 'التكلفة المستهدفة'],
        'target_results' => ['target results', 'results target', 'النتائج المستهدفة'],
        'target_roas' => ['target roas', 'roas target', 'العائد المستهدف'],
        'currency' => ['currency', 'العملة'],
        'market' => ['country', 'market', 'الدولة', 'السوق'],
        'vertical' => ['sector', 'vertical', 'industry', 'القطاع'],
    ];

    /** @return list<list<string>> the downloadable template: Arabic and English headers, one example row */
    public static function template(bool $ar): array
    {
        return [
            $ar ? ['اسم العميل', 'الميزانية الشهرية', 'تكلفة النتيجة المستهدفة', 'النتائج المستهدفة', 'العائد المستهدف', 'العملة', 'الدولة', 'القطاع']
                : ['Client name', 'Monthly budget', 'Target cost per result', 'Target results', 'Target ROAS', 'Currency', 'Country', 'Sector'],
            [$ar ? 'عيادة النور' : 'Al Noor Clinic', '3000', '12', '250', '', 'SAR', 'SA', 'clinic'],
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array{clients: list<array<string, mixed>>, skipped: list<string>, missing_name: bool}
     */
    public static function parse(Agency $agency, array $rows): array
    {
        $header = array_map(fn ($h) => mb_strtolower(trim((string) TextClean::invisible((string) $h))), $rows[0] ?? []);
        $col = [];
        foreach (self::COLUMNS as $field => $names) {
            foreach ($header as $i => $h) {
                if (in_array($h, $names, true)) {
                    $col[$field] = $i;
                    break;
                }
            }
        }
        if (! isset($col['name'])) {
            return ['clients' => [], 'skipped' => [], 'missing_name' => true];
        }
        $existing = Client::where('agency_id', $agency->id)->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->all();
        $num = fn ($v) => ($v = trim((string) TextClean::western((string) $v))) === '' ? null : (float) str_replace(',', '', $v);
        $verticals = array_keys((array) config('adsranger.verticals'));
        $clients = $skipped = [];
        foreach (array_slice($rows, 1) as $r) {
            $name = trim((string) TextClean::invisible((string) ($r[$col['name']] ?? '')));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (in_array($key, $existing, true)) {
                $skipped[] = $name;

                continue;
            }
            $existing[] = $key;
            $get = fn (string $f) => isset($col[$f]) ? ($r[$col[$f]] ?? null) : null;
            $vertical = strtolower(trim((string) $get('vertical')));
            $clients[] = [
                'name' => mb_substr($name, 0, 190),
                'monthly_budget' => $num($get('monthly_budget')),
                'target_cpr' => $num($get('target_cpr')),
                'target_results' => ($t = $num($get('target_results'))) === null ? null : (int) $t,
                'target_roas' => $num($get('target_roas')),
                'currency' => preg_match('/^[A-Za-z]{3}$/', trim((string) $get('currency'))) ? strtoupper(trim((string) $get('currency'))) : $agency->currency,
                'market' => preg_match('/^[A-Za-z]{2}$/', trim((string) $get('market'))) ? strtoupper(trim((string) $get('market'))) : null,
                'vertical' => in_array($vertical, $verticals, true) ? $vertical : null,
            ];
        }

        return ['clients' => $clients, 'skipped' => $skipped, 'missing_name' => false];
    }

    /** @return array{created: int, skipped: int, over_limit: int} */
    public static function import(Agency $agency, array $rows): array
    {
        $p = self::parse($agency, $rows);
        $room = max(0, PlanLimits::clientsLimit($agency) - PlanLimits::clientsUsed($agency));
        $take = array_slice($p['clients'], 0, $room);
        DB::transaction(function () use ($agency, $take) {
            foreach ($take as $c) {
                Client::create($c + ['agency_id' => $agency->id, 'status' => 'active']);
            }
        });
        AuditLog::record('clients.imported', $agency, ['created' => count($take), 'skipped' => count($p['skipped'])]);

        return ['created' => count($take), 'skipped' => count($p['skipped']), 'over_limit' => count($p['clients']) - count($take)];
    }
}
