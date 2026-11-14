<?php

namespace Tests\Feature;

use App\Filament\Pages\AgencyOpsHubPage;
use App\Models\Agency;
use App\Models\AgencyOperationalAudit;
use App\Models\Client;
use App\Models\InfluencerCampaign;
use App\Models\User;
use App\Support\Shell;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** D-315: the three hub pages get a stylesheet; Agency Ops has no sample numbers, checks roles and validates what is typed. */
class HubPagesDesignTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-hub', 'plan' => 'agency', 'status' => 'active', 'timezone' => 'Asia/Amman', 'language' => 'en']);
        $this->agency->forceFill(['module_overrides' => ['agency_ops_hub' => true, 'intelligence_hub' => true, 'organic' => true]])->save();
        $this->client = Client::create(['agency_id' => $this->agency->id, 'name' => 'Zad Store', 'status' => 'active', 'currency' => 'KWD']);
    }

    private function member(string $role): User
    {
        $u = User::factory()->create();
        DB::table('agency_user')->where('user_id', $u->id)->delete();
        $this->agency->members()->attach($u->id, ['role' => $role]);

        return $u;
    }

    private function page(User $u, string $path): string
    {
        Shell::forget();

        return $this->withSession(['locale' => 'en'])->actingAs($u)->get("/app/{$this->agency->slug}/$path")->assertOk()->getContent();
    }

    public function test_the_three_hub_pages_carry_the_stylesheet_and_no_ticket_numbers(): void
    {
        $owner = $this->member('owner');
        foreach (['agency-ops', 'intelligence-hub', 'organic-social'] as $path) {
            $html = $this->page($owner, $path);
            $this->assertStringContainsString('data-hub-styles', $html, $path);
            $this->assertMatchesRegularExpression('#class="hub [^"]*"#', $html, $path);
            $this->assertDoesNotMatchRegularExpression('/\(#\d+/', strip_tags($html), "$path shows an internal ticket number");
        }
    }

    public function test_organic_page_does_not_invent_a_best_time(): void
    {
        $html = $this->page($this->member('owner'), 'organic-social');
        $this->assertStringContainsString('data-no-best-day', $html);
        $this->assertStringNotContainsString('19:00', $html);
    }

    public function test_agency_ops_has_no_prefilled_numbers_and_a_view_only_note_for_analysts(): void
    {
        $html = $this->page($this->member('analyst'), 'agency-ops');
        $this->assertStringContainsString('data-ops-view-only', $html);
        $this->assertStringNotContainsString('wire:submit="runRunwayAudit"', $html);
        $owner = $this->page($this->member('owner'), 'agency-ops');
        $this->assertStringContainsString('wire:submit="runRunwayAudit"', $owner);
        foreach (['1500', '8500'] as $sample) {
            $this->assertStringNotContainsString($sample, $owner);
        }
    }

    public function test_an_analyst_cannot_record_and_bad_numbers_are_refused(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $this->actingAs($this->member('analyst'));
        Filament::setTenant($this->agency);
        Livewire::test(AgencyOpsHubPage::class)->set('remainingBalance', 900)->set('dailyBurnRate', 300)->call('runRunwayAudit');
        $this->assertSame(0, AgencyOperationalAudit::count());

        $this->actingAs($this->member('manager'));
        Filament::setTenant($this->agency);
        Livewire::test(AgencyOpsHubPage::class)->set('remainingBalance', 900)->set('dailyBurnRate', 0)->call('runRunwayAudit')->assertHasErrors('dailyBurnRate');
        $this->assertSame(0, AgencyOperationalAudit::count());

        Livewire::test(AgencyOpsHubPage::class)->set('remainingBalance', 900)->set('dailyBurnRate', 300)->call('runRunwayAudit')->assertHasNoErrors();
        $audit = AgencyOperationalAudit::sole();
        $this->assertSame('critical_depletion', $audit->status);
        $this->assertSame(['Runs out within 3 days', 'bad'], AgencyOpsHubPage::statusLabel($audit->status));

        Livewire::test(AgencyOpsHubPage::class)->set('influencerName', 'Noura')->set('promoCode', 'bad code!')->set('fixedFee', 500)
            ->set('commissionPercent', 10)->set('ordersCount', 40)->set('attributedRevenue', 6200)->call('recordInfluencer')->assertHasErrors('promoCode');
        Livewire::test(AgencyOpsHubPage::class)->set('influencerName', 'Noura')->set('promoCode', 'noura10')->set('fixedFee', 500)
            ->set('commissionPercent', 10)->set('ordersCount', 40)->set('attributedRevenue', 6200)->call('recordInfluencer')->assertHasNoErrors();
        $ic = InfluencerCampaign::sole();
        $this->assertSame('NOURA10', $ic->promo_code);
        $this->assertEqualsWithDelta(1120.0, (float) $ic->total_payout, 0.001);

        $html = $this->page($this->member('owner'), 'agency-ops');
        $this->assertStringContainsString('1,120.000 KWD', $html, 'payout shown in the client currency with 3 decimals');
        $this->assertStringContainsString('3 days left', $html);
    }
}
