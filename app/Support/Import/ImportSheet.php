<?php

namespace App\Support\Import;

use OpenSpout\Reader\XLSX\Reader;
use RuntimeException;

/** PS-01 (D-295): one reader for CSV text, Google Sheets CSV and .xlsx files (OpenSpout, streamed). Rows come back as lists of strings. */
class ImportSheet
{
    /** @return list<list<string>> */
    public static function fromCsv(string $csv, int $max = 50001): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $csv));
        $lines = array_values(array_filter(explode("\n", $csv), fn ($l) => trim($l) !== ''));
        if ($lines === []) {
            return [];
        }
        // The delimiter is read from the busiest of the first lines (Google / TikTok exports start with a title line).
        $probe = implode("\n", array_slice($lines, 0, 8));
        $delim = substr_count($probe, ';') > substr_count($probe, ',') ? ';' : (substr_count($probe, "\t") > substr_count($probe, ',') ? "\t" : ',');
        $rows = [];
        foreach ($lines as $i => $line) {
            if ($i >= $max) {
                throw new RuntimeException(__('Too many rows (max :n per import).', ['n' => $max - 1]));
            }
            $rows[] = array_map(fn ($v) => (string) $v, str_getcsv($line, $delim, '"', ''));
        }

        return $rows;
    }

    /** @return list<list<string>> first sheet of an .xlsx file */
    public static function fromXlsx(string $path, int $max = 50001): array
    {
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = array_map(function ($v) {
                        if ($v instanceof \DateTimeInterface) {
                            return $v->format('Y-m-d');
                        }

                        return is_float($v) ? rtrim(rtrim(sprintf('%.6F', $v), '0'), '.') : (string) $v;
                    }, $row->toArray());
                    if (implode('', $cells) === '') {
                        continue;
                    }
                    if (count($rows) >= $max) {
                        throw new RuntimeException(__('Too many rows (max :n per import).', ['n' => $max - 1]));
                    }
                    $rows[] = $cells;
                }
                break; // first sheet only
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /** By file name: .xlsx is read as a workbook, everything else as CSV text. @return list<list<string>> */
    public static function fromFile(string $path, string $name): array
    {
        return str_ends_with(strtolower($name), '.xlsx') ? self::fromXlsx($path) : self::fromCsv((string) file_get_contents($path));
    }
}
