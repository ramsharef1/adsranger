<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** D-88 / UX-66: alert levels high / medium / low → critical / warning / info (alerts + stored account-health issues). */
return new class extends Migration
{
    private const UP = ['high' => 'critical', 'medium' => 'warning', 'low' => 'info'];

    public function up(): void
    {
        $this->rename(self::UP);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::UP));
    }

    private function rename(array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table('alerts')->where('severity', $from)->update(['severity' => $to]);
        }
        DB::table('ad_accounts')->whereNotNull('health')->orderBy('id')->each(function ($a) use ($map) {
            $issues = json_decode((string) $a->health, true);
            if (! is_array($issues)) {
                return;
            }
            $new = array_map(fn ($i) => is_array($i) && isset($i[0]) ? array_replace($i, [0 => $map[$i[0]] ?? $i[0]]) : $i, $issues);
            if ($new !== $issues) {
                DB::table('ad_accounts')->where('id', $a->id)->update(['health' => json_encode($new)]);
            }
        });
    }
};
