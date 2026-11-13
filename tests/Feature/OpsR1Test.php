<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\OperationsBoard;
use App\Jobs\DailySync;
use App\Jobs\RunNightShift;
use App\Jobs\SendScheduledReport;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Ops\CertExpiry;
use App\Support\Ops\ConfigDrift;
use App\Support\Ops\ErrorLookup;
use App\Support\Ops\Heartbeat;
use App\Support\Ops\JobRuns;
use App\Support\Ops\LaunchReadiness;
use App\Support\Ops\LogRoll;
use App\Support\Ops\Prune;
use App\Support\PlatformHealth;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** cl110: R1 ops + safety (D-289) — EG-01, 02, 03, 04, 05, 06, 07, 08, 12, 13, 14. */
class OpsR1Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CertExpiry::$reader = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_heartbeat_endpoint_is_200_when_fresh_and_503_when_stale(): void
    {
        $this->getJson('/up/heartbeat')->assertStatus(503)->assertJson(['ok' => false, 'last' => null]);
        Heartbeat::beat();
        $this->getJson('/up/heartbeat')->assertOk()->assertJson(['ok' => true, 'age_minutes' => 0]);
        Carbon::setTestNow(now()->addMinutes(20));
        $this->getJson('/up/heartbeat')->assertStatus(503);
    }

    public function test_queue_lanes_only_when_switched_on(): void
    {
        $this->assertNull((new DailySync)->queue);
        config(['ops.lanes_enabled' => true]);
        $this->assertSame('high', (new DailySync)->queue);
        $this->assertSame('slow', (new RunNightShift)->queue);
        $this->assertSame('default', (new SendScheduledReport(1, 'monthly', '2026-10'))->queue);
    }

    public function test_job_runs_record_tasks_and_the_board_flags_late_and_failed(): void
    {
        JobRuns::track('ops-heartbeat', fn () => Heartbeat::beat());
        try {
            JobRuns::track('fx-pull', fn () => throw new \RuntimeException('rate limited'));
        } catch (\RuntimeException) {
        }
        $board = collect(JobRuns::board())->keyBy('name');
        $this->assertSame('ok', $board['ops-heartbeat']['status']);
        $this->assertSame(1, $board['ops-heartbeat']['runs']);
        $this->assertSame('failed', $board['fx-pull']['status']);
        $this->assertStringContainsString('rate limited', $board['fx-pull']['error']);
        $this->assertContains('fx-pull', JobRuns::problems());

        // A daily task whose last run is two days old is late.
        DB::table('job_runs')->insert(['job' => 'DailySync', 'last_started_at' => now()->subDays(2), 'last_finished_at' => now()->subDays(2), 'last_status' => 'ok', 'runs' => 1]);
        $this->assertTrue(collect(JobRuns::board())->firstWhere('name', 'daily-sync')['missed']);
    }

    public function test_prune_deletes_ops_rows_only(): void
    {
        DB::table('failed_jobs')->insert(['uuid' => 'a', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDays(40)]);
        DB::table('failed_jobs')->insert(['uuid' => 'b', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDay()]);
        DB::table('missing_pages')->insert(['path' => '/old', 'hits' => 1, 'last_seen_at' => now()->subDays(200), 'ignored' => false]);
        DB::table('missing_pages')->insert(['path' => '/kept', 'hits' => 1, 'last_seen_at' => now()->subDays(200), 'ignored' => true]);
        $agencies = DB::table('agencies')->count();
        $out = Prune::run();
        $this->assertSame(1, $out['failed_jobs']);
        $this->assertSame(1, $out['missing_pages']);
        $this->assertSame(['b'], DB::table('failed_jobs')->pluck('uuid')->all());
        $this->assertTrue(DB::table('missing_pages')->where('path', '/kept')->exists(), 'an ignored path is a decision, kept');
        $this->assertSame($agencies, DB::table('agencies')->count());
        $this->assertNotNull(PlatformSetting::get(Prune::LAST_KEY));
    }

    public function test_log_roll_compresses_keeps_n_and_resets_the_health_offset(): void
    {
        $dir = storage_path('logs');
        $log = $dir.'/laravel.log';
        $backup = is_file($log) ? file_get_contents($log) : null;
        foreach (glob($dir.'/laravel-*.log.gz') ?: [] as $f) {
            rename($f, $f.'.keep');
        }
        try {
            for ($i = 1; $i <= 10; $i++) {
                file_put_contents($dir.'/laravel-2026010'.($i % 10).'-0000'.$i.'.log.gz', gzencode('x'));
            }
            file_put_contents($log, "[2026-11-13 10:00:00] production.ERROR: boom {\"ref\":\"AB12CD34\"}\n#0 trace line\n");
            PlatformSetting::put(PlatformHealth::OFFSET_KEY, 999);
            $r = LogRoll::run(true);
            $this->assertTrue($r['rolled']);
            $this->assertSame('', file_get_contents($log));
            $this->assertSame(0, (int) PlatformSetting::get(PlatformHealth::OFFSET_KEY));
            $this->assertCount(8, glob($dir.'/laravel-*.log.gz'));
            $found = ErrorLookup::find('ab12cd34');
            $this->assertCount(1, $found, 'the reference is found inside the rolled .gz file');
            $this->assertStringContainsString('#0 trace line', implode("\n", $found[0]['lines']));
            $this->assertSame([], ErrorLookup::find('not a ref!'));
        } finally {
            foreach (glob($dir.'/laravel-*.log.gz') ?: [] as $f) {
                unlink($f);
            }
            foreach (glob($dir.'/laravel-*.log.gz.keep') ?: [] as $f) {
                rename($f, substr($f, 0, -5));
            }
            $backup === null ? @unlink($log) : file_put_contents($log, $backup);
        }
    }

    public function test_config_drift_and_certificates_raise_health_problems(): void
    {
        config(['ops.force_checks' => true, 'app.debug' => true, 'app.env' => 'production', 'app.url' => 'https://adsranger.com',
            'session.secure' => true, 'ops.cert_hosts' => ['adsranger.com', 'mcp.adsranger.com'], 'queue.default' => 'database', 'mail.default' => 'smtp', 'health.check_urls' => false, 'health.restore_test' => false]);
        CertExpiry::$reader = fn (string $host) => $host === 'adsranger.com' ? now()->addDays(5)->addHour() : now()->addDays(60);
        $this->assertCount(2, ConfigDrift::problems(), 'debug on + mail not log');
        $kinds = collect(PlatformHealth::check())->pluck('text')->implode(' | ');
        $this->assertStringContainsString('APP_DEBUG', $kinds);
        $this->assertStringContainsString('D-266', $kinds);
        $this->assertStringContainsString('adsranger.com expires in 5 days', $kinds);
        $this->assertStringNotContainsString('mcp.adsranger.com expires', $kinds);
    }

    public function test_launch_checks_and_deploy_verify(): void
    {
        Heartbeat::beat();
        $checks = collect(LaunchReadiness::checks())->keyBy('key');
        $this->assertTrue($checks['heartbeat']['ok']);
        $this->assertTrue($checks['migrations']['ok']);
        $this->assertSame([], LaunchReadiness::pendingMigrations());
        $this->artisan('deploy:verify', ['--no-http' => true, '--since' => now()->subHour()->format('Y-m-d H:i:s')])
            ->expectsOutputToContain('pending migrations: 0')->expectsOutputToContain('queue lanes: off');
    }

    /** EG-12: every migration rolls back and runs again (a broken down() is found before a real rollback needs it). */
    public function test_migrations_roll_back_and_run_again(): void
    {
        $this->artisan('migrate:rollback', ['--step' => 12])->assertSuccessful();
        $this->artisan('migrate')->assertSuccessful();
        $this->assertSame([], LaunchReadiness::pendingMigrations());
    }

    public function test_operations_board_for_super_admin(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        JobRuns::track('ops-heartbeat', fn () => Heartbeat::beat());
        $this->get('/admin/operations')->assertOk()->assertSee('ops-heartbeat')->assertSee('/up/heartbeat');
        Livewire::test(OperationsBoard::class)->set('ref', 'ZZZZ9999')->call('lookup')->assertSet('found', []);
        $this->actingAs(User::factory()->create(['role' => 'agency_owner']));
        $this->get('/admin/operations')->assertForbidden();
    }
}
