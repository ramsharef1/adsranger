<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Support\PlatformHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** A-3: the health check reports new log errors and failed jobs once, and skips old log lines on its first run. */
class PlatformHealthA3Test extends TestCase
{
    use RefreshDatabase;

    public function test_new_errors_and_failed_jobs_are_reported_once(): void
    {
        config(['health.check_urls' => false, 'health.restore_test' => false, 'push.enabled' => false]);
        $log = storage_path('logs/laravel.log');
        $backup = is_file($log) ? File::get($log) : null;
        File::put($log, "[2026-11-12 10:00:00] production.ERROR: old problem\n");

        try {
            $this->assertSame([], PlatformHealth::check()); // first run starts from now

            File::append($log, "[2026-11-13 04:00:00] production.ERROR: SQLSTATE broke {\"exception\":\"x\"}\n[2026-11-13 04:00:01] production.INFO: fine\n");
            DB::table('failed_jobs')->insert(['uuid' => 'u-1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);

            $kinds = array_column(PlatformHealth::check(), 'kind');
            $this->assertSame(['log', 'failed_jobs'], $kinds);
            $this->assertSame(2, AuditLog::where('action', 'platform.health_alert')->count());

            // Same problem again within the hour: shown on the board, not re-alerted.
            File::append($log, "[2026-11-13 04:05:00] production.ERROR: again\n");
            PlatformHealth::check();
            $this->assertSame(2, AuditLog::where('action', 'platform.health_alert')->count());
            $this->assertNotEmpty(PlatformSetting::get(PlatformHealth::LAST_KEY)['problems']);
        } finally {
            $backup === null ? File::delete($log) : File::put($log, $backup);
        }
    }
}
