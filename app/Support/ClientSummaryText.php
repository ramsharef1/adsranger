<?php

namespace App\Support;

use App\Models\Client;

/**
 * XS-08 (R4): a short client summary ready to paste into WhatsApp, in Arabic or English. Rules only (no AI), the
 * client's own currency, hide_spend respected, change vs the period before, and no AdsRanger name (agency guard).
 */
class ClientSummaryText
{
    public static function make(Client $c, string $from, string $to, string $lang): string
    {
        $ar = $lang === 'ar';
        [$pf, $pt] = Periods::previous($from, $to);
        $t = MetricsQuery::totals($c, $from, $to);
        $p = MetricsQuery::totals($c, $pf, $pt);
        $cur = $c->currency ?: 'USD';
        $money = fn (float $v) => number_format($v, 2).' '.$cur;
        $chg = function (?float $now, ?float $prev) use ($ar): string {
            if (! $prev || $now === null) {
                return '';
            }
            $pct = round(($now / $prev - 1) * 100);
            if ($pct === 0.0 || $pct == 0) {
                return $ar ? ' (دون تغيير)' : ' (no change)';
            }

            return ' ('.($pct > 0 ? '+' : '').$pct.'% '.($ar ? 'عن الفترة السابقة' : 'vs the period before').')';
        };
        $result = SectorPacks::resultLabel($c, $t['main_type'], $ar ? 'ar' : 'en') ?: ($ar ? 'النتائج' : 'Results');

        $lines = DemoData::isSample($c) ? [$ar ? '⚠️ بيانات تجريبية: الأرقام ليست حقيقية' : '⚠️ Sample data: these numbers are not real'] : [];
        $lines[] = '*'.$c->name.'* · '.$from.' → '.$to;
        if (! $c->hide_spend) {
            $lines[] = ($ar ? 'الإنفاق: ' : 'Spend: ').$money((float) $t['spend']).$chg((float) $t['spend'], (float) $p['spend']);
        }
        $lines[] = $result.': '.number_format((float) $t['main_results']).$chg((float) $t['main_results'], (float) $p['main_results']);
        if (! $c->hide_spend && $t['cpr'] !== null) {
            $lines[] = ($ar ? 'تكلفة النتيجة: ' : 'Cost per result: ').$money((float) $t['cpr']).$chg((float) $t['cpr'], $p['cpr'] !== null ? (float) $p['cpr'] : null)
                .($c->target_cpr ? ($ar ? ' · المستهدف ' : ' · target ').$money((float) $c->target_cpr) : '');
        }
        if ($t['roas'] !== null) {
            $lines[] = 'ROAS: '.number_format((float) $t['roas'], 2).'x'.($c->target_roas ? ($ar ? ' · المستهدف ' : ' · target ').number_format((float) $c->target_roas, 2).'x' : '');
        }
        if ((float) $t['spend'] === 0.0 && (float) $t['main_results'] === 0.0) {
            $lines[] = $ar ? 'لا بيانات لهذه الفترة.' : 'No data for this period.';
        }

        return implode("\n", $lines);
    }
}
