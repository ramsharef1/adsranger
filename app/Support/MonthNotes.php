<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientMonthNote;
use App\Models\CodWeek;
use Carbon\Carbon;

/**
 * XS-03 / FS-20 / R2-56 (R4): «ما أنجزناه هذا الشهر» and «ما سنعمله الشهر القادم» for the monthly report. A draft is
 * built from what the app already knows (alerts handled, contacts, target or budget changes, pauses, COD weeks,
 * next month's seasons); the agency edits it before it goes in the report. Nothing is sent on its own.
 */
class MonthNotes
{
    public static function get(Client $c, string $month): ?ClientMonthNote
    {
        return ClientMonthNote::where('client_id', $c->id)->where('month', $month)->first();
    }

    public static function save(Client $c, string $month, ?string $done, ?string $next, ?int $by): ClientMonthNote
    {
        $clean = fn (?string $s) => ($s = trim(strip_tags((string) $s))) === '' ? null : mb_substr($s, 0, 3000);

        return ClientMonthNote::updateOrCreate(['client_id' => $c->id, 'month' => $month],
            ['agency_id' => $c->agency_id, 'done' => $clean($done), 'next' => $clean($next), 'updated_by' => $by]);
    }

    /** @return array{done: string, next: string} a draft in the given language, one line per item */
    public static function draft(Client $c, string $month, string $lang): array
    {
        $ar = $lang === 'ar';
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $done = [];

        $resolved = Alert::where('client_id', $c->id)->where('status', 'resolved')->whereBetween('resolved_at', [$start, $end])->count();
        if ($resolved) {
            $done[] = $ar ? "التنبيهات التي عالجناها على الحسابات الإعلانية: {$resolved}." : "Handled {$resolved} account alerts.";
        }
        $contacts = ClientContact::where('client_id', $c->id)->whereBetween('happened_on', [$start->toDateString(), $end->toDateString()])->get();
        if ($contacts->isNotEmpty()) {
            $meetings = $contacts->where('kind', 'meeting')->count();
            $done[] = $ar ? 'مرات التواصل معكم: '.$contacts->count().($meetings ? " (منها اجتماعات: {$meetings})" : '').'.'
                : 'Were in touch '.$contacts->count().' times'.($meetings ? ", including {$meetings} meetings" : '').'.';
        }
        $changes = AuditLog::where('action', 'client.updated')->whereBetween('created_at', [$start, $end])
            ->where('meta->client_id', $c->id)->get()->flatMap(fn ($l) => (array) ($l->meta['changed'] ?? []))->unique();
        if ($changes->intersect(['monthly_budget'])->isNotEmpty()) {
            $done[] = $ar ? 'حدّثنا الميزانية الشهرية.' : 'Updated the monthly budget.';
        }
        if ($changes->intersect(['target_cpr', 'target_results', 'target_roas'])->isNotEmpty()) {
            $done[] = $ar ? 'حدّثنا الأهداف (تكلفة النتيجة، عدد النتائج، العائد).' : 'Updated the targets (cost per result, results, ROAS).';
        }
        if (AuditLog::where('action', 'client.paused')->whereBetween('created_at', [$start, $end])->where('meta->client_id', $c->id)->exists()) {
            $done[] = $ar ? 'أوقفنا الحملات مؤقتًا بناءً على الاتفاق.' : 'Paused the work as agreed.';
        }
        $cod = CodWeek::where('client_id', $c->id)->whereBetween('week_start', [$start->toDateString(), $end->toDateString()])->sum('delivered');
        if ($cod > 0) {
            $done[] = $ar ? "تابعنا الطلبات المسلّمة فعلًا لحساب تكلفة الطلب المسلّم (الطلبات المسلّمة: {$cod})." : "Tracked delivered orders ({$cod}) to report the cost per delivered order.";
        }

        $next = [];
        $nStart = $start->copy()->addMonthNoOverflow()->startOfMonth();
        foreach (array_slice(MenaCalendar::events($nStart->toDateString(), $nStart->copy()->endOfMonth()->toDateString()), 0, 3) as $e) {
            $next[] = $ar ? 'نستعد لموسم '.$e['ar'].'.' : 'Prepare for '.$e['label'].'.';
        }
        $open = Alert::where('client_id', $c->id)->where('status', 'open')->whereIn('severity', ['critical', 'warning', 'high', 'medium'])->count();
        if ($open) {
            $next[] = $ar ? "معالجة التنبيهات المفتوحة (العدد: {$open})." : "Work through {$open} open alerts.";
        }

        $bullet = fn (array $l) => implode("\n", array_map(fn ($x) => '• '.$x, $l));

        return ['done' => $bullet($done), 'next' => $bullet($next)];
    }
}
