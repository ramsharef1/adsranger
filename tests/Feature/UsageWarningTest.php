<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\User;
use App\Support\DemoData;
use App\Support\PlanLimits;
use App\Support\UsageWarning;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** EX-50 point 6: a dismissible warning banner at 80 % of the ad-account or seat limit, owner/admin only. */
class UsageWarningTest extends TestCase
{
    use RefreshDatabase;

    private function agency(array $attrs = []): array
    {
        $a = Agency::create($attrs + ['name' => 'Acme', 'slug' => 'acme', 'plan' => 'starter', 'status' => 'active', 'language' => 'en', 'signup_source' => 'invite']);
        $owner = User::factory()->create();
        $analyst = User::factory()->create();
        $a->members()->attach($owner->id, ['role' => 'owner']);
        $a->members()->attach($analyst->id, ['role' => 'analyst']);
        DemoData::seed($a, 5);
        AdAccount::where('agency_id', $a->id)->update(['is_active' => false]);
        $tpl = AdAccount::where('agency_id', $a->id)->firstOrFail();
        for ($i = 0; $i < 12; $i++) {
            AdAccount::create(['agency_id' => $a->id, 'connection_id' => $tpl->connection_id, 'ad_network_id' => $tpl->ad_network_id, 'external_id' => "w-$i", 'is_active' => false]);
        }

        return [$a, $owner, $analyst];
    }

    private function track(Agency $a, int $n): void
    {
        AdAccount::where('agency_id', $a->id)->update(['is_active' => false]);
        AdAccount::where('agency_id', $a->id)->where('external_id', 'like', 'w-%')->orderBy('id')->limit($n)->get()->each->update(['is_active' => true]);
    }

    public function test_banner_shows_at_80_percent_for_owner_only(): void
    {
        [$a, $owner, $analyst] = $this->agency();
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $limit = PlanLimits::adAccountsLimit($a);
        $this->assertSame(10, $limit);

        $this->track($a, 7);
        $this->assertSame([], collect(UsageWarning::rows($a))->where('key', 'ad_accounts')->all());
        $this->actingAs($owner)->withSession(['locale' => 'en'])->get('/app/acme/pacing')->assertOk()->assertDontSee('8 of 10 ad accounts tracked');

        $this->track($a, 8);
        $this->actingAs($owner)->withSession(['locale' => 'en'])->get('/app/acme/pacing')->assertOk()
            ->assertSee('data-usage-warning', false)->assertSee('You are close to your plan limit')
            ->assertSee('8 of 10 ad accounts tracked')->assertSee('See plans');
        $this->actingAs($owner)->withSession(['locale' => 'ar'])->get('/app/acme/pacing')->assertOk()
            ->assertSee('اقتربت من حد باقتك')->assertSee('8 من 10 حسابات إعلانية متتبَّعة');
        // Analysts can't act on it, so they don't see it (EX-37).
        $this->actingAs($analyst)->withSession(['locale' => 'en'])->get('/app/acme/pacing')->assertOk()->assertDontSee('data-usage-warning', false);

        // At the limit the warning stays (the create action still opens the quota notice).
        $this->track($a, 10);
        $this->assertSame(10, collect(UsageWarning::rows($a))->firstWhere('key', 'ad_accounts')['used']);
    }

    public function test_seats_and_trial_cap_wording(): void
    {
        [$a] = $this->agency(['status' => 'trial', 'trial_ends_at' => now()->addDays(10), 'signup_source' => 'self_signup']);
        // Trial cap: 3 seats; owner + analyst = 2 (67 %): no warning. One pending invite → 3 of 3.
        $this->assertSame(3, PlanLimits::seatsLimit($a));
        $this->assertSame([], collect(UsageWarning::rows($a))->where('key', 'seats')->all());
        $a->invitations()->create(['email' => 'x@acme.test', 'role' => 'manager', 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDays(7)]);
        app()->setLocale('en');
        $row = collect(UsageWarning::rows($a))->firstWhere('key', 'seats');
        $this->assertSame('3 of 3 team members', $row['text']);
        $this->assertSame('You are close to your trial limit', UsageWarning::title($a));
        app()->setLocale('ar');
        $this->assertSame('3 من 3 أعضاء في الفريق', collect(UsageWarning::rows($a))->firstWhere('key', 'seats')['text']);
        $this->assertSame('اقتربت من حد الفترة التجريبية', UsageWarning::title($a));

        // Read-only (trial ended, self-signup): no usage banner.
        $a->update(['trial_ends_at' => now()->subDay()]);
        $this->assertSame([], UsageWarning::rows($a->fresh()));
    }
}
