<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ClientApproval;
use App\Models\ClientLink;
use App\Support\DataExport;
use App\Support\DataGaps;
use App\Support\MetricsQuery;
use App\Support\MonthlyReport;
use App\Support\Pacing;
use App\Support\Periods;
use App\Support\PlanGate;
use App\Support\TopAds;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** #56: the public, read-only client report behind a signed link. No login; noindex; spend hidden when the client asks. */
class ClientReportController extends Controller
{
    public const PERIODS = ['7d', '30d', 'mtd', 'last_month'];

    public function show(Request $request, string $token)
    {
        $link = ClientLink::findActive($token);
        abort_unless($link, 404);

        $client = $link->client()->with('agency')->first();
        abort_unless($client && $client->status !== 'archived', 404);

        $lang = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : ($client->agency->language === 'en' ? 'en' : 'ar');
        app()->setLocale($lang);

        $period = in_array($request->query('p'), self::PERIODS, true) ? $request->query('p') : '30d';
        $tz = $client->agency->timezone ?: 'UTC';
        [$from, $to] = Periods::range($period, $tz);
        [$pf, $pt] = Periods::previous($from, $to);

        $link->forceFill(['views' => $link->views + 1, 'last_viewed_at' => now()])->saveQuietly();

        return response()->view('client-report.show', [
            'link' => $link, 'client' => $client, 'agency' => $client->agency, 'lang' => $lang,
            'period' => $period, 'periods' => self::PERIODS, 'from' => $from, 'to' => $to,
            't' => MetricsQuery::totals($client, $from, $to),
            'prev' => MetricsQuery::totals($client, $pf, $pt),
            'pace' => $client->monthly_budget ? Pacing::month($client) : null,
            'campaigns' => array_slice(MetricsQuery::breakdown($client, $from, $to, 'campaign'), 0, 10),
            'synced' => MetricsQuery::lastSynced($client),
            'gaps' => DataGaps::forReport($client, $from, $to), // FS-24 / XS-44: days without data, never shown as zero
            'hideSpend' => (bool) $client->hide_spend,
            'pdfMonths' => PlanGate::allows($client->agency, 'monthly_pdf') ? MonthlyReport::months(3, $lang, $tz) : [],
            // XS-06 (R4): what the agency asked this client to approve
            'approvals' => ClientApproval::where('client_id', $client->id)->whereIn('status', ['pending', 'approved', 'changes'])->latest()->limit(10)->get(),
            'token' => $token,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }

    /** XS-06 (R4): the client approves or asks for changes on the report link, with the name they type. */
    public function answer(Request $request, string $token, int $approval)
    {
        $link = ClientLink::findActive($token);
        abort_unless($link, 404);
        $data = $request->validate(['decision' => ['required', 'in:approved,changes'], 'name' => ['required', 'string', 'max:80'], 'note' => ['nullable', 'string', 'max:1000']]);
        $a = ClientApproval::where('client_id', $link->client_id)->where('status', 'pending')->findOrFail($approval);
        $a->update(['status' => $data['decision'], 'answered_by' => trim(strip_tags($data['name'])), 'client_note' => trim(strip_tags((string) ($data['note'] ?? ''))) ?: null, 'answered_at' => now()]);
        AuditLog::record('client.approval_answered', $link->client?->agency, ['approval' => $a->id, 'decision' => $a->status]);

        return redirect('/r/'.$token.'#approvals')->with('approval_saved', true);
    }

    /** #100: the client's ad gallery for the chosen period, through the same signed link. Follows hide_spend. */
    public function ads(Request $request, string $token)
    {
        $link = ClientLink::findActive($token);
        abort_unless($link, 404);
        $client = $link->client()->with('agency')->first();
        abort_unless($client && $client->status !== 'archived', 404);

        $lang = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : ($client->agency->language === 'en' ? 'en' : 'ar');
        app()->setLocale($lang);
        $period = in_array($request->query('p'), self::PERIODS, true) ? $request->query('p') : '30d';
        [$from, $to] = Periods::range($period, $client->agency->timezone ?: 'UTC');

        return response()->view('client-report.ads', [
            'link' => $link, 'client' => $client, 'agency' => $client->agency, 'lang' => $lang,
            'period' => $period, 'periods' => self::PERIODS, 'from' => $from, 'to' => $to,
            'ads' => TopAds::list($client->agency, collect([$client]), $from, $to, null, 'results', 60),
            'hideSpend' => (bool) $client->hide_spend,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }

    /** #57: the monthly PDF for a full month (default: last month), through the same signed link. */
    public function pdf(Request $request, string $token)
    {
        $link = ClientLink::findActive($token);
        abort_unless($link, 404);
        $client = $link->client()->with('agency')->first();
        abort_unless($client && $client->status !== 'archived', 404);
        abort_unless(PlanGate::allows($client->agency, 'monthly_pdf'), 404);

        $lang = in_array($request->query('lang'), ['ar', 'en', 'both'], true) ? $request->query('lang') : ($client->agency->language === 'en' ? 'en' : 'ar'); // XS-22: 'both'
        $month = $request->query('m');
        if (! MonthlyReport::validMonth($month)) {
            $month = now($client->agency->timezone ?: 'UTC')->startOfMonth()->subMonthNoOverflow()->format('Y-m');
        }

        return response(MonthlyReport::pdf($client, $month, $lang), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.MonthlyReport::filename($client, $month, $lang).'"',
            'X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, no-store',
        ]);
    }

    /** #79 client-facing CSV: daily metrics for the chosen period, through the same link. Follows hide_spend. */
    public function csv(Request $request, string $token)
    {
        $link = ClientLink::findActive($token);
        abort_unless($link, 404);
        $client = $link->client()->with('agency')->first();
        abort_unless($client && $client->status !== 'archived', 404);

        $lang = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : ($client->agency->language === 'en' ? 'en' : 'ar');
        app()->setLocale($lang);
        $period = in_array($request->query('p'), self::PERIODS, true) ? $request->query('p') : '30d';
        [$from, $to] = Periods::range($period, $client->agency->timezone ?: 'UTC');

        return response()->streamDownload(function () use ($client, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, DataExport::BOM);
            DataExport::writeMetrics($out, $client->agency, [$client->id], $from, $to, ! $client->hide_spend, false);
            fclose($out);
        }, 'adsranger-'.(Str::slug($client->name) ?: 'client')."-{$from}-{$to}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8', 'X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
