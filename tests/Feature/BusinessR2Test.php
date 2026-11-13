<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\CustomersBoard;
use App\Filament\Admin\Pages\GrowthLog;
use App\Models\Agency;
use App\Models\ClientOnboardingLink;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\Business\TrialRequests;
use App\Support\Business\UpgradeMoments;
use App\Support\Business\UsageLedger;
use App\Support\PlanGate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** R2 (D-289, D-291, D-292): business view, sign-up origin + share codes, upgrade moments, trial requests, usage ledger, intake. */
class BusinessR2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        UpgradeMoments::reset();
    }

    private function agency(array $extra = []): Agency
    {
        return Agency::create(['name' => 'Souq Ads', 'slug' => 'souq-'.uniqid(), 'plan' => 'starter', 'status' => 'active', 'timezone' => 'Asia/Amman', 'currency' => 'USD'] + $extra);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    private function join(string $email, array $session = []): void
    {
        $this->withSession($session)->post('/waitlist', ['agency_name' => 'Souq Ads', 'email' => $email, 'country' => 'SA', 'consent' => '1'])->assertRedirect('/waitlist');
    }

    public function test_waitlist_keeps_the_first_origin_and_gives_a_share_code(): void
    {
        $this->get('/tools/hreflang-checker?utm_source=x&utm_medium=post&utm_campaign=launch')->assertOk();
        $this->post('/waitlist', ['agency_name' => 'Souq Ads', 'email' => 'a@souq.test', 'country' => 'SA', 'consent' => '1'])
            ->assertSessionHas('waitlist_ref');

        $a = WaitlistEntry::where('email', 'a@souq.test')->firstOrFail();
        $this->assertSame('/tools/hreflang-checker', $a->origin_landing);
        $this->assertSame('hreflang-checker', $a->origin_tool);
        $this->assertSame(['x', 'post', 'launch'], [$a->utm_source, $a->utm_medium, $a->utm_campaign]);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $a->ref_code);

        // A colleague who opens the share link is counted under that code; an unknown code is dropped.
        $this->join('b@souq.test', ['origin' => ['landing' => '/waitlist', 'ref' => $a->ref_code]]);
        $this->join('c@souq.test', ['origin' => ['landing' => '/waitlist', 'ref' => 'NOPE1234']]);
        $this->assertSame($a->ref_code, WaitlistEntry::where('email', 'b@souq.test')->value('referred_by'));
        $this->assertNull(WaitlistEntry::where('email', 'c@souq.test')->value('referred_by'));

        // Signing up again keeps the first origin and the same code.
        $this->join('a@souq.test', ['origin' => ['landing' => '/en/pricing']]);
        $this->assertSame('/tools/hreflang-checker', $a->fresh()->origin_landing);
        $this->assertSame($a->ref_code, $a->fresh()->ref_code);
    }

    public function test_upgrade_moments_count_shown_locks_only_once_per_request(): void
    {
        $a = $this->agency(['plan' => 'starter']);
        $this->assertTrue(PlanGate::locked($a, 'forecast'));
        $this->assertSame(0, DB::table('upgrade_moments')->count(), 'a plain check is not a moment');

        PlanGate::lockedShown($a, 'forecast');
        PlanGate::lockedShown($a, 'forecast');
        UpgradeMoments::record($a->id, 'seats', 'limit');
        $this->assertSame(1, (int) DB::table('upgrade_moments')->where(['feature' => 'forecast', 'kind' => 'locked'])->value('hits'));

        UpgradeMoments::reset();
        PlanGate::lockedShown($a, 'forecast');
        $top = UpgradeMoments::top();
        $this->assertSame('forecast', $top[0]->feature);
        $this->assertSame(2, (int) $top[0]->hits);
    }

    public function test_trial_request_once_then_granted_from_the_customers_page(): void
    {
        $a = $this->agency(['status' => 'trial', 'trial_ends_at' => now()->subDay()]);
        $owner = User::factory()->create();

        $this->assertFalse(TrialRequests::ask($a, $owner, '  '));
        $this->assertTrue(TrialRequests::ask($a, $owner, 'Our client signs next week'));
        $this->assertFalse(TrialRequests::ask($a, $owner, 'again'), 'one open request at a time');

        $id = (int) DB::table('trial_extension_requests')->value('id');
        $this->actingAs($this->admin());
        Livewire::test(CustomersBoard::class)->assertSee('Souq Ads')->set("grantDays.$id", 10)->call('grant', $id);

        $this->assertSame('granted', TrialRequests::latest($a)->status);
        $this->assertTrue($a->fresh()->trial_ends_at->isFuture());
    }

    public function test_customers_page_notes_matrix_and_ledger(): void
    {
        $a = $this->agency();
        DB::table('clients')->insert(['agency_id' => $a->id, 'name' => 'Client One', 'status' => 'active', 'created_at' => now()->subMonth(), 'updated_at' => now()]);
        $this->assertSame(1, UsageLedger::snapshot(now()->subMonthNoOverflow()->format('Y-m')));
        $this->assertSame(1, (int) DB::table('usage_ledger')->where('agency_id', $a->id)->value('clients'));

        $this->actingAs($this->admin());
        $this->get('/admin/customers')->assertOk();
        Livewire::test(CustomersBoard::class)
            ->set('noteAgency', $a->id)->set('noteBody', 'Call after Eid')->set('noteNext', now()->addWeek()->toDateString())
            ->call('addNote')->assertHasNoErrors()->assertSee('Call after Eid');

        $m = CustomersBoard::planMatrix();
        $this->assertContains('agency', $m['tiers']);
        $forecast = collect($m['features'])->firstWhere('key', 'forecast');
        $this->assertFalse($forecast['tiers']['starter']);
        $this->assertTrue($forecast['tiers']['agency']);
    }

    public function test_growth_log_counts_origins_and_keeps_checks_and_wins(): void
    {
        WaitlistEntry::create(['email' => 'o@x.test', 'agency_name' => 'X', 'country' => 'SA', 'consent_at' => now(), 'consent_text_version' => 'v1', 'origin_tool' => 'hreflang-checker', 'ref_code' => 'ABCDEFGH']);
        WaitlistEntry::create(['email' => 'p@x.test', 'agency_name' => 'Y', 'country' => 'AE', 'consent_at' => now(), 'consent_text_version' => 'v1', 'referred_by' => 'ABCDEFGH']);

        $this->actingAs($this->admin());
        $this->get('/admin/growth-log')->assertOk()->assertSee('hreflang-checker')->assertSee('o@x.test');
        Livewire::test(GrowthLog::class)
            ->set('question', 'best Arabic ad report tool')->set('assistant', 'Assistant A')->set('cited', 'tool')->call('addCheck')->assertHasNoErrors()
            ->set('prospect', 'Gulf Media')->set('country', 'ae')->set('outcome', 'lost')->set('reason', 'Needs TikTok first')->call('addProspect')->assertHasNoErrors()
            ->assertSee('Needs TikTok first');
        $this->assertSame('AE', DB::table('prospect_log')->value('country'));
        $this->assertSame(1, DB::table('ai_answer_checks')->count());
    }

    public function test_client_intake_fields_stay_hidden_until_the_legal_line_is_set(): void
    {
        $a = $this->agency(['plan' => 'agency']);
        config(['modules.enabled.client_onboarding' => true]);
        $link = ClientOnboardingLink::create(['agency_id' => $a->id, 'token' => Str::random(40), 'prospect_name' => 'Shop', 'status' => 'pending']);

        $this->get('/onboard/'.$link->token)->assertOk()->assertDontSee('intake[offers]', false);
        $this->post('/onboard/'.$link->token, ['intake' => ['offers' => 'Abayas']])->assertRedirect();
        $this->assertNull($link->fresh()->intake);

        config(['services.client_intake.legal_line' => 'Legal line from Business.']);
        $this->get('/onboard/'.$link->token)->assertOk()->assertSee('intake[offers]', false)->assertSee('Legal line from Business.');
        $this->post('/onboard/'.$link->token, ['intake' => ['offers' => 'Abayas', 'evil' => 'x', 'dont' => '']])->assertRedirect();
        $this->assertSame(['offers' => 'Abayas'], $link->fresh()->intake);
    }
}
