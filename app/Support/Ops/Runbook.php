<?php

namespace App\Support\Ops;

/** FS-54: the runbook step for a health-alert kind (longest matching prefix in config/ops.php › runbook). */
class Runbook
{
    public static function hint(string $kind): ?string
    {
        $best = null;
        foreach ((array) config('ops.runbook') as $prefix => $hint) {
            if (str_starts_with($kind, $prefix) && ($best === null || strlen($prefix) > strlen($best[0]))) {
                $best = [$prefix, $hint];
            }
        }

        return $best[1] ?? null;
    }
}
