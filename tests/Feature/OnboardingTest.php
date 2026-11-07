<?php

namespace Tests\Feature;

use App\Filament\Pages\DailyDesk;
use App\Filament\Pages\OnboardingPage;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** D-188 required onboarding: gate, required steps 1–3, skippable 4–7, Solo without the team step, Desk checklist. */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $plan = 'starter', bool $required = true, string $role = 'owner'): array
    {
        $a = Agency::create(['name' => 'New workspace', 'slug' => 'ob-'.$plan.'-'.$role.'-'.(int) $required, 'plan' => $plan, 'status' => 'active',
            'currency' => 'USD', 'onboarding_required' => $required]);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => $role]);

        return [$a, $u];
    }

    private function page(Agency $a, User $u)
    {
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        return Livewire::test(OnboardingPage::class);
    }

    public function test_only_the_owner_of_a_gated_workspace_is_sent_to_onboarding(): void
    {
        [$a, $owner] = $this->workspace();
        [$old, $oldOwner] = $this->workspace('pro', required: false);
        $member = User::factory()->create();
        $a->members()->attach($member->id, ['role' => 'analyst']);

        $this->actingAs($owner)->get("/app/{$a->slug}/desk")->assertRedirect("/app/{$a->slug}/onboarding");
        $this->actingAs($owner)->get("/app/{$a->slug}/onboarding")->assertOk()->assertSee('شركة تدير إعلاناتها بنفسها');

        $this->actingAs($member)->get("/app/{$a->slug}/desk")->assertOk();

        $this->actingAs($oldOwner)->get("/app/{$old->slug}/desk")->assertOk();
    }

    public function test_the_three_required_steps_open_the_workspace(): void
    {
        [$a, $u] = $this->workspace();

        $this->page($a, $u)->call('skip')->assertForbidden();
        $page = $this->page($a, $u);
        $page->call('save')->assertHasErrors('audience');
        $page->set('audience', 'business')->call('save')->assertSet('step', 2);
        $page->set('name', 'Souq Store')->set('country', 'SA')->set('currency', 'SAR')->call('save')->assertSet('step', 3)->assertSet('clientName', 'Souq Store');
        $page->call('save')->assertSet('step', 4);

        $a->refresh();
        $this->assertSame('business', $a->audience);
        $this->assertSame(['Souq Store', 'SA', 'SAR'], [$a->name, $a->country, $a->currency]);
        $this->assertNotNull($a->onboarding_completed_at);
        $this->assertSame('Souq Store', Client::where('agency_id', $a->id)->value('name'));
        $this->assertTrue(AuditLog::where('action', 'onboarding.completed')->where('subject_id', $a->id)->exists());
        $this->assertSame(3, AuditLog::where('action', 'onboarding.step')->where('subject_id', $a->id)->count());

        $this->actingAs($u)->get("/app/{$a->slug}/desk")->assertOk()->assertSee('أكمل الإعداد')->assertSee('اربط حساباتك أو استورد بياناتك لترى النتائج.');
    }

    public function test_solo_skips_the_team_step_and_the_checklist_can_be_finished_or_hidden(): void
    {
        [$a, $u] = $this->workspace('solo');
        $a->forceFill(['audience' => 'freelancer', 'onboarding_step' => 4, 'onboarding_completed_at' => now()])->save();
        Client::create(['agency_id' => $a->id, 'name' => 'Client A', 'currency' => 'USD']);

        $page = $this->page($a, $u);
        $page->assertSet('step', 4)->set('monthlyBudget', '1500')->call('save')->assertSet('step', 5)
            ->call('skip')->assertSet('step', 6)->assertSee('The Solo plan has one seat')
            ->call('save')->assertSet('step', 7)->assertSee('8:00 AM Amman time')
            ->set('emailDigest', true)->call('save')->assertRedirect(DailyDesk::getUrl(tenant: $a));
        $this->assertEquals(1500, Client::where('agency_id', $a->id)->value('monthly_budget'));
        $this->assertTrue((bool) $u->fresh()->email_digest);

        $this->actingAs($u)->get("/app/{$a->slug}/desk")->assertOk()->assertSee('أكمل الإعداد')->assertDontSee('ادعُ فريقك');
        Livewire::test(DailyDesk::class)->call('hideOnboardingChecklist');
        $this->assertTrue((bool) $a->fresh()->onboarding_checklist_hidden);
        $this->actingAs($u)->get("/app/{$a->slug}/desk")->assertDontSee('أكمل الإعداد');
    }
}
