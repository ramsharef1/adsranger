<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\User;
use App\Support\DemoData;
use App\Support\Import\ImportSheet;
use App\Support\MetricsImporter;
use App\Support\Onboarding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/** R3 part 3 (D-290, D-295, D-296): PS-05 sample data in place, PS-06 sample-data mark, DB-04 Excel export, PS-04 first-report steps. */
class R3cSampleExportTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create(['name' => 'Souq Ads', 'slug' => 'souq', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'currency' => 'SAR', 'language' => 'en']);
        $this->owner = User::factory()->create();
        $this->agency->members()->attach($this->owner->id, ['role' => 'owner']);
    }

    public function test_empty_workspace_offers_sample_data_and_clears_it_in_one_click(): void
    {
        $this->withSession(['locale' => 'en'])->actingAs($this->owner)->get('/app/souq')->assertSee('data-sample-data', false)->assertSee('Show sample data here');

        $this->post('/app/souq/sample-data/fill')->assertRedirect();
        $this->assertTrue(DemoData::present($this->agency));
        $demo = Client::where('agency_id', $this->agency->id)->firstOrFail();
        $this->assertTrue(DemoData::isSample($demo));
        $this->get('/app/souq')->assertSee('Clear sample data');

        $this->post('/app/souq/sample-data/clear')->assertRedirect();
        $this->assertFalse(DemoData::present($this->agency));
        $this->assertSame(0, Client::where('agency_id', $this->agency->id)->count());
    }

    public function test_only_owner_and_admins_can_fill_sample_data(): void
    {
        $analyst = User::factory()->create();
        $this->agency->members()->attach($analyst->id, ['role' => 'analyst']);
        $this->actingAs($analyst)->post('/app/souq/sample-data/fill')->assertForbidden();
        $this->assertFalse(DemoData::present($this->agency));
    }

    public function test_files_made_from_sample_data_are_marked(): void
    {
        DemoData::seed($this->agency, 5);
        $demo = Client::where('agency_id', $this->agency->id)->orderBy('id')->firstOrFail();
        $this->actingAs($this->owner);

        $r = $this->get('/app/souq/export/daily-metrics.csv?client='.$demo->id.'&from='.now()->subDays(6)->toDateString().'&to='.now()->subDay()->toDateString());
        $r->assertOk();
        $this->assertStringContainsString('sample-data', (string) $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString(DemoData::LABEL, $r->streamedContent());

        $link = ClientLink::issue($demo);
        $this->get('/r/'.$link->token.'?lang=en')->assertOk()->assertSee('data-sample-banner', false)->assertSee('Sample data: these numbers are not real');
    }

    public function test_daily_metrics_excel_is_right_to_left_in_arabic_with_numbers_as_numbers(): void
    {
        $c = Client::create(['agency_id' => $this->agency->id, 'name' => 'عيادة النور', 'status' => 'active']);
        MetricsImporter::importRows($this->agency, $this->owner, 'meta', ImportSheet::fromCsv("Day,Campaign,Spend,Impressions\n2026-11-01,Noor,100.5,5000\n"), 'm.csv', 'file', $c->id);

        $r = $this->actingAs($this->owner)->withSession(['locale' => 'ar'])
            ->get('/app/souq/export/daily-metrics.xlsx?client='.$c->id.'&from=2026-11-01&to=2026-11-01');
        $r->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $r->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($path, $r->streamedContent());
        $zip = new \ZipArchive;
        $zip->open($path);
        $this->assertMatchesRegularExpression('/rightToLeft="(1|true)"/', (string) $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();

        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }
        $reader->close();
        @unlink($path);
        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertTrue(collect($rows[1])->contains(fn ($v) => is_float($v) && abs($v - 100.5) < 0.001), 'spend is a number, not text');
    }

    public function test_first_report_steps_appear_after_an_upload(): void
    {
        $this->agency->forceFill(['onboarding_required' => true, 'onboarding_completed_at' => now()])->save();
        $this->assertNotContains(8, array_column(Onboarding::checklist($this->agency, $this->owner)['items'] ?? [], 'step'));

        MetricsImporter::importRows($this->agency, $this->owner, 'google_ads', ImportSheet::fromCsv("Day,Campaign,Cost\n2026-11-01,X,10\n"), 'g.csv');
        $steps = collect(Onboarding::checklist($this->agency->fresh(), $this->owner)['items'])->keyBy('step');
        $this->assertFalse($steps[8]['done'], 'one campaign still to sort');
        $this->assertStringEndsWith('/import-review', $steps[8]['url']);
        $this->assertFalse($steps[9]['done']);
    }
}
