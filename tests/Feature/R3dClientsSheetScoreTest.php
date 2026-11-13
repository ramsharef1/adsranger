<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\Plan;
use App\Models\User;
use App\Support\ClientSetupScore;
use App\Support\Import\ClientSheet;
use App\Support\Import\ImportSheet;
use App\Support\MetricsImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** R3 end (D-297): WS-03 / H-14 client import from a sheet, EH-30 setup score, WS-19 hint, Growth O-438 /data fixes. */
class R3dClientsSheetScoreTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create(['name' => 'Souq Ads', 'slug' => 'souq', 'plan' => 'starter', 'status' => 'active', 'timezone' => 'Asia/Amman', 'currency' => 'SAR', 'language' => 'en']);
        $this->owner = User::factory()->create();
        $this->agency->members()->attach($this->owner->id, ['role' => 'owner']);
    }

    public function test_arabic_sheet_creates_clients_skips_existing_and_respects_the_limit(): void
    {
        Client::create(['agency_id' => $this->agency->id, 'name' => 'عيادة النور', 'status' => 'active']);
        $csv = "اسم العميل,الميزانية الشهرية,تكلفة النتيجة المستهدفة,العملة,الدولة,القطاع\n"
            ."عيادة النور,3000,12,SAR,SA,clinic\n"
            ."\u{200F}متجر الريان,٥٠٠٠,,AED,AE,ecommerce\n"
            ."Hope Charity,,,usd,jo,charity\n";
        $rows = ImportSheet::fromCsv($csv);

        $p = ClientSheet::parse($this->agency, $rows);
        $this->assertSame(['متجر الريان', 'Hope Charity'], array_column($p['clients'], 'name'));
        $this->assertSame(['عيادة النور'], $p['skipped']);
        $this->assertSame(5000.0, $p['clients'][0]['monthly_budget'], 'Eastern digits read as Western');
        $this->assertSame(['AED', 'AE', 'ecommerce'], [$p['clients'][0]['currency'], $p['clients'][0]['market'], $p['clients'][0]['vertical']]);
        $this->assertSame(['USD', 'JO'], [$p['clients'][1]['currency'], $p['clients'][1]['market']]);

        $r = ClientSheet::import($this->agency, $rows);
        $this->assertSame(['created' => 2, 'skipped' => 1, 'over_limit' => 0], $r);
        $this->assertSame(3, Client::where('agency_id', $this->agency->id)->count());
    }

    public function test_plan_limit_stops_extra_clients(): void
    {
        Plan::updateOrCreate(['key' => 'starter'], ['name' => 'Starter', 'clients' => 2, 'seats' => 3, 'ad_accounts' => 10]);
        $rows = ImportSheet::fromCsv("Client name\nA\nB\nC\n");
        $r = ClientSheet::import($this->agency->fresh(), $rows);
        $this->assertSame([2, 1], [$r['created'], $r['over_limit']]);
    }

    public function test_template_downloads_and_round_trips(): void
    {
        $r = $this->withSession(['locale' => 'ar'])->actingAs($this->owner)->get('/app/souq/clients-template.csv')->assertOk();
        $rows = ImportSheet::fromCsv($r->streamedContent());
        $this->assertSame('اسم العميل', $rows[0][0]);
        $this->assertSame(['عيادة النور'], array_column(ClientSheet::parse($this->agency, $rows)['clients'], 'name'));
    }

    public function test_setup_score_counts_the_steps_done(): void
    {
        $c = Client::create(['agency_id' => $this->agency->id, 'name' => 'Noor', 'status' => 'active']);
        $this->assertSame(0, ClientSetupScore::agency($this->agency)[$c->id]['score']);

        $c->forceFill(['target_cpr' => 10])->save();
        MetricsImporter::importRows($this->agency, $this->owner, 'meta', ImportSheet::fromCsv("Day,Campaign,Spend\n2026-11-01,X,5\n"), 'm.csv', 'file', $c->id);
        ClientLink::issue($c);
        $s = ClientSetupScore::agency($this->agency)[$c->id];
        $this->assertSame(60, $s['score']);
        $this->assertCount(2, $s['missing']);

    }

    public function test_data_page_and_launch_list_wording(): void
    {
        $this->get('/data')->assertOk()->assertSee('بيانات مفتوحة للإعلانات في المنطقة', false)->assertSee('تقويم المواسم 2027')->assertDontSee('لتسويق المنطقة');
        $this->get('/en/data')->assertOk()->assertSee('Open MENA Ad Data: 2027 Calendar in CSV, JSON');
        $this->get('/waitlist')->assertOk()->assertSee('انضم إلى قائمة إطلاق', false);
        $this->get('/pricing')->assertOk()->assertSee('انضم إلى قائمة الإطلاق')->assertDontSee('انضم إلى قائمة الانتظار');
    }
}
