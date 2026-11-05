<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Agencies\AgencyResource;
use App\Filament\Pages\AlertRules;
use App\Filament\Pages\ExportData;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Jobs\DailySync;
use App\Jobs\RunAlerts;
use App\Jobs\SyncAdAccount;
use App\Mail\TrialLifecycleMail;
use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\SyncRun;
use App\Models\User;
use App\Support\DemoData;
use App\Support\ReadOnlyGuard;
use App\Support\Trial;
use App\Support\TrialLifecycle;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * D-61 trial lifecycle: trial → read-only (14 days) → closed / expired (30 days) → archived → purged.
 * Self-signup agencies only: invited and admin-created agencies are never locked, closed or deleted.
 */
class TrialLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $end;

    protected function setUp(): void
    {
        parent::setUp();
        $this->end = Carbon::parse('2026-12-01 10:00:00', 'UTC');
        Carbon::setTestNow($this->end->copy()->subDays(10));
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function agency(string $source = 'self_signup', string $slug = 'trial-co', ?Carbon $ends = null): array
    {
        $a = Agency::create(['name' => 'Trial Co '.$slug, 'slug' => $slug, 'plan' => 'starter', 'status' => 'trial', 'timezone' => 'UTC',
            'trial_ends_at' => $ends ?? $this->end, 'signup_source' => $source, 'language' => 'en']);
        $owner = User::factory()->create();
        $a->members()->attach($owner->id, ['role' => 'owner']);
        $a->forceFill(['owner_id' => $owner->id])->save();

        return [$a->fresh(), $owner];
    }

    private function at(string $offset): void
    {
        Carbon::setTestNow($this->end->copy()->modify($offset));
    }

    private function queued(string $kind): int
    {
        return Mail::queued(TrialLifecycleMail::class, fn (TrialLifecycleMail $m) => $m->kind === $kind)->count();
    }

    public function test_timeline_moves_through_each_stage_once_with_mails_and_audits(): void
    {
        config(['trial.purge_after_days' => 30]); // auto-purge is off by default (D-80); this test covers it when enabled
        [$a, $owner] = $this->agency();
        DemoData::seed($a, 10);
        $clients = Client::where('agency_id', $a->id)->count();
        $this->assertGreaterThan(0, $clients);

        $this->at('-5 days');
        TrialLifecycle::run();
        $this->assertSame(0, $this->queued('ending'));
        $this->assertSame(Trial::RUNNING, Trial::stage($a->fresh()));

        // 3 days before the end: "ends in 3 days", once.
        $this->at('-3 days +1 hour');
        TrialLifecycle::run();
        TrialLifecycle::run();
        $this->assertSame(1, $this->queued('ending'));
        Mail::assertQueued(TrialLifecycleMail::class, fn ($m) => $m->hasTo($owner->email) && $m->kind === 'ending');

        // Day 14 end: read-only, "trial ended" mail once.
        $this->at('+1 hour');
        $this->assertSame(Trial::READ_ONLY, Trial::stage($a->fresh()), 'read-only from the dates even before the daily run');
        TrialLifecycle::run();
        TrialLifecycle::run();
        $a->refresh();
        $this->assertSame('read_only', $a->status);
        $this->assertNotNull($a->trial_readonly_at);
        $this->assertSame(1, $this->queued('ended'));
        $this->assertSame(1, AuditLog::where('action', 'agency.trial_readonly')->where('subject_id', $a->id)->count());
        $this->assertTrue(Trial::isReadOnly($a));
        $this->assertFalse(Trial::isClosed($a));

        // 14 days read-only later: closed (expired).
        $this->at('+14 days +1 hour');
        TrialLifecycle::run();
        $a->refresh();
        $this->assertSame('expired', $a->status);
        $this->assertSame(Trial::EXPIRED, Trial::stage($a));
        $this->assertSame(1, AuditLog::where('action', 'agency.trial_expired')->count());
        $this->assertSame(0, $this->queued('deletion'));

        // 7 days before deletion (day 14 + 14 + 23): final notice, once.
        $this->at('+37 days +1 hour');
        TrialLifecycle::run();
        TrialLifecycle::run();
        $this->assertSame(1, $this->queued('deletion'));
        $this->assertSame(1, AuditLog::where('action', 'agency.deletion_scheduled')->count());
        $this->assertNull($a->fresh()->deleted_at);

        // 30 days after closing: archived (default mode): soft-deleted, every row kept, tenant routes 404.
        $this->at('+44 days +1 hour');
        $this->assertSame('archive', config('trial.delete_mode'));
        TrialLifecycle::run();
        $this->assertNull(Agency::find($a->id));
        $archived = Agency::withTrashed()->find($a->id);
        $this->assertTrue($archived->trashed());
        $this->assertSame(Trial::ARCHIVED, Trial::stage($archived));
        $this->assertSame($clients, Client::where('agency_id', $a->id)->count(), 'archive keeps the data');
        $this->assertSame('archive', AuditLog::where('action', 'agency.deleted')->firstOrFail()->meta['mode']);
        $this->assertSame($owner->email, $owner->fresh()->email, 'member emails are never rewritten');
        $this->actingAs($owner)->get('/app/trial-co')->assertNotFound();

        // Purged after `trial.purge_after_days` (30): rows deleted through the foreign keys, owner account too.
        $this->at('+75 days');
        TrialLifecycle::run();
        $this->assertNull(Agency::withTrashed()->find($a->id));
        $this->assertSame(0, Client::where('agency_id', $a->id)->count());
        $this->assertNull(User::find($owner->id));
        $this->assertSame(['archive', 'archive_purged'], AuditLog::where('action', 'agency.deleted')->orderBy('id')->get()->pluck('meta.mode')->all());

        // Three mails in all, never repeated.
        $this->assertSame([1, 1, 1], [$this->queued('ending'), $this->queued('ended'), $this->queued('deletion')]);
    }

    public function test_deletion_waits_for_the_full_notice_period_if_a_run_was_missed(): void
    {
        [$a] = $this->agency();
        $this->at('+50 days'); // first run ever, long past the due date
        TrialLifecycle::run();
        $a = Agency::withTrashed()->find($a->id);
        $this->assertFalse($a->trashed(), 'never deleted on the same run that sends the notice');
        $this->assertSame(1, $this->queued('deletion'));
        $this->at('+56 days');
        TrialLifecycle::run();
        $this->assertFalse(Agency::withTrashed()->find($a->id)->trashed());
        $this->at('+57 days +1 hour');
        TrialLifecycle::run();
        $this->assertTrue(Agency::withTrashed()->find($a->id)->trashed());
    }

    public function test_delete_mode_purges_on_the_due_day(): void
    {
        config(['trial.delete_mode' => 'delete']);
        [$a, $owner] = $this->agency();
        $other = Agency::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active', 'signup_source' => 'invite']);
        $other->members()->attach($owner->id, ['role' => 'manager']);
        DemoData::seed($a, 5);
        $this->at('+38 days');
        TrialLifecycle::run();
        $this->at('+45 days');
        TrialLifecycle::run();
        $this->assertNull(Agency::withTrashed()->find($a->id));
        $this->assertSame(0, Client::where('agency_id', $a->id)->count());
        $this->assertNotNull(User::find($owner->id), 'a member of another workspace keeps the account');
        $this->assertSame('deleted', AuditLog::where('action', 'agency.deleted')->firstOrFail()->meta['mode']);
    }

    public function test_invited_and_admin_agencies_are_never_locked_closed_or_deleted(): void
    {
        [$invite, $owner] = $this->agency('invite', 'invited');
        [$admin] = $this->agency('admin', 'admin-made');
        $this->at('+400 days');
        foreach (range(1, 3) as $i) {
            TrialLifecycle::run();
        }
        foreach ([$invite, $admin] as $a) {
            $a = Agency::withTrashed()->find($a->id);
            $this->assertFalse($a->trashed());
            $this->assertSame('trial', $a->status);
            $this->assertSame(Trial::NONE, Trial::stage($a));
            $this->assertFalse(Trial::isReadOnly($a));
            $this->assertTrue(Trial::hasEnded($a), 'trial ended: Starter limits as before, nothing locked');
        }
        Mail::assertNothingQueued();
        $this->assertSame(0, AuditLog::whereIn('action', ['agency.trial_readonly', 'agency.trial_expired', 'agency.deletion_scheduled', 'agency.deleted'])->count());

        // Writes still work for the invited agency.
        $this->actingAs($owner);
        Filament::setTenant($invite->fresh());
        Livewire::test(AlertRules::class)->set('rules.spend_no_results.enabled', false)->call('save')->assertNotNotified('Your trial ended — choose a plan');
        $this->assertFalse((bool) AlertRule::where('agency_id', $invite->id)->where('rule_key', 'spend_no_results')->value('enabled'));
        $this->actingAs($owner)->get('/app/invited/clients/create')->assertOk();
    }

    public function test_read_only_refuses_writes_but_viewing_and_export_work(): void
    {
        [$a, $owner] = $this->agency();
        DemoData::seed($a, 10);
        $client = Client::where('agency_id', $a->id)->orderBy('id')->first();
        $this->at('+1 day');
        TrialLifecycle::run();
        $a->refresh();

        $this->actingAs($owner);
        Filament::setTenant($a);

        // Livewire writes: refused with the EX-50 style notice, nothing saved.
        Livewire::test(AlertRules::class)->call('save')->assertNotified('Your trial ended — choose a plan');
        Livewire::test(AlertRules::class)->call('runNow')->assertNotified('Your trial ended — choose a plan');
        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])->callAction('clientLinks', ['label' => 'x', 'days' => ''])
            ->assertNotified('Your trial ended — choose a plan');
        $this->assertSame(0, ClientLink::count());
        // Viewing calls and download actions still work.
        Livewire::test(ViewClient::class, ['record' => $client->getRouteKey()])->call('setPeriod', '7d')->assertNotNotified('Your trial ended — choose a plan');
        $this->assertTrue(ReadOnlyGuard::allows(new ExportData, 'mountAction', ['metrics']));
        $this->assertTrue(ReadOnlyGuard::allows(new ViewClient, 'mountAction', ['monthlyPdf']));
        $this->assertFalse(ReadOnlyGuard::allows(new ViewClient, 'mountAction', ['emailReports']));

        // Pages: create / edit don't open; views do; the workspace ZIP downloads.
        $this->get('/app/trial-co/clients/create')->assertRedirect('/app/trial-co/clients');
        $this->get("/app/trial-co/clients/{$client->id}/edit")->assertRedirect('/app/trial-co/clients');
        $this->withSession(['locale' => 'en'])->get('/app/trial-co/pacing')->assertOk()->assertSee('Your trial ended — choose a plan')->assertSee('See plans');
        $this->get('/app/trial-co/export-data')->assertOk();
        $this->get('/app/trial-co/export/workspace.zip')->assertOk();
    }

    public function test_read_only_stops_syncs_and_alert_runs(): void
    {
        [$ro] = $this->agency('self_signup', 'ro');
        [$live] = $this->agency('invite', 'live');
        DemoData::seed($ro, 5);
        DemoData::seed($live, 5);
        AdAccount::query()->update(['is_active' => true]); // demo accounts start untracked
        $this->at('+2 days');
        Carbon::setTestNow(now()->setTime(10, 0));

        Queue::fake();
        (new DailySync)->handle();
        $roAccounts = AdAccount::where('agency_id', $ro->id)->pluck('id')->all();
        $pushed = collect(Queue::pushed(SyncAdAccount::class))->map(fn ($j) => $j->adAccountId);
        $this->assertNotEmpty($pushed->diff($roAccounts), 'the invited agency still syncs');
        $this->assertEmpty($pushed->intersect($roAccounts), 'no syncs for the read-only agency');

        // A sync already queued does nothing.
        $runs = SyncRun::count();
        (new SyncAdAccount($roAccounts[0], now()->subDay()->toDateString(), now()->toDateString()))->handle();
        $this->assertSame($runs, SyncRun::count());

        (new RunAlerts)->handle();
        $this->assertNull($ro->fresh()->alerts_run_on);
        $this->assertNotNull($live->fresh()->alerts_run_on);
    }

    public function test_closed_workspace_opens_on_the_trial_ended_page_and_owner_keeps_the_zip(): void
    {
        [$a, $owner] = $this->agency();
        $analyst = User::factory()->create();
        $a->members()->attach($analyst->id, ['role' => 'analyst']);
        $this->at('+20 days');
        TrialLifecycle::run();
        $this->assertSame('expired', $a->fresh()->status);

        $this->actingAs($owner)->get('/app/trial-co')->assertRedirect('/app/trial-co/trial-ended');
        $this->actingAs($owner)->get('/app/trial-co/clients')->assertRedirect('/app/trial-co/trial-ended');
        $this->actingAs($owner)->withSession(['locale' => 'en'])->get('/app/trial-co/trial-ended')->assertOk()
            ->assertSee('This workspace is closed. Your data is kept until 14 January 2027')
            ->assertSee('Download all workspace data')->assertSee('See plans');
        $this->actingAs($owner)->get('/app/trial-co/export/workspace.zip')->assertOk();
        $this->actingAs($owner)->get('/app/trial-co/plan')->assertOk();

        $this->actingAs($analyst)->withSession(['locale' => 'ar'])->get('/app/trial-co/trial-ended')->assertOk()
            ->assertSee('أُغلقت مساحة العمل هذه')->assertSee('اطلب من مالك مساحة العمل اختيار باقة.')
            ->assertDontSee('Download all workspace data');
    }

    public function test_admin_extend_lifts_read_only_closed_and_archived(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $action = AgencyResource::extendTrialAction();
        foreach ([Trial::READ_ONLY => ['+3 days'], Trial::EXPIRED => ['+20 days'], Trial::ARCHIVED => ['+38 days', '+46 days']] as $stage => $runs) {
            Carbon::setTestNow($this->end->copy()->subDays(10));
            [$a] = $this->agency('self_signup', 'ext-'.$stage);
            $this->at('-1 day');
            TrialLifecycle::run(); // "ends in 3 days" sent
            foreach ($runs as $when) {
                $this->at($when);
                TrialLifecycle::run();
            }
            $a = Agency::withTrashed()->find($a->id);
            $this->assertSame($stage, Trial::stage($a));
            $this->assertTrue($action->record($a)->isVisible());

            $this->actingAs($admin);
            $to = Trial::extend($a, 7);
            $a = Agency::find($a->id);
            $this->assertNotNull($a, 'restored from the archive');
            $this->assertSame('trial', $a->status);
            $this->assertSame(Trial::RUNNING, Trial::stage($a));
            $this->assertFalse(Trial::isReadOnly($a));
            $this->assertNull($a->trial_ending_notified_at);
            $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $to->timestamp, 2);
            $this->assertSame($stage, AuditLog::where('action', 'agency.trial_extended')->where('subject_id', $a->id)->latest('id')->first()->meta['lifted_from']);
        }
    }

    public function test_archives_are_kept_by_default(): void
    {
        $this->assertNull(config('trial.purge_after_days'));
    }
}
