<?php

namespace Tests\Feature;

use App\Filament\Pages\ProfitAttributionHubPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientUnitEconomic;
use App\Models\User;
use App\Support\Attribution\ProfitAndAttributionManager;
use App\Support\CurrentClient;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** cl89: Product's Wave B.2 EX check (UX-255…264). */
class WaveB2FixesCl89Test extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Agency, 1: User, 2: Client} */
    private function workspace(): array
    {
        config(['modules.enabled.intelligence_hub' => true]);
        $owner = User::factory()->create();
        $agency = Agency::create(['name' => 'Agency fx', 'slug' => 'fx-b2', 'plan' => 'agency', 'status' => 'active', 'signup_source' => 'admin', 'owner_id' => $owner->id, 'language' => 'en']);
        DB::table('agency_user')->where('user_id', $owner->id)->delete();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $client = Client::create(['agency_id' => $agency->id, 'name' => 'Jeddah Shop', 'currency' => 'SAR', 'status' => 'active']);

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        CurrentClient::remember($client->id, $agency);

        return [$agency, $owner, $client];
    }

    public function test_hub_shows_no_invented_numbers_until_the_agency_enters_its_own(): void
    {
        [, , $client] = $this->workspace();
        ProfitAndAttributionManager::ensureUnitEconomics($client);

        $lw = Livewire::test(ProfitAttributionHubPage::class)
            ->assertSee('Enter your costs and one period')
            ->assertDontSee('10,000')
            ->assertDontSeeText('simulated')
            ->assertDontSeeText('position_based')
            ->assertSee('Even split')
            ->assertSee('Winner');
        $this->assertNull($lw->get('cogsPct'));
        $this->assertSame([], $lw->get('poasPreview'));

        // Costs without a period still calculate nothing
        $lw->set('cogsPct', 30)->set('shippingFee', 15)->set('returnShippingFee', 12)->set('codSharePct', 50)
            ->set('codReturnRatePct', 20)->set('bnplSharePct', 30)->set('bnplFeePct', 6)->set('gatewayFeePct', 2)
            ->call('previewCodPoas');
        $this->assertSame([], $lw->get('poasPreview'));

        $lw->set('periodRevenue', 20000)->set('periodSpend', 2500)->set('periodOrders', 100)->call('previewCodPoas')
            ->assertSee('Estimated')
            ->assertSee('8,040.00 SAR');
        $this->assertSame(3.22, $lw->get('poasPreview')['poas']);

        $lw->call('saveUnitEconomics');
        $this->assertNotNull(ClientUnitEconomic::where('client_id', $client->id)->value('costs_entered_at'));
    }

    public function test_formula_needing_data_we_do_not_have_says_so_instead_of_using_zero(): void
    {
        $this->workspace();

        $lw = Livewire::test(ProfitAttributionHubPage::class)
            ->set('periodRevenue', 9000)->set('periodSpend', 1500)->set('periodOrders', 90)
            ->set('kpiFormula', 'clicks / impressions')
            ->call('previewFormula');
        $this->assertNull($lw->get('formulaPreviewValue'));
        $this->assertStringContainsString('clicks, impressions', (string) $lw->get('formulaPreviewError'));

        $lw->set('kpiFormula', 'revenue / spend')->call('previewFormula');
        $this->assertSame(6.0, $lw->get('formulaPreviewValue'));
    }

    public function test_survey_offers_something_else_and_says_who_collects_the_answer(): void
    {
        [, , $client] = $this->workspace();
        $survey = ProfitAndAttributionManager::ensureSurvey($client);

        $this->get('/survey/'.$survey->survey_token.'?lang=en')->assertOk()
            ->assertSee('value="other"', false)
            ->assertSee('Jeddah Shop</bdi> collects this answer', false)
            ->assertSee('Your answer helps us know where to advertise.')
            ->assertDontSee('AdsRanger');
    }
}
