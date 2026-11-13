<?php

namespace App\Support;

use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

/**
 * #57 monthly client report: one calendar month, Arabic or English, as a PDF (mPDF shapes Arabic and lays out RTL).
 * Same figures as the client link (#56): results by goal vs the month before, spend + cost (unless hide_spend),
 * budget and pace, campaigns.
 */
class MonthlyReport
{
    /** The last N full months as ['2026-10' => 'October 2026'], newest first. */
    public static function months(int $n = 6, string $locale = 'en', string $tz = 'UTC'): array
    {
        $out = [];
        $m = now($tz)->startOfMonth();
        for ($i = 1; $i <= $n; $i++) {
            $d = $m->copy()->subMonthsNoOverflow($i);
            $out[$d->format('Y-m')] = $d->locale($locale)->translatedFormat('F Y');
        }

        return $out;
    }

    public static function validMonth(?string $month): bool
    {
        return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1
            && Carbon::createFromFormat('Y-m-d', $month.'-01')->lt(now()->startOfMonth()->addMonth());
    }

    public static function data(Client $client, string $month, string $lang): array
    {
        $client->loadMissing('agency');
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $from = $start->toDateString();
        $to = $start->copy()->endOfMonth()->toDateString();
        $prevStart = $start->copy()->subMonthNoOverflow();

        return [
            'client' => $client, 'agency' => $client->agency, 'lang' => $lang,
            'monthLabel' => $start->copy()->locale($lang)->translatedFormat('F Y'),
            'prevLabel' => $prevStart->copy()->locale($lang)->translatedFormat('F Y'),
            't' => MetricsQuery::totals($client, $from, $to),
            'prev' => MetricsQuery::totals($client, $prevStart->toDateString(), $prevStart->copy()->endOfMonth()->toDateString()),
            'budget' => $client->monthly_budget ?: null,
            'campaigns' => array_slice(MetricsQuery::breakdown($client, $from, $to, 'campaign'), 0, 15),
            'hideSpend' => (bool) $client->hide_spend,
            'notes' => MonthNotes::get($client, $month), // XS-03 / FS-20 (R4)
            'generated' => now($client->agency->timezone ?: 'UTC')->locale($lang)->translatedFormat('j F Y'),
        ];
    }

    public static function filename(Client $client, string $month, string $lang): string
    {
        $slug = Str::slug($client->name) ?: 'client';

        return "adsranger-{$slug}-{$month}-{$lang}.pdf";
    }

    /** PDF bytes. */
    public static function pdf(Client $client, string $month, string $lang): string
    {
        $previous = app()->getLocale();
        app()->setLocale($lang);
        try {
            $html = view('reports.monthly-pdf', static::data($client, $month, $lang))->render();
        } finally {
            app()->setLocale($previous);
        }

        $tmp = storage_path('app/mpdf');
        if (! is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }
        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'tempDir' => $tmp,
            'margin_left' => 14, 'margin_right' => 14, 'margin_top' => 16, 'margin_bottom' => 16, 'margin_header' => 6,
            'default_font' => 'dejavusans', 'autoScriptToLang' => true, 'autoLangToFont' => true,
            'directionality' => $lang === 'ar' ? 'rtl' : 'ltr',
        ]);
        $mpdf->SetTitle((DemoData::isSample($client) ? DemoData::LABEL.' · ' : '').__('report.pdf_title', ['client' => $client->name], $lang));
        if (DemoData::isSample($client)) { // PS-06: sample numbers can never pass as real
            $mpdf->SetWatermarkText(DemoData::LABEL, 0.08);
            $mpdf->showWatermarkText = true;
        }
        $mpdf->SetAuthor($client->agency->name);
        $mpdf->SetCreator('AdsRanger');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
