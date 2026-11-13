<?php

namespace Tests\Feature;

use App\Filament\Pages\ExportData;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\User;
use App\Support\DataExport;
use App\Support\DemoData;
use App\Support\TwoPerson;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** #79 data export: CSV (UTF-8 + BOM), role and tenant scoping, hide_spend, workspace ZIP, audit. */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private array $clients;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create(['name' => 'Acme Agency', 'slug' => 'acme', 'plan' => 'starter', 'status' => 'active', 'language' => 'en']);
        DemoData::seed($this->agency, 20);
        $this->clients = Client::where('agency_id', $this->agency->id)->orderBy('id')->get()->all();
    }

    private function member(string $role, ?Agency $agency = null): User
    {
        $u = User::factory()->create();
        // Filament attaches users created after a request to the last tenant (MemberResource); start clean.
        DB::table('agency_user')->where('user_id', $u->id)->delete();
        ($agency ?? $this->agency)->members()->attach($u->id, ['role' => $role]);

        return $u;
    }

    private function url(string $name, array $q = []): string
    {
        return route('filament.app.export.'.$name, ['tenant' => 'acme'] + $q);
    }

    private function period(): array
    {
        return ['from' => now()->subDays(25)->toDateString(), 'to' => now()->toDateString()];
    }

    private function csv(User $u, string $name, array $q = [], string $locale = 'en'): array
    {
        $r = $this->withSession(['locale' => $locale])->actingAs($u)->get($this->url($name, $q));
        $r->assertOk();
        $body = $r->streamedContent();
        $this->assertStringStartsWith(DataExport::BOM, $body);
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $lines = array_values(array_filter(explode("\n", substr($body, 3))));
        // PS-06: this workspace runs on sample data, so the daily file opens with the sample-data mark; the rows follow it.
        if (isset($lines[0]) && str_contains($lines[0], DemoData::LABEL)) {
            array_shift($lines);
        }

        return array_map(fn ($l) => str_getcsv($l, ',', '"', ''), $lines);
    }

    public function test_client_csv_has_bom_headers_and_daily_rows(): void
    {
        $owner = $this->member('owner');
        $c = $this->clients[0];
        $rows = $this->csv($owner, 'metrics', ['client' => $c->id] + $this->period());

        $head = $rows[0];
        $this->assertSame('Date', $head[0]);
        $this->assertContains('Spend', $head);
        $this->assertContains('Campaign', $head);
        $this->assertNotContains('Client', $head, 'a per-client file has no client column');
        $this->assertGreaterThan(5, count($rows));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $rows[1][0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.csv', 'subject_type' => Client::class, 'subject_id' => $c->id]);
    }

    public function test_arabic_headers_when_the_panel_is_arabic(): void
    {
        $rows = $this->csv($this->member('owner'), 'metrics', ['client' => $this->clients[0]->id] + $this->period(), 'ar');
        $this->assertContains('الإنفاق', $rows[0]);
        $this->assertContains('العميل', $this->csv($this->member('admin'), 'clients', [], 'ar')[0]);
    }

    public function test_a_copy_for_the_client_follows_hide_spend(): void
    {
        $owner = $this->member('owner');
        $c = $this->clients[0];
        $c->update(['hide_spend' => true]);

        $internal = $this->csv($owner, 'metrics', ['client' => $c->id] + $this->period());
        $this->assertContains('Spend', $internal[0], 'the agency\'s own copy keeps spend');

        $forClient = $this->csv($owner, 'metrics', ['client' => $c->id, 'for_client' => 1] + $this->period());
        $this->assertNotContains('Spend', $forClient[0]);
        $this->assertNotContains('Cost per result', $forClient[0]);
        $this->assertContains('Impressions', $forClient[0]);
        $this->assertSame(count($internal[0]) - 2, count($forClient[0]));
        foreach (array_slice($forClient, 1) as $row) {
            $this->assertCount(count($forClient[0]), $row);
        }
    }

    public function test_client_link_csv_is_client_facing(): void
    {
        $c = $this->clients[0];
        $link = ClientLink::issue($c);
        $r = $this->get('/r/'.$link->token.'/csv?lang=en');
        $r->assertOk();
        $this->assertStringStartsWith(DataExport::BOM, $r->streamedContent());
        $this->assertStringContainsString('Spend', $r->streamedContent());

        $c->update(['hide_spend' => true]);
        $body = $this->get('/r/'.$link->token.'/csv?lang=en')->streamedContent();
        $this->assertStringNotContainsString('Spend', $body);
        $this->assertStringContainsString('Impressions', $body);

        $link->forceFill(['revoked_at' => now()])->save();
        $this->get('/r/'.$link->token.'/csv')->assertNotFound();
    }

    public function test_workspace_metrics_cover_every_client_for_owners(): void
    {
        $rows = $this->csv($this->member('owner'), 'metrics', $this->period());
        $this->assertSame('Client', $rows[0][1]);
        $names = array_unique(array_column(array_slice($rows, 1), 1));
        foreach ($this->clients as $c) {
            $this->assertContains($c->name, $names);
        }
    }

    public function test_client_restricted_manager_only_exports_their_clients(): void
    {
        [$mine, $theirs] = [$this->clients[0], $this->clients[1]];
        $manager = $this->member('manager');
        $other = $this->member('manager');
        $mine->users()->attach($manager->id);
        $theirs->users()->attach($other->id);

        $names = array_unique(array_column(array_slice($this->csv($manager, 'metrics', $this->period()), 1), 1));
        $this->assertContains($mine->name, $names);
        $this->assertNotContains($theirs->name, $names);

        $this->withSession(['locale' => 'en'])->actingAs($manager)->get($this->url('metrics', ['client' => $theirs->id]))->assertNotFound();

        $clientNames = array_column(array_slice($this->csv($manager, 'clients'), 1), 0);
        $this->assertContains($mine->name, $clientNames);
        $this->assertNotContains($theirs->name, $clientNames);

        $accountClients = array_column(array_slice($this->csv($manager, 'accounts'), 1), 3);
        $this->assertNotContains($theirs->name, $accountClients);
        $this->assertContains($mine->name, $accountClients);
    }

    public function test_analyst_exports_what_they_can_see(): void
    {
        $analyst = $this->member('analyst');
        $rows = $this->csv($analyst, 'clients');
        $this->assertCount(count($this->clients) + 1, $rows);
    }

    public function test_other_workspaces_cannot_export(): void
    {
        $other = Agency::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);
        $stranger = $this->member('owner', $other);
        $r = $this->actingAs($stranger)->get($this->url('clients'));
        $this->assertContains($r->status(), [403, 404]);

        // their own workspace export never includes Acme's clients
        $r = $this->withSession(['locale' => 'en'])->actingAs($stranger)->get(route('filament.app.export.metrics', ['tenant' => 'other'] + $this->period()));
        $r->assertOk();
        $this->assertCount(1, array_filter(explode("\n", $r->streamedContent())));

        $this->app['auth']->forgetGuards();
        $this->get($this->url('clients'))->assertRedirect();
    }

    public function test_period_is_validated(): void
    {
        $owner = $this->member('owner');
        $this->actingAs($owner)->get($this->url('metrics', ['from' => '2026-10-10', 'to' => '2026-10-01']))->assertStatus(422);
        $this->actingAs($owner)->get($this->url('metrics', ['from' => '2020-01-01', 'to' => '2026-10-01']))->assertStatus(422);
    }

    public function test_workspace_zip_is_for_the_owner(): void
    {
        $admin = $this->member('admin');
        $this->actingAs($admin)->get($this->url('workspace'))->assertForbidden();

        // DS-35 (D-305): owner + admin in the workspace, so the full download needs the admin's approval first.
        $owner = $this->member('owner');
        $this->actingAs($owner)->get($this->url('workspace'))->assertForbidden();
        $req = TwoPerson::request($this->agency, 'export_workspace', null, null, $owner);
        $this->assertSame('admin', $admin->roleIn($this->agency));
        $this->assertTrue(TwoPerson::decide($req->fresh(), $admin, true));

        $r = $this->actingAs($owner)->get($this->url('workspace'));
        $r->assertOk();
        $path = $r->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        foreach (['clients.csv', 'ad_accounts.csv', 'daily_metrics.csv', 'campaigns.csv', 'members.csv', 'README.txt'] as $f) {
            $this->assertNotFalse($zip->locateName($f), $f);
        }
        $this->assertStringStartsWith(DataExport::BOM, $zip->getFromName('clients.csv'));
        $this->assertStringNotContainsString('password', strtolower($zip->getFromName('members.csv')));
        $zip->close();
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.csv', 'subject_type' => Agency::class]);
        $this->actingAs($owner)->get($this->url('workspace'))->assertForbidden(); // an approval is used once
    }

    public function test_formulas_are_neutralised(): void
    {
        $this->assertSame("'=HYPERLINK(1)", DataExport::cell('=HYPERLINK(1)'));
        $this->assertSame('-12.5', DataExport::cell('-12.5'));
        $this->assertSame('Brand', DataExport::cell('Brand'));
    }

    public function test_export_page_and_actions(): void
    {
        $owner = $this->member('owner');
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get('/app/acme/export-data')->assertOk()
            ->assertSee('Download daily metrics')->assertSee('Download all workspace data');
        $this->withSession(['locale' => 'en'])->actingAs($this->member('analyst'))->get('/app/acme/export-data')->assertOk()
            ->assertDontSee('Download all workspace data');

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->agency);
        $this->actingAs($owner);
        Livewire::test(ExportData::class)
            ->callAction('metrics', ['from' => now()->subDays(10)->toDateString(), 'to' => now()->subDay()->toDateString()])
            ->assertHasNoActionErrors()
            ->assertRedirectContains('/app/acme/export/daily-metrics.xlsx'); // DB-04: Excel is the default format
    }

    public function test_owner_can_self_serve_delete_workspace_with_password_and_slug(): void
    {
        config(['modules.enabled.self_serve_deletion' => true]); // ADSR-110 0.2: held back by default
        $owner = $this->member('owner');
        $this->withSession(['locale' => 'en'])->actingAs($owner)->get('/app/acme/export-data')->assertOk()
            ->assertSee('Delete workspace');

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->agency);
        $this->actingAs($owner);

        Livewire::test(ExportData::class)
            ->callAction('deleteWorkspace', ['password' => 'wrong-pass', 'confirm_slug' => 'acme'])
            ->assertHasActionErrors(['password']);

        Livewire::test(ExportData::class)
            ->callAction('deleteWorkspace', ['password' => 'password', 'confirm_slug' => 'wrong-slug'])
            ->assertHasActionErrors(['confirm_slug']);

        Livewire::test(ExportData::class)
            ->callAction('deleteWorkspace', ['password' => 'password', 'confirm_slug' => 'acme'])
            ->assertHasNoActionErrors()
            ->assertRedirect('/');

        $this->assertSoftDeleted('agencies', ['id' => $this->agency->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'agency.deleted', 'subject_type' => Agency::class, 'subject_id' => $this->agency->id]);
    }

    public function test_member_can_self_serve_delete_personal_account(): void
    {
        config(['modules.enabled.self_serve_deletion' => true]); // ADSR-110 0.2: held back by default
        $analyst = $this->member('analyst');
        $analystId = $analyst->id;

        $this->withSession(['locale' => 'en'])->actingAs($analyst)->get('/app/acme/export-data')->assertOk()
            ->assertSee('Delete my account')
            ->assertDontSee('Delete workspace');

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($this->agency);
        $this->actingAs($analyst);

        Livewire::test(ExportData::class)
            ->callAction('deleteAccount', ['password' => 'password', 'confirm_word' => 'NOPE'])
            ->assertHasActionErrors(['confirm_word']);

        Livewire::test(ExportData::class)
            ->callAction('deleteAccount', ['password' => 'password', 'confirm_word' => 'DELETE'])
            ->assertHasNoActionErrors()
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $analystId]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deleted', 'subject_type' => User::class, 'subject_id' => $analystId]);
    }
}
