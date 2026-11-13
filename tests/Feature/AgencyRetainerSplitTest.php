<?php

namespace Tests\Feature;

use App\Filament\Pages\RetainerSplitPage;
use App\Models\Agency;
use App\Models\AgencyBillingProfile;
use App\Models\Client;
use App\Models\ClientRetainerTerm;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\AgencyOps\RetainerSplit;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** E2-067 retainer vs ad-spend split + CSV / ZATCA TLV / JoFotara UBL drafts (module agency_ops_hub, off). */
class AgencyRetainerSplitTest extends TestCase
{
    use RefreshDatabase;

    private const BANNED = ['compliant', 'compliance', 'certified', 'approved', 'tax-approved', 'متوافق', 'معتمد', 'موافق عليه'];

    /** @return array{0: Agency, 1: User, 2: Client} */
    private function workspace(string $slug, string $currency, string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $agency = Agency::create(['name' => 'Agency '.$slug, 'slug' => $slug, 'plan' => 'agency', 'status' => 'active', 'signup_source' => 'admin', 'owner_id' => $user->id, 'language' => 'ar', 'currency' => $currency, 'country' => 'JO']);
        DB::table('agency_user')->where('user_id', $user->id)->delete();
        $agency->members()->attach($user->id, ['role' => $role]);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Client '.$slug, 'currency' => $currency, 'status' => 'active']);

        return [$agency, $user, $client];
    }

    private function spend(Client $client, string $date, float $spend): void
    {
        DailyMetric::create(['agency_id' => $client->agency_id, 'client_id' => $client->id, 'date' => $date, 'network' => 'meta', 'currency' => $client->currency, 'spend' => $spend, 'impressions' => 1000, 'clicks' => 10]);
    }

    private function terms(Client $client, array $t): ClientRetainerTerm
    {
        return ClientRetainerTerm::create($t + ['agency_id' => $client->agency_id, 'client_id' => $client->id, 'currency' => $client->currency, 'fixed_amount' => 0, 'spend_percent' => 0, 'min_fee' => 0]);
    }

    public function test_fee_models_minimum_and_tax_on_the_fee_only_with_currency_decimals(): void
    {
        [, , $jod] = $this->workspace('split-jod', 'JOD');
        $this->spend($jod, '2026-10-05', 1000.1234);
        $this->spend($jod, '2026-10-20', 500);
        $this->spend($jod, '2026-11-02', 9999); // other month
        $s = RetainerSplit::compute($jod, $this->terms($jod, ['fee_model' => 'hybrid', 'fixed_amount' => 200, 'spend_percent' => 10, 'min_fee' => 300, 'tax_rate_percent' => 16]), '2026-10');
        $this->assertSame([1500.123, 150.012, 350.012, false, 56.002, 1906.137], [$s['spend'], $s['percent_fee'], $s['fee'], $s['min_applied'], $s['tax'], $s['total']]);
        $this->assertSame('1500.123', RetainerSplit::amount($s['spend'], 'JOD'));

        [, , $sar] = $this->workspace('split-sar', 'SAR');
        $this->spend($sar, '2026-10-05', 1000);
        $p = RetainerSplit::compute($sar, $this->terms($sar, ['fee_model' => 'percent_spend', 'spend_percent' => 12.5, 'min_fee' => 500]), '2026-10');
        $this->assertSame([125.0, 500.0, true, 0.0, 1500.0], [$p['percent_fee'], $p['fee'], $p['min_applied'], $p['tax'], $p['total']]);
        $this->assertSame('1500.00', RetainerSplit::amount($p['total'], 'SAR'));
        foreach (['KWD' => 3, 'BHD' => 3, 'OMR' => 3, 'JOD' => 3, 'SAR' => 2, 'AED' => 2, 'USD' => 2] as $cur => $dec) {
            $this->assertSame($dec, RetainerSplit::decimals($cur), $cur);
        }
        $fixed = RetainerSplit::compute($sar, ClientRetainerTerm::make(['fee_model' => 'fixed', 'fixed_amount' => 2000, 'spend_percent' => 50, 'min_fee' => 0, 'currency' => 'SAR']), '2026-10');
        $this->assertSame(2000.0, $fixed['fee'], 'the share is ignored on a fixed fee');
    }

    public function test_exports_are_neutral_drafts_with_a_valid_tlv_and_ubl_structure(): void
    {
        [$agency, , $client] = $this->workspace('split-export', 'SAR');
        $this->spend($client, '2026-10-05', 1000);
        $terms = $this->terms($client, ['fee_model' => 'fixed', 'fixed_amount' => 2000, 'tax_rate_percent' => 15]);
        $profile = AgencyBillingProfile::create(['agency_id' => $agency->id, 'legal_name' => 'وكالة الأفق للتسويق', 'tax_identifier' => '310122393500003', 'default_currency' => 'SAR', 'country_code' => 'SA']);
        $split = RetainerSplit::compute($client, $terms, '2026-10');

        $tlv = RetainerSplit::zatcaTlv($profile, $split, Carbon::parse('2026-11-01 09:30:00', 'Asia/Riyadh'));
        $this->assertSame([1 => 'وكالة الأفق للتسويق', 2 => '310122393500003', 3 => '2026-11-01T06:30:00Z', 4 => '3300.00', 5 => '300.00'], RetainerSplit::decodeTlv($tlv));

        $xml = RetainerSplit::ublXml($profile, $client, $split, 'DRAFT-1', Carbon::parse('2026-11-01'));
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $doc->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $doc->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $this->assertSame('3300.00', (string) $doc->xpath('//cac:LegalMonetaryTotal/cbc:PayableAmount')[0]);
        $this->assertSame('300.00', (string) $doc->xpath('/*/cac:TaxTotal/cbc:TaxAmount')[0]);
        $this->assertCount(2, $doc->xpath('//cac:InvoiceLine'));
        $this->assertSame('15.00', (string) $doc->xpath('//cac:InvoiceLine[1]//cac:ClassifiedTaxCategory/cbc:Percent')[0]);
        $this->assertEmpty($doc->xpath('//cac:InvoiceLine[2]//cac:ClassifiedTaxCategory'), 'pass-through spend gets no invented tax category');

        $csv = RetainerSplit::csv($client, $split, true);
        $this->assertStringContainsString('3300.00', $csv);
        foreach ([$xml, $csv, base64_decode($tlv)] as $out) {
            foreach (self::BANNED as $word) {
                $this->assertStringNotContainsStringIgnoringCase($word, $out);
            }
        }
        $this->assertStringContainsString(RetainerSplit::REMINDER_EN, $xml);
        $this->assertStringContainsString(RetainerSplit::REMINDER_AR, $csv);
        $this->assertStringNotContainsStringIgnoringCase('AdsRanger', $xml.$csv);
    }

    public function test_screen_is_held_and_hide_spend_blocks_exports_and_analysts_only_view(): void
    {
        [$agency, $owner, $client] = $this->workspace('split-ui', 'KWD');
        $this->assertContains($this->actingAs($owner)->get("/app/{$agency->slug}/retainer-split")->status(), [403, 404]);

        config(['modules.preview_agencies' => ['split-ui']]);
        $this->spend($client, now()->subMonthNoOverflow()->startOfMonth()->addDays(3)->toDateString(), 1234.5);
        $this->actingAs($owner);
        Filament::setTenant($agency);
        Livewire::test(RetainerSplitPage::class)
            ->set('legalName', 'Horizon Agency')->set('taxId', 'bad id!')->call('saveProfile')->assertHasErrors(['taxId'])
            ->set('taxId', '12345678')->call('saveProfile')->assertHasNoErrors()
            ->set('feeModel', 'percent_spend')->set('spendPercent', 10)->set('minFee', 50)->call('saveTerms')->assertHasNoErrors()
            ->call('downloadCsv')->assertFileDownloaded()
            ->call('downloadUbl')->assertFileDownloaded()
            ->call('downloadZatcaTlv')->assertFileDownloaded();
        $this->assertSame('KWD', ClientRetainerTerm::sole()->currency);

        $html = $this->get("/app/{$agency->slug}/retainer-split?lang=ar")->assertOk()
            ->assertSee('1,234.500 KWD')->assertSee('123.450 KWD')->assertSee(RetainerSplit::REMINDER_AR)->getContent();
        foreach (self::BANNED as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, strip_tags($html));
        }

        $client->update(['hide_spend' => true]);
        Livewire::test(RetainerSplitPage::class)->call('downloadCsv')->assertNoFileDownloaded()->call('downloadUbl')->assertNoFileDownloaded()
            ->assertSee('الإنفاق مخفي عن هذا العميل');

        $client->update(['hide_spend' => false]);
        $analyst = User::factory()->create();
        DB::table('agency_user')->where('user_id', $analyst->id)->delete();
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);
        $this->actingAs($analyst);
        Livewire::test(RetainerSplitPage::class)->set('fixedAmount', 9)->set('feeModel', 'fixed')->call('saveTerms')->call('downloadCsv')->assertNoFileDownloaded()->call('saveProfile');
        $this->assertSame('percent_spend', ClientRetainerTerm::sole()->fee_model);
        $this->assertSame('Horizon Agency', AgencyBillingProfile::sole()->legal_name);
    }
}
