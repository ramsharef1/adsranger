<?php

namespace App\Support\Ops;

use App\Models\PlatformSetting;
use App\Support\PlatformHealth;

/**
 * EG-02: `laravel.log` is one file that grows forever. Daily check: when it passes the size limit, or on Sundays, it is
 * compressed to `laravel-<date>.log.gz`, a new empty log starts and the health-check offset resets. Keeps N old files.
 */
class LogRoll
{
    /** @return array{rolled: bool, file: ?string, removed: int} */
    public static function run(bool $force = false): array
    {
        $dir = storage_path('logs');
        $log = $dir.'/laravel.log';
        $size = is_file($log) ? (int) filesize($log) : 0;
        $limit = (int) config('ops.log_roll_mb', 20) * 1024 * 1024;
        $file = null;
        if ($size > 0 && ($force || $size >= $limit || now()->isSunday())) {
            $file = $dir.'/laravel-'.now()->format('Ymd-His').'.log.gz';
            $in = fopen($log, 'rb');
            $out = gzopen($file, 'wb6');
            if ($in && $out) {
                while (! feof($in)) {
                    gzwrite($out, (string) fread($in, 1024 * 512));
                }
                fclose($in);
                gzclose($out);
                file_put_contents($log, '');
                PlatformSetting::put(PlatformHealth::OFFSET_KEY, 0);
            } else {
                $file = null;
            }
        }
        $old = glob($dir.'/laravel-*.log.gz') ?: [];
        rsort($old);
        $removed = 0;
        foreach (array_slice($old, (int) config('ops.log_keep', 8)) as $f) {
            $removed += @unlink($f) ? 1 : 0;
        }

        return ['rolled' => $file !== null, 'file' => $file ? basename($file) : null, 'removed' => $removed];
    }
}
