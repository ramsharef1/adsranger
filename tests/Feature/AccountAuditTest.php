<?php

namespace Tests\Feature;

use App\Audit\AccountAudit;
use App\Filament\Pages\AccountAuditPage;
use App\Jobs\RunAccountAudit;
use App\Models\Ad;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\AdSet;
use App\Models\Agency;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Client;
use App\Models\User;
use App\Support\CurrentClient;
use App\Support\DemoData;
use App\Sync\SyncService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** #173 Day-1 account audit (FR-016, D-135): ranked findings, plan networks, Starter top 3, roles, first-sync trigger. */
class AccountAuditTest extends TestCase
{
    use RefreshDatabase;

    private function agency(string $plan = 'starter', string $role = 'owner'): array
    {
        $a = Agency::create(['name' => 'Acme', 'slug' => 'acme-audit-'.$plan.'-'.$role, 'plan' => $plan, 'status' => 'active', 'currency' => 'USD']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => $role]);
        DemoData::seed($a, 30);
        $client = Client::where('agency_id', $a->id)->where('name', 'like', '%Souq%')->firstOrFail();

        return [$a, $u, $client];
    }

    /** Plant a rejected ad in the client's highest-spend Meta ad set, with numeric ids so the fix link is built. */
    private function plantRejectedAd(Client $client): Ad
    {
        $account = AdAccount::where('client_id', $client->id)->whereHas('network', fn ($q) => $q->where('key', 'meta'))->firstOrFail();
        $account->update(['external_id' => 'act_123456']);
        $set = AdSet::where('ad_account_id', $account->id)->firstOrFail();
        $ad = Ad::where('ad_set_id', $set->id)->firstOrFail();
        $ad->update(['status' => 'DISAPPROVED', 'external_id' => '987654']);

        return $ad;
    }

    private function page(Agency $a, User $u, Client $client)
    {
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);
        CurrentClient::remember($client->id);

        return Livewire::test(AccountAuditPage::class)->set('clientId', $client->id);
    }

    public function test_audit_ranks_findings_by_money_at_risk_with_a_fix_link(): void
    {
        [, , $client] = $this->agency();
        $ad = $this->plantRejectedAd($client);

        $audit = AccountAudit::run($client);
        $findings = $audit->findings()->get();

        $this->assertGreaterThan(0, $findings->count());
        $this->assertLessThanOrEqual(10, $findings->count());
        $rejected = $findings->firstWhere('check_key', 'rejected_ads');
        $this->assertNotNull($rejected);
        $this->assertSame('critical', $rejected->severity);
        $this->assertSame($ad->id, $rejected->entity_id);
        $this->assertGreaterThan(0, $rejected->money_at_risk);
        $this->assertSame('https://adsmanager.facebook.com/adsmanager/manage/ads?act=123456&selected_ad_ids=987654', $rejected->fix_url);
        $this->assertTrue($findings->contains('check_key', 'pacing'));

        $money = $findings->pluck('money_at_risk')->all();
        $withMoney = array_values(array_filter($money, fn ($m) => $m !== null));
        $sorted = $withMoney;
        rsort($sorted);
        $this->assertSame($sorted, $withMoney);
        $this->assertSame(array_merge($withMoney, array_fill(0, count($money) - count($withMoney), null)), $money); // money first, then results-only
        $this->assertEqualsWithDelta(array_sum($withMoney), $audit->money_at_risk, 0.05);
    }

    public function test_audit_uses_only_networks_in_the_plan(): void
    {
        [$a, , $client] = $this->agency();
        $this->plantRejectedAd($client);
        $meta = AdNetwork::where('key', 'meta')->firstOrFail();
        DB::table('agency_network_overrides')->insert(['agency_id' => $a->id, 'ad_network_id' => $meta->id, 'mode' => 'deny', 'created_at' => now(), 'updated_at' => now()]);

        $audit = AccountAudit::run($client->fresh());

        $this->assertFalse($audit->findings()->where('network', 'meta')->exists());
        $this->assertFalse($audit->findings()->where('check_key', 'rejected_ads')->exists());
    }

    public function test_starter_sees_the_top_three_and_pro_sees_all(): void
    {
        [$a, $u, $client] = $this->agency('starter');
        $this->plantRejectedAd($client);
        $audit = AccountAudit::run($client);
        $this->assertGreaterThan(3, $audit->findings_count);
        $fourth = $audit->findings()->where('rank', 4)->first();

        $hidden = $audit->findings_count - 3;
        $page = $this->page($a, $u, $client)->assertOk()->assertSee($hidden.' more finding', false);
        $this->assertSame(3, count($page->viewData('shown')));
        $page->call('dismiss', $fourth->id)->assertForbidden();

        $a->update(['plan' => 'pro']);
        $page = $this->page($a->fresh(), $u, $client);
        $this->assertSame($audit->findings_count, count($page->viewData('shown')));
        $this->assertSame(0, $page->viewData('hiddenCount'));
    }

    public function test_managers_run_and_dismiss_and_dismissals_carry_over_but_analysts_only_view(): void
    {
        [$a, $u, $client] = $this->agency('pro', 'manager');
        $this->plantRejectedAd($client);

        $this->page($a, $u, $client)->call('runAudit')->assertOk();
        $audit = AccountAudit::latest($client);
        $this->assertSame($u->id, $audit->ran_by);
        $first = $audit->findings()->where('rank', 1)->first();

        $this->page($a, $u, $client)->call('dismiss', $first->id)->assertOk();
        $this->assertNotNull($first->fresh()->dismissed_at);

        $again = AccountAudit::run($client);
        $this->assertNotNull($again->findings()->where('fingerprint', $first->fingerprint)->first()->dismissed_at);

        $analyst = User::factory()->create();
        $a->members()->attach($analyst->id, ['role' => 'analyst']);
        $this->page($a, $analyst, $client)->assertOk()->assertDontSee('Run the audit now')->call('runAudit')->assertForbidden();
        $this->assertSame(2, Audit::where('client_id', $client->id)->count());
    }

    public function test_first_successful_sync_queues_one_audit_per_client(): void
    {
        [, , $client] = $this->agency();
        $account = AdAccount::where('client_id', $client->id)->firstOrFail();

        Queue::fake();
        SyncService::queueFirstAudit($account);
        Queue::assertPushed(RunAccountAudit::class, fn ($job) => $job->clientId === $client->id && $job->trigger === 'first_sync');

        (new RunAccountAudit($client->id))->handle();
        (new RunAccountAudit($client->id))->handle();
        $this->assertSame(1, Audit::where('client_id', $client->id)->count());
        $this->assertSame('first_sync', Audit::where('client_id', $client->id)->value('trigger'));

        Queue::fake();
        SyncService::queueFirstAudit($account);
        Queue::assertNotPushed(RunAccountAudit::class);
    }

    public function test_page_renders_in_arabic(): void
    {
        [$a, $u, $client] = $this->agency('pro');
        AccountAudit::run($client);

        $this->actingAs($u)->withSession(['locale' => 'ar'])->get("/app/{$a->slug}/account-audit?client={$client->id}")
            ->assertOk()->assertSee('تدقيق الحساب')->assertSee('المبلغ المعرّض للخطر')->assertSee('شغّل التدقيق الآن');
        $this->assertTrue(AuditFinding::where('client_id', $client->id)->exists());
    }
}
