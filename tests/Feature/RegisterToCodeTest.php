<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** A-12 (FR-029): every Done row in config/feature_register.php points at real code and a real test. */
class RegisterToCodeTest extends TestCase
{
    public function test_every_done_row_has_code_and_a_test(): void
    {
        $rows = config('feature_register');
        $this->assertNotEmpty($rows);
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->all();
        $missing = [];

        foreach ($rows as $id => $row) {
            if (empty($row['code']) || empty($row['tests'])) {
                $missing[] = "$id: needs at least one code anchor and one test";

                continue;
            }
            foreach ($row['code'] as $anchor) {
                $ok = match (true) {
                    str_starts_with($anchor, 'view:') => view()->exists(substr($anchor, 5)),
                    str_starts_with($anchor, 'route:') => in_array(substr($anchor, 6), $uris, true),
                    str_starts_with($anchor, 'config:') => is_file(config_path(substr($anchor, 7).'.php')),
                    default => class_exists($anchor),
                };
                if (! $ok) {
                    $missing[] = "$id: code $anchor";
                }
            }
            foreach ($row['tests'] as $test) {
                $file = base_path('tests/'.$test);
                if (! is_file($file) || ! preg_match('/function test_\w+\(/', (string) file_get_contents($file))) {
                    $missing[] = "$id: test $test";
                }
            }
        }

        $this->assertSame([], $missing, 'Register rows marked Done without code or a test');
    }
}
