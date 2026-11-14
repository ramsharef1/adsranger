<?php

namespace App\Support\Attribution;

use App\Models\Client;
use App\Models\ClientCohortSnapshot;
use App\Models\ClientUnitEconomic;
use App\Support\TextClean;

class CohortLtvAnalyzer
{
    /**
     * @param  array{
     *   cohort_month: string,
     *   acquisition_key: string,
     *   customers_count: int|string,
     *   cac_amount: float|int|string,
     *   d30_ltv: float|int|string,
     *   d60_ltv: float|int|string,
     *   d90_ltv: float|int|string,
     *   d180_ltv: float|int|string
     * }  $row
     */
    public static function recordCohort(Client $client, array $row): ClientCohortSnapshot
    {
        $month = substr(trim(TextClean::western((string) ($row['cohort_month'] ?? now()->format('Y-m')))), 0, 7);
        $key = trim((string) ($row['acquisition_key'] ?? 'all'));
        $customers = max(0, (int) TextClean::western((string) ($row['customers_count'] ?? 0)));
        $cac = max(0.0, (float) TextClean::western((string) ($row['cac_amount'] ?? 0)));
        $d30 = max(0.0, (float) TextClean::western((string) ($row['d30_ltv'] ?? 0)));
        $d60 = max($d30, (float) TextClean::western((string) ($row['d60_ltv'] ?? 0)));
        $d90 = max($d60, (float) TextClean::western((string) ($row['d90_ltv'] ?? 0)));
        $d180 = max($d90, (float) TextClean::western((string) ($row['d180_ltv'] ?? 0)));

        $paybackDays = self::interpolatePaybackDays($cac, $d30, $d60, $d90, $d180);

        return ClientCohortSnapshot::updateOrCreate(
            [
                'client_id' => $client->id,
                'cohort_month' => $month,
                'acquisition_key' => $key !== '' ? $key : 'all',
            ],
            [
                'agency_id' => $client->agency_id,
                'customers_count' => $customers,
                'cac_amount' => round($cac, 3),
                'd30_ltv' => round($d30, 3),
                'd60_ltv' => round($d60, 3),
                'd90_ltv' => round($d90, 3),
                'd180_ltv' => round($d180, 3),
                'payback_days' => $paybackDays,
            ]
        );
    }

    public static function importCsv(Client $client, string $csv): int
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv)) ?: [];
        if (count($lines) < 2) {
            return 0;
        }
        $headers = array_map(fn (string $h): string => strtolower(trim($h)), str_getcsv((string) array_shift($lines)));
        $saved = 0;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            if (count($cols) < count($headers)) {
                $cols = array_pad($cols, count($headers), '');
            }
            $assoc = array_combine($headers, array_slice($cols, 0, count($headers)));
            if (! is_array($assoc) || empty($assoc['cohort_month'])) {
                continue;
            }
            self::recordCohort($client, [
                'cohort_month' => (string) $assoc['cohort_month'],
                'acquisition_key' => (string) ($assoc['acquisition_key'] ?? 'all'),
                'customers_count' => $assoc['customers_count'] ?? 0,
                'cac_amount' => $assoc['cac_amount'] ?? 0,
                'd30_ltv' => $assoc['d30_ltv'] ?? 0,
                'd60_ltv' => $assoc['d60_ltv'] ?? 0,
                'd90_ltv' => $assoc['d90_ltv'] ?? 0,
                'd180_ltv' => $assoc['d180_ltv'] ?? 0,
            ]);
            $saved++;
        }

        return $saved;
    }

    /**
     * @return list<array{
     *   cohort_month: string,
     *   acquisition_key: string,
     *   customers_count: int,
     *   cac_amount: float,
     *   d30_ltv: float,
     *   d60_ltv: float,
     *   d90_ltv: float,
     *   d180_ltv: float,
     *   ltv_to_cac_d90: ?float,
     *   ltv_to_cac_d180: ?float,
     *   net_ltv_d90: float,
     *   payback_days: ?int
     * }>
     */
    public static function summarize(Client $client): array
    {
        $econ = ClientUnitEconomic::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->first();
        $grossMarginFactor = $econ && $econ->costs_entered_at
            ? max(0.0, 1.0 - ((float) $econ->cogs_pct / 100.0))
            : 1.0;

        return ClientCohortSnapshot::where('agency_id', $client->agency_id)
            ->where('client_id', $client->id)
            ->orderByDesc('cohort_month')
            ->orderBy('acquisition_key')
            ->get()
            ->map(function (ClientCohortSnapshot $row) use ($grossMarginFactor): array {
                $cac = (float) $row->cac_amount;

                return [
                    'cohort_month' => $row->cohort_month,
                    'acquisition_key' => $row->acquisition_key,
                    'customers_count' => (int) $row->customers_count,
                    'cac_amount' => $cac,
                    'd30_ltv' => (float) $row->d30_ltv,
                    'd60_ltv' => (float) $row->d60_ltv,
                    'd90_ltv' => (float) $row->d90_ltv,
                    'd180_ltv' => (float) $row->d180_ltv,
                    'ltv_to_cac_d90' => $cac > 0 ? round((float) $row->d90_ltv / $cac, 2) : null,
                    'ltv_to_cac_d180' => $cac > 0 ? round((float) $row->d180_ltv / $cac, 2) : null,
                    'net_ltv_d90' => round((float) $row->d90_ltv * $grossMarginFactor, 3),
                    'payback_days' => $row->payback_days,
                ];
            })
            ->all();
    }

    public static function interpolatePaybackDays(float $cac, float $d30, float $d60, float $d90, float $d180): ?int
    {
        if ($cac <= 0.0) {
            return 0;
        }
        $curve = [
            [0, 0.0],
            [30, $d30],
            [60, $d60],
            [90, $d90],
            [180, $d180],
        ];
        for ($i = 1, $n = count($curve); $i < $n; $i++) {
            [$prevDay, $prevVal] = $curve[$i - 1];
            [$curDay, $curVal] = $curve[$i];
            if ($curVal >= $cac) {
                $spanVal = $curVal - $prevVal;
                if ($spanVal <= 0.0) {
                    return $curDay;
                }
                $ratio = ($cac - $prevVal) / $spanVal;

                return max(1, (int) round($prevDay + $ratio * ($curDay - $prevDay)));
            }
        }

        return null;
    }
}
