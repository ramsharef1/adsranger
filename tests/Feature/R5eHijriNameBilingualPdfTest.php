<?php

namespace Tests\Feature;

use App\Filament\Pages\UtmBuilderPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\User;
use App\Support\CampaignName;
use App\Support\MenaCalendar;
use App\Support\MonthlyReport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R5 end: XS-21 Hijri month token in campaign names, XS-22 Arabic + English monthly PDF. */
class R5eHijriNameBilingualPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_hijri_token_and_campaign_name(): void
    {
        $this->assertSame([1448, 9], MenaCalendar::hijriYearMonth('2027-02-15'), 'mid Ramadan 1448');
        $this->assertSame('RMD-1448', CampaignName::hijriToken('2027-02-15'));
        $this->assertSame('SHW-1448', CampaignName::hijriToken('2027-03-20'));
        $this->assertSame('ZAD_LEADS_KSA_202702_RMD-1448_IFTAR-OFFER', CampaignName::build(['client' => 'Zad', 'objective' => 'LEADS', 'market' => 'ksa', 'extra' => 'iftar offer', 'date' => '2027-02-15', 'gregorian' => true, 'hijri' => true]));
        $this->assertSame('ZAD|SALES', CampaignName::build(['client' => 'زاد', 'objective' => 'SALES', 'sep' => '|', 'hijri' => false]));
    }

    public function test_builder_fills_utm_campaign(): void
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r5e', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(UtmBuilderPage::class)->set('name.client', 'Zad')->set('name.date', '2027-02-15')
            ->assertSee('ZAD_LEADS_RMD-1448')->call('useName')->assertSet('campaign', 'zad_leads_rmd-1448');
    }

    public function test_bilingual_pdf_has_both_languages(): void
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r5e2', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh', 'language' => 'ar']);
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Zad', 'currency' => 'SAR', 'status' => 'active']);
        app()->setLocale('ar');
        $html = view('reports.monthly-pdf', MonthlyReport::data($c, '2026-10', 'both'))->render();
        $this->assertStringContainsString(__('report.no_data', [], 'ar'), $html);
        $this->assertStringContainsString(__('report.no_data', [], 'en'), $html);
        $this->assertStringContainsString('October 2026', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html, 'no double escaping');

        $en = view('reports.monthly-pdf', MonthlyReport::data($c, '2026-10', 'en'))->render();
        $this->assertStringNotContainsString(__('report.no_data', [], 'ar'), $en);

        $link = ClientLink::issue($c);
        $this->get('/r/'.$link->token.'/pdf?m=2026-10&lang=both')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/r/'.$link->token.'?lang=en')->assertOk()->assertSee('lang=both', false);
    }
}
