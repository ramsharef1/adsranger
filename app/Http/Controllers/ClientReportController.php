<?php

namespace App\Http\Controllers;

use App\Models\ClientLink;
use App\Support\MetricsQuery;
use App\Support\Pacing;
use App\Support\Periods;
use Illuminate\Http\Request;

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
            'hideSpend' => (bool) $client->hide_spend,
        ])->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'private, no-store');
    }
}
