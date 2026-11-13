<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * A-5 (IDEA-043): the result of the last monthly restore test, written by the root script
 * scripts/ops/adsranger-restore-test.sh (installed at /usr/local/bin) to storage/app/restore-test.json.
 */
class RestoreTest
{
    public const MAX_AGE_DAYS = 35;

    /** @return array{at: string, ok: bool, backup: string, seconds: int, message: string, tables: list<array{table: string, restored: int, live: int}>}|null */
    public static function last(): ?array
    {
        $file = storage_path('app/restore-test.json');
        if (! is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) && isset($data['at'], $data['ok']) ? $data : null;
    }

    /** A problem line for the health check, or null when the last test passed and is recent. */
    public static function problem(): ?string
    {
        $last = static::last();
        if ($last === null) {
            return __('No restore test has run yet.');
        }
        if (! $last['ok']) {
            return __('The last restore test failed: :msg', ['msg' => $last['message']]);
        }
        if (Carbon::parse($last['at'])->diffInDays(now()) > self::MAX_AGE_DAYS) {
            return __('The last restore test is more than :days days old.', ['days' => self::MAX_AGE_DAYS]);
        }

        return null;
    }
}
