<?php

namespace App\Support\Ops;

/**
 * EG-08: find the log lines for an error reference shown on a 500 page (UX-9), in laravel.log and the rolled .gz files.
 * Super admin only; returns the matching entry and the lines that follow it (stack trace), capped.
 */
class ErrorLookup
{
    /** @return list<array{file: string, lines: list<string>}> */
    public static function find(string $ref, int $maxEntries = 5, int $linesPerEntry = 30): array
    {
        $ref = strtoupper(trim($ref));
        if (! preg_match('/^[A-Z0-9]{6,16}$/', $ref)) {
            return [];
        }
        $dir = storage_path('logs');
        $files = array_merge([$dir.'/laravel.log'], array_reverse(glob($dir.'/laravel-*.log.gz') ?: []));
        $out = [];
        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }
            $fh = str_ends_with($file, '.gz') ? gzopen($file, 'rb') : fopen($file, 'rb');
            if (! $fh) {
                continue;
            }
            $read = str_ends_with($file, '.gz') ? fn () => gzgets($fh) : fn () => fgets($fh);
            $current = null;
            while (($line = $read()) !== false) {
                $isEntry = (bool) preg_match('/^\[\d{4}-\d\d-\d\d/', $line);
                if ($isEntry) {
                    if ($current !== null) {
                        $out[] = $current;
                        $current = null;
                        if (count($out) >= $maxEntries) {
                            break;
                        }
                    }
                    if (str_contains($line, '"ref":"'.$ref.'"') || str_contains($line, $ref)) {
                        $current = ['file' => basename($file), 'lines' => [rtrim(mb_substr($line, 0, 600))]];
                    }
                } elseif ($current !== null && count($current['lines']) < $linesPerEntry) {
                    $current['lines'][] = rtrim(mb_substr($line, 0, 300));
                }
            }
            if ($current !== null && count($out) < $maxEntries) {
                $out[] = $current;
            }
            str_ends_with($file, '.gz') ? gzclose($fh) : fclose($fh);
            if (count($out) >= $maxEntries) {
                break;
            }
        }

        return $out;
    }
}
