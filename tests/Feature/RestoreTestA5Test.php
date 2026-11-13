<?php

namespace Tests\Feature;

use App\Support\RestoreTest;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** A-5: the health check reads the root script's result file. */
class RestoreTestA5Test extends TestCase
{
    public function test_missing_failed_old_and_passing_results(): void
    {
        $file = storage_path('app/restore-test.json');
        $backup = is_file($file) ? File::get($file) : null;
        try {
            File::delete($file);
            $this->assertSame('No restore test has run yet.', RestoreTest::problem());

            File::put($file, json_encode(['at' => now()->toIso8601String(), 'ok' => false, 'backup' => 'adssaas-x.sql.gz', 'seconds' => 3, 'message' => 'restore failed: boom', 'tables' => []]));
            $this->assertStringContainsString('restore failed: boom', (string) RestoreTest::problem());

            File::put($file, json_encode(['at' => now()->subDays(40)->toIso8601String(), 'ok' => true, 'backup' => 'b', 'seconds' => 3, 'message' => 'restored and checked', 'tables' => []]));
            $this->assertStringContainsString('more than 35 days', (string) RestoreTest::problem());

            File::put($file, json_encode(['at' => now()->subDays(2)->toIso8601String(), 'ok' => true, 'backup' => 'b', 'seconds' => 3, 'message' => 'restored and checked', 'tables' => [['table' => 'users', 'restored' => 3, 'live' => 4]]]));
            $this->assertNull(RestoreTest::problem());
        } finally {
            $backup === null ? File::delete($file) : File::put($file, $backup);
        }
    }
}
