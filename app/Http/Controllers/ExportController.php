<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\DataExport;
use App\Support\DemoData;
use App\Support\Import\ClientSheet;
use App\Support\TwoPerson;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * #79 CSV downloads inside /app/{agency}/export/… (Filament tenant routes: signed in, member of the workspace).
 * Every role exports what it can see (EX-49): owners and admins the whole workspace, client-restricted members
 * only their clients. The full workspace ZIP is for the owner. Every download is audit-logged as `export.csv`.
 */
class ExportController extends Controller
{
    public const MAX_DAYS = 800;

    protected function agency(): Agency
    {
        $a = Filament::getTenant();
        abort_unless($a instanceof Agency, 404);

        return $a;
    }

    /** [from, to] from the query, default the last 30 days to yesterday (workspace time). */
    public static function period(Request $r, Agency $a): array
    {
        $tz = $a->timezone ?: 'UTC';
        $valid = fn ($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
        $to = $valid($r->query('to')) ? CarbonImmutable::parse($r->query('to')) : CarbonImmutable::now($tz)->subDay();
        $from = $valid($r->query('from')) ? CarbonImmutable::parse($r->query('from')) : $to->subDays(29);
        abort_if($from->gt($to) || $from->diffInDays($to) > self::MAX_DAYS, 422, __('Choose a period of up to :days days.', ['days' => self::MAX_DAYS]));

        return [$from->toDateString(), $to->toDateString()];
    }

    protected function csv(string $filename, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write) {
            $out = fopen('php://output', 'w');
            fwrite($out, DataExport::BOM);
            $write($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    protected static function slug(string $s): string
    {
        return Str::slug($s) ?: 'export';
    }

    public function metrics(Request $request)
    {
        $agency = $this->agency();
        $user = $request->user();
        [$from, $to] = static::period($request, $agency);
        $ids = DataExport::visibleClientIds($user, $agency);
        $client = null;
        $withSpend = true;

        if ($request->filled('client')) {
            $client = Client::visibleTo($user, $agency)->whereKey((int) $request->query('client'))->first();
            abort_unless($client, 404);
            $ids = [$client->id];
            // A copy meant for the client follows hide_spend (EX-18): spend and cost per result are left out.
            $withSpend = ! ($request->boolean('for_client') && $client->hide_spend);
        }

        AuditLog::record('export.csv', $client ?? $agency, ['kind' => 'daily_metrics', 'from' => $from, 'to' => $to,
            'client_id' => $client?->id, 'for_client' => $request->boolean('for_client'), 'spend' => $withSpend]);

        // PS-06: a file holding sample data says so in its name and on its first line.
        $sample = collect($ids)->contains(fn ($id) => DemoData::isSample(Client::find($id)));
        $name = 'adsranger-'.($sample ? 'sample-data-' : '').static::slug($client?->name ?? $agency->name)."-daily-{$from}-{$to}.csv";

        return $this->csv($name, function ($out) use ($agency, $ids, $from, $to, $withSpend, $client, $sample) {
            if ($sample) {
                fputcsv($out, [DemoData::LABEL], ',', '"', '');
            }
            DataExport::writeMetrics($out, $agency, $ids, $from, $to, $withSpend, $client === null);
        });
    }

    /**
     * DB-04 (D-295): the same daily rows as an .xlsx — right-to-left sheet in Arabic, bold frozen header, numbers as numbers,
     * the sample-data mark on top when it applies (PS-06).
     */
    public function metricsXlsx(Request $request)
    {
        $csv = $this->metrics($request);
        ob_start();
        $csv->sendContent();
        $text = (string) ob_get_clean();
        $rows = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), array_values(array_filter(explode("\n", str_replace(["\r\n", "\r"], "\n", ltrim($text, DataExport::BOM))), fn ($l) => $l !== '')));
        $name = preg_replace('/\.csv$/', '.xlsx', (string) $csv->headers->get('Content-Disposition'));
        $file = preg_match('/filename="?([^";]+)"?/', $name, $m) ? $m[1] : 'adsranger-export.xlsx';

        return response()->streamDownload(function () use ($rows) {
            $w = new XlsxWriter;
            $w->openToFile('php://output');
            // The header is the first row with more than one cell (a sample-data mark may sit above it).
            $header = collect($rows)->search(fn ($r) => count($r) > 1);
            $view = (new SheetView)->setRightToLeft(app()->getLocale() === 'ar');
            if ($header !== false) {
                $view->setFreezeRow($header + 2);
            }
            $w->getCurrentSheet()->setSheetView($view); // set before the first row: the sheet view is written first
            foreach ($rows as $i => $r) {
                $isHeader = $i === $header;
                $cells = array_map(fn ($v) => ! $isHeader && is_numeric($v) && ! preg_match('/^0\d/', $v) ? $v + 0 : $v, $r);
                $w->addRow(Row::fromValues($cells, $isHeader ? (new Style)->setFontBold() : null));
            }
            $w->close();
        }, $file, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    /** WS-03: the client sheet template (headers in the panel's language, one example row). */
    public function clientsTemplate()
    {
        return $this->csv('adsranger-clients-template.csv', function ($out) {
            foreach (ClientSheet::template(app()->getLocale() === 'ar') as $row) {
                fputcsv($out, $row, ',', '"', '');
            }
        });
    }

    public function clients(Request $request)
    {
        $agency = $this->agency();
        $ids = DataExport::visibleClientIds($request->user(), $agency);
        AuditLog::record('export.csv', $agency, ['kind' => 'clients']);

        return $this->csv('adsranger-'.static::slug($agency->name).'-clients.csv', fn ($out) => DataExport::writeClients($out, $agency, $ids));
    }

    public function adAccounts(Request $request)
    {
        $agency = $this->agency();
        $ids = DataExport::visibleClientIds($request->user(), $agency);
        AuditLog::record('export.csv', $agency, ['kind' => 'ad_accounts']);

        return $this->csv('adsranger-'.static::slug($agency->name).'-ad-accounts.csv', fn ($out) => DataExport::writeAdAccounts($out, $agency, $ids));
    }

    /** Owner only: every table of the workspace as CSVs in one ZIP (#79 "download my data"). */
    public function workspace(Request $request)
    {
        $agency = $this->agency();
        abort_unless(static::canDownloadWorkspace($request->user(), $agency), 403);
        abort_unless(TwoPerson::consume($agency, 'export_workspace', null, $request->user()), 403, __('This needs approval from another owner or admin first.')); // DS-35
        AuditLog::record('export.csv', $agency, ['kind' => 'workspace_zip']);
        $path = DataExport::workspaceZip($agency);

        return response()->download($path, 'adsranger-'.static::slug($agency->name).'-workspace-'.now()->format('Y-m-d').'.zip', [
            'Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    public static function canDownloadWorkspace($user, Agency $agency): bool
    {
        return $user && class_exists(\ZipArchive::class) && ($user->roleIn($agency) === 'owner' || $user->isSuperAdmin());
    }
}
