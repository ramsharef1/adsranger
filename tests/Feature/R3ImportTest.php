<?php

namespace Tests\Feature;

use App\Filament\Resources\DataImports\Pages\ListDataImports;
use App\Models\Agency;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\Import\ImportSheet;
use App\Support\MetricsImporter;
use App\Support\TextClean;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

/** R3 (D-290, D-295): PS-01 smart import (network guess, title rows, Arabic Ads Manager, xlsx), PS-07 re-upload diff, PS-24 / PS-26. */
class R3ImportTest extends TestCase
{
    use RefreshDatabase;

    private function setUpWorkspace(): array
    {
        $agency = Agency::create(['name' => 'Souq Ads', 'slug' => 'souq-'.uniqid(), 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'currency' => 'SAR']);
        $user = User::factory()->create();
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Noor Clinic', 'status' => 'active']);

        return [$agency, $user, $client];
    }

    private function arabicMetaCsv(string $spendDay2 = '٢٠٠٫٥٠'): string
    {
        // Arabic Ads Manager export: Arabic headers, Eastern digits, an RLM inside the campaign name.
        return "اسم الحملة,بدء إعداد التقارير,المبلغ الذي تم إنفاقه (SAR),مرات الظهور,النتائج,مؤشر النتائج\n"
            ."\u{200F}عيادة النور - رمضان,٢٠٢٦-١١-٠١,١٠٠,٥٠٠٠,٤,actions:lead\n"
            ."عيادة النور - رمضان,2026-11-02,$spendDay2,6000,5,actions:lead\n";
    }

    public function test_arabic_meta_export_is_recognised_cleaned_and_saved_in_western_digits(): void
    {
        [$agency, $user, $client] = $this->setUpWorkspace();
        $rows = ImportSheet::fromCsv($this->arabicMetaCsv());

        $p = MetricsImporter::preview($rows);
        $this->assertSame('meta', $p['network']);
        $this->assertSame(2, $p['rows']);
        $this->assertSame('عيادة النور - رمضان', $p['sample'][0]['campaign']);
        $this->assertSame(100.0, $p['sample'][0]['spend']);

        $import = MetricsImporter::importRows($agency, $user, null, $rows, 'meta-ar.csv', 'file', $client->id);
        $this->assertSame('meta', $import->network);
        $this->assertSame(2, $import->rows);
        $this->assertSame(1, $import->names_cleaned);
        $m = DailyMetric::where('data_import_id', $import->id)->orderBy('date')->get();
        $this->assertSame(['2026-11-01', '2026-11-02'], $m->map(fn ($r) => substr((string) $r->getAttributes()['date'], 0, 10))->all());
        $this->assertEqualsWithDelta(200.5, (float) $m[1]->spend, 0.001);
        $this->assertSame('عيادة النور - رمضان', $m[0]->campaign_name);
    }

    public function test_google_export_with_title_lines_finds_its_header_and_network(): void
    {
        [$agency, $user, $client] = $this->setUpWorkspace();
        $csv = "Campaign report\n\"November 1, 2026 - November 2, 2026\"\nDay,Campaign,Cost,Impr.,Conv.,Avg. CPC\n2026-11-01,NOOR_LEADS_KSA,\"1,250.00\",9000,12,1.10\nTotal,,1250.00,9000,12,\n";
        $rows = ImportSheet::fromCsv($csv);

        $this->assertSame(2, MetricsImporter::headerRow($rows));
        $import = MetricsImporter::importRows($agency, $user, null, $rows, 'google.csv', 'file', $client->id);
        $this->assertSame('google_ads', $import->network);
        $this->assertSame(1, $import->rows, 'the totals line has no date and is skipped');
        $this->assertEqualsWithDelta(1250.0, (float) DailyMetric::where('data_import_id', $import->id)->value('spend'), 0.001);
    }

    public function test_reupload_adds_only_new_days_and_updates_changed_rows(): void
    {
        [$agency, $user, $client] = $this->setUpWorkspace();
        MetricsImporter::importRows($agency, $user, null, ImportSheet::fromCsv($this->arabicMetaCsv()), 'a.csv', 'file', $client->id);

        $same = MetricsImporter::importRows($agency, $user, null, ImportSheet::fromCsv($this->arabicMetaCsv()), 'a.csv', 'file', $client->id);
        $this->assertSame([0, 0, 2], [$same->rows, $same->rows_updated, $same->rows_skipped]);

        $next = $this->arabicMetaCsv('250')."عيادة النور - رمضان,2026-11-03,90,4000,3,actions:lead\n";
        $diff = MetricsImporter::importRows($agency, $user, null, ImportSheet::fromCsv($next), 'b.csv', 'file', $client->id);
        $this->assertSame([1, 1, 1], [$diff->rows, $diff->rows_updated, $diff->rows_skipped]);
        $this->assertSame(3, DailyMetric::where('agency_id', $agency->id)->count());
        $this->assertEqualsWithDelta(440.0, (float) DailyMetric::where('agency_id', $agency->id)->sum('spend'), 0.001);
    }

    public function test_xlsx_file_is_read_with_its_first_sheet(): void
    {
        [$agency, $user, $client] = $this->setUpWorkspace();
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $w = new Writer;
        $w->openToFile($path);
        $w->addRow(Row::fromValues(['By Day', 'Campaign name', 'Cost', 'Impressions', 'Conversions', 'CPC (destination)']));
        $w->addRow(Row::fromValues(['2026-11-05', 'TT Ramadan', 75.5, 12000, 6, 0.4]));
        $w->close();

        $rows = ImportSheet::fromFile($path, 'tiktok.xlsx');
        $import = MetricsImporter::importRows($agency, $user, null, $rows, 'tiktok.xlsx', 'file', $client->id);
        @unlink($path);

        $this->assertSame('tiktok', $import->network);
        $this->assertEqualsWithDelta(75.5, (float) DailyMetric::where('data_import_id', $import->id)->value('spend'), 0.001);
    }

    public function test_import_modal_preview_shows_network_and_rows(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/meta.csv', $this->arabicMetaCsv());
        $html = (string) ListDataImports::previewHtml('imports/meta.csv');
        $this->assertStringContainsString('data-import-preview', $html);
        $this->assertStringContainsString('عيادة النور - رمضان', $html);
        $this->assertStringContainsString('100.00', $html);
    }

    public function test_text_clean_helpers(): void
    {
        $this->assertSame('حملة رمضان', TextClean::invisible("\u{200F}حملـــة\u{200E} رمضان\u{2069}"));
        $this->assertSame('1,234.5', TextClean::western('١٬٢٣٤٫٥'));
        $this->assertFalse(TextClean::hasInvisible('NOOR_LEADS_KSA'));
    }
}
