<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ReportSchedule;
use App\Models\ReportSend;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * #106: per-recipient "Stop these emails" behind a signed URL. GET asks (so mail scanners that open links don't
 * unsubscribe anyone); POST to the same signed URL records it. Queued sends to that email are skipped.
 */
class ReportUnsubscribeController extends Controller
{
    protected function context(Request $request, int $schedule): array
    {
        $s = ReportSchedule::with(['client', 'agency'])->find($schedule);
        $email = strtolower((string) $request->query('email'));
        abort_unless($s && $s->client && filter_var($email, FILTER_VALIDATE_EMAIL), 404);
        $lang = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : ($s->lang === 'en' ? 'en' : 'ar');
        app()->setLocale($lang);

        return [$s, $email, $lang];
    }

    public function show(Request $request, int $schedule)
    {
        [$s, $email, $lang] = $this->context($request, $schedule);
        $done = DB::table('report_unsubscribes')->where('report_schedule_id', $s->id)->where('email', $email)->exists();

        return response()->view('reports.unsubscribe', ['s' => $s, 'email' => $email, 'lang' => $lang, 'done' => $done])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, int $schedule)
    {
        [$s, $email, $lang] = $this->context($request, $schedule);
        $inserted = DB::table('report_unsubscribes')->insertOrIgnore(['report_schedule_id' => $s->id, 'email' => $email, 'created_at' => now()]);
        if ($inserted) {
            ReportSend::where('report_schedule_id', $s->id)->where('email', $email)->where('status', 'queued')
                ->update(['status' => 'skipped', 'error' => 'unsubscribed']);
            AuditLog::record('report.unsubscribed', $s->client, ['schedule_id' => $s->id, 'email' => $email]);
        }

        return response()->view('reports.unsubscribe', ['s' => $s, 'email' => $email, 'lang' => $lang, 'done' => true])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}
