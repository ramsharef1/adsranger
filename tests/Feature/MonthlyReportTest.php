<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\User;
use App\Support\DemoData;
use App\Support\MonthlyReport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** #57 monthly PDF report. */
class MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private function client(): array
    {
        $a = Agency::create(['name' => 'Acme Agency', 'slug' => 'acme', 'plan' => 'starter', 'status' => 'active']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'analyst']);
        DemoData::seed($a, 70);

        return [$a, $u, Client::where('agency_id', $a->id)->orderBy('id')->first()];
    }

    public function test_pdf_in_both_languages(): void
    {
        [, , $c] = $this->client();
        $month = now()->subMonthNoOverflow()->format('Y-m');
        foreach (['ar', 'en'] as $lang) {
            $pdf = MonthlyReport::pdf($c, $month, $lang);
            $this->assertStringStartsWith('%PDF-', $pdf, $lang);
            $this->assertGreaterThan(5000, strlen($pdf), $lang);
        }
        $html = view('reports.monthly-pdf', MonthlyReport::data($c, $month, 'en'))->render();
        $this->assertStringContainsString($c->name, $html);
        $this->assertStringContainsString('Monthly report', $html);
    }

    public function test_hide_spend_leaves_spend_out(): void
    {
        [, , $c] = $this->client();
        $c->update(['hide_spend' => true]);
        $html = view('reports.monthly-pdf', MonthlyReport::data($c, now()->subMonthNoOverflow()->format('Y-m'), 'en'))->render();
        $this->assertStringNotContainsString('Cost per result', $html);
    }

    public function test_month_validation(): void
    {
        $this->assertTrue(MonthlyReport::validMonth(now()->subMonthNoOverflow()->format('Y-m')));
        $this->assertFalse(MonthlyReport::validMonth('2026-13'));
        $this->assertFalse(MonthlyReport::validMonth('../etc'));
        $this->assertFalse(MonthlyReport::validMonth(now()->addMonths(2)->format('Y-m')));
        $this->assertCount(6, MonthlyReport::months(6));
    }

    public function test_download_through_the_client_link(): void
    {
        [, , $c] = $this->client();
        $link = ClientLink::issue($c);
        $r = $this->get('/r/'.$link->token.'/pdf?lang=en');
        $r->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $r->getContent());
        $this->get('/r/'.$link->token)->assertSee('/r/'.$link->token.'/pdf', false);

        $link->forceFill(['revoked_at' => now()])->save();
        $this->get('/r/'.$link->token.'/pdf')->assertNotFound();
    }

    public function test_download_from_the_client_page(): void
    {
        [$a, $u, $c] = $this->client();
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        Livewire::test(ViewClient::class, ['record' => $c->getRouteKey()])
            ->callAction('monthlyPdf', ['month' => now()->subMonthNoOverflow()->format('Y-m'), 'lang' => 'ar'])
            ->assertFileDownloaded(MonthlyReport::filename($c, now()->subMonthNoOverflow()->format('Y-m'), 'ar'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.monthly_pdf']);
    }
}
