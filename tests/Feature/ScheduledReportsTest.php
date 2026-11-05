<?php

namespace Tests\Feature;

use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Jobs\QueueScheduledReports;
use App\Jobs\SendScheduledReport;
use App\Mail\ScheduledReportMail;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ReportSchedule;
use App\Models\ReportSend;
use App\Models\User;
use App\Support\DemoData;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/** #106 scheduled email reports: schedule CRUD + validation, due-once sending, PDF, unsubscribe, gating, isolation. */
class ScheduledReportsTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        // Tuesday 10 Nov 2026, 12:00 in Amman.
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00', 'UTC'));
        $this->agency = Agency::create(['name' => 'Acme Agency', 'slug' => 'acme', 'plan' => 'starter', 'status' => 'active', 'language' => 'en', 'timezone' => 'Asia/Amman']);
        DemoData::seed($this->agency, 45);
        $this->client = Client::where('agency_id', $this->agency->id)->orderBy('id')->first();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $role): User
    {
        $u = User::factory()->create();
        DB::table('agency_user')->where('user_id', $u->id)->delete();
        $this->agency->members()->attach($u->id, ['role' => $role]);

        return $u;
    }

    private function page(User $u)
    {
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->agency);

        return Livewire::test(ViewClient::class, ['record' => $this->client->getRouteKey()]);
    }

    private function schedule(array $attrs = []): ReportSchedule
    {
        $s = ReportSchedule::create($attrs + ['agency_id' => $this->agency->id, 'client_id' => $this->client->id, 'monthly_day' => 10,
            'weekly_day' => null, 'lang' => 'en', 'recipients' => ['a@example.com', 'b@example.com'], 'attach_pdf' => true,
            'include_link' => false, 'active' => true]);
        $s->forceFill(['created_at' => now()->subDays(20)])->save();

        return $s->fresh();
    }

    private function form(array $over = []): array
    {
        return $over + ['monthly' => true, 'monthly_day' => 3, 'weekly' => true, 'weekly_day' => 7, 'lang' => 'ar',
            'recipients' => ['Client@Example.com', 'boss@example.com'], 'attach_pdf' => true, 'include_link' => true, 'active' => true];
    }

    public function test_manager_saves_a_schedule(): void
    {
        $this->page($this->member('manager'))->callAction('emailReports', $this->form())->assertHasNoActionErrors();

        $s = ReportSchedule::where('client_id', $this->client->id)->firstOrFail();
        $this->assertSame(['client@example.com', 'boss@example.com'], $s->recipients);
        $this->assertSame(3, $s->monthly_day);
        $this->assertSame(7, $s->weekly_day);
        $this->assertSame('ar', $s->lang);
        $this->assertNotNull($s->activeLink(), 'a client link is issued for the emails');
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.schedule_saved', 'subject_id' => $this->client->id]);

        // Saving again updates the same schedule and keeps the link.
        $linkId = $s->client_link_id;
        $this->page($this->member('admin'))->callAction('emailReports', $this->form(['weekly' => false, 'lang' => 'en']))->assertHasNoActionErrors();
        $s->refresh();
        $this->assertNull($s->weekly_day);
        $this->assertSame('en', $s->lang);
        $this->assertSame($linkId, $s->client_link_id);
        $this->assertSame(1, ReportSchedule::count());
    }

    public function test_validation(): void
    {
        $owner = $this->member('owner');
        $this->page($owner)->callAction('emailReports', $this->form(['recipients' => ['not-an-email']]))->assertHasActionErrors();
        $this->page($owner)->callAction('emailReports', $this->form(['recipients' => array_map(fn ($i) => "p$i@example.com", range(1, 11))]))->assertHasActionErrors(['recipients']);
        $this->page($owner)->callAction('emailReports', $this->form(['recipients' => []]))->assertHasActionErrors(['recipients']);
        $this->page($owner)->callAction('emailReports', $this->form(['monthly' => false, 'weekly' => false]))->assertHasActionErrors();
        $this->assertSame(0, ReportSchedule::count());
    }

    public function test_analysts_do_not_see_the_schedule(): void
    {
        $this->page($this->member('analyst'))->assertActionHidden('emailReports')->assertActionVisible('exportCsv');
    }

    public function test_due_monthly_report_is_sent_once_with_the_pdf(): void
    {
        Mail::fake();
        $s = $this->schedule();

        (new QueueScheduledReports)->handle();

        Mail::assertSent(ScheduledReportMail::class, 2);
        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $m) {
            return $m->hasTo('a@example.com') && $m->kind === 'monthly' && $m->period === '2026-10'
                && str_starts_with((string) $m->pdf, '%PDF-') && count($m->attachments()) === 1;
        });
        $this->assertSame(2, ReportSend::where('status', 'sent')->count());
        $this->assertNotNull($s->fresh()->last_sent_at);

        (new QueueScheduledReports)->handle();
        Carbon::setTestNow(now()->addDay());
        (new QueueScheduledReports)->handle();
        Mail::assertSent(ScheduledReportMail::class, 2);

        // Next one: 10 December.
        $this->assertSame('2026-12-10', $s->fresh()->nextSendDate()->toDateString());
    }

    public function test_dispatch_goes_through_the_queue_once(): void
    {
        Queue::fake();
        $this->schedule();
        (new QueueScheduledReports)->handle();
        (new QueueScheduledReports)->handle();
        Queue::assertPushed(SendScheduledReport::class, 1);
        $this->assertSame(2, ReportSend::where('status', 'queued')->count());
    }

    public function test_only_due_schedules_send(): void
    {
        Mail::fake();
        $this->schedule(['monthly_day' => 20]);                         // later this month
        $second = Client::where('agency_id', $this->agency->id)->where('id', '!=', $this->client->id)->first();
        $later = $this->schedule(['monthly_day' => 5, 'client_id' => $second->id]); // created after its send date → next month
        $later->forceFill(['created_at' => now()->subDay()])->save();
        (new QueueScheduledReports)->handle();
        Mail::assertNothingSent();
        $this->assertSame('2026-12-05', $later->fresh()->nextSendDate()->toDateString());
    }

    public function test_weekly_summary_and_hidden_spend(): void
    {
        Mail::fake();
        $this->client->update(['hide_spend' => true]);
        $this->schedule(['monthly_day' => null, 'weekly_day' => 2, 'recipients' => ['a@example.com']]); // Tuesday = today

        (new QueueScheduledReports)->handle();

        Mail::assertSent(ScheduledReportMail::class, function (ScheduledReportMail $m) {
            $html = $m->render();

            return $m->kind === 'weekly' && $m->period === '2026-W46' && $m->pdf === null
                && str_contains($html, 'Impressions') && ! str_contains($html, 'Spend') && ! str_contains($html, 'Cost per result')
                && str_contains($html, '/reports/unsubscribe/');
        });
    }

    public function test_arabic_email_is_rtl(): void
    {
        $s = $this->schedule(['lang' => 'ar']);
        $m = new ScheduledReportMail($s, 'monthly', '2026-10', null, null, $s->unsubscribeUrl('a@example.com'));
        $html = $m->locale('ar')->render();
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('أوقف هذه الرسائل', $html);
        $this->assertStringContainsString('<bdi dir="ltr">'.e($this->client->name).'</bdi>', $html);
    }

    public function test_unsubscribe_is_honoured(): void
    {
        Mail::fake();
        $s = $this->schedule();
        $url = $s->unsubscribeUrl('B@example.com');

        $this->get($url)->assertOk()->assertSee('Stop these emails');
        $this->get($url.'x')->assertForbidden();
        $this->post($url)->assertOk()->assertSee('will not get');
        $this->assertDatabaseHas('report_unsubscribes', ['report_schedule_id' => $s->id, 'email' => 'b@example.com']);

        (new QueueScheduledReports)->handle();
        Mail::assertSent(ScheduledReportMail::class, 1);
        Mail::assertNotSent(ScheduledReportMail::class, fn ($m) => $m->hasTo('b@example.com'));
        $this->assertSame(['a@example.com'], $s->fresh()->activeRecipients());
    }

    public function test_unsubscribe_after_queueing_skips_the_send(): void
    {
        Queue::fake();
        Mail::fake();
        $s = $this->schedule();
        (new QueueScheduledReports)->handle();
        $this->post($s->unsubscribeUrl('a@example.com'))->assertOk();
        (new SendScheduledReport($s->id, 'monthly', '2026-10'))->handle();
        Mail::assertSent(ScheduledReportMail::class, 1);
        $this->assertDatabaseHas('report_sends', ['email' => 'a@example.com', 'status' => 'skipped']);
    }

    public function test_tier_gate_switch_and_suspended_workspaces(): void
    {
        Mail::fake();
        $this->schedule();

        config(['plan_features.features.scheduled_reports.tier' => 'pro']);
        (new QueueScheduledReports)->handle();
        Mail::assertNothingSent();

        config(['plan_features.features.scheduled_reports.tier' => 'starter', 'reports.email_enabled' => false]);
        (new QueueScheduledReports)->handle();
        Mail::assertNothingSent();

        config(['reports.email_enabled' => true]);
        $this->agency->update(['status' => 'suspended']);
        (new QueueScheduledReports)->handle();
        Mail::assertNothingSent();

        $this->agency->update(['status' => 'active']);
        (new QueueScheduledReports)->handle();
        Mail::assertSent(ScheduledReportMail::class, 2);
    }

    public function test_locked_plan_shows_the_locked_state(): void
    {
        config(['plan_features.features.scheduled_reports.tier' => 'pro']);
        $this->page($this->member('owner'))->mountAction('emailReports')->assertSee(__('plan.available_on', ['tier' => 'Pro']), false);
    }

    public function test_tenant_isolation(): void
    {
        Mail::fake();
        $other = Agency::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active', 'timezone' => 'Asia/Amman']);
        $otherClient = Client::create(['agency_id' => $other->id, 'name' => 'Other Co', 'currency' => 'USD']);
        $theirs = ReportSchedule::create(['agency_id' => $other->id, 'client_id' => $otherClient->id, 'monthly_day' => 10, 'lang' => 'en',
            'recipients' => ['x@example.com'], 'active' => true]);
        $theirs->forceFill(['created_at' => now()->subDays(20)])->save();
        $mine = $this->schedule();

        // Unsubscribing from Acme's schedule never touches the other workspace's.
        $this->post($mine->unsubscribeUrl('x@example.com'))->assertOk();
        (new QueueScheduledReports)->handle();
        Mail::assertSent(ScheduledReportMail::class, fn ($m) => $m->hasTo('x@example.com') && $m->schedule->client_id === $otherClient->id);
        Mail::assertSent(ScheduledReportMail::class, fn ($m) => $m->schedule->client->agency_id === $this->agency->id && $m->hasTo('a@example.com'));
        $this->assertSame(0, ReportSend::where('agency_id', $this->agency->id)->where('client_id', $otherClient->id)->count());

        // Acme's members cannot open the other workspace's client page.
        $owner = $this->member('owner');
        $this->assertContains($this->actingAs($owner)->get('/app/other/clients/'.$otherClient->id)->status(), [403, 404]);
        $this->actingAs($owner)->get('/app/acme/clients/'.$otherClient->id)->assertNotFound();
    }
}
