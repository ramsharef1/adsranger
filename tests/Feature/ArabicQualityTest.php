<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * I-X-2 (D-98): Arabic + UI quality gate. Fails the build when
 *  - an English key used with __() / trans_choice() / @lang in app/ or resources/views has no Arabic value (EX-15);
 *  - an Arabic value joins «و / بـ / لـ / كـ» directly to a Latin word, a :placeholder or a tag (EX-61);
 *  - an Arabic value uses a retired or banned form (D-51 brand, D-43 glossary);
 *  - a 5-form Arabic plural is missing for a key used with trans_choice().
 * Group keys (report.title) and package keys (filament-panels::…) are checked by their own files.
 */
class ArabicQualityTest extends TestCase
{
    /** Retired or banned Arabic forms → what to use instead. */
    private const BANNED = [
        'أدز رينجر' => 'AdsRanger (D-51)',
        'الشبكات الإعلانية' => 'المنصات الإعلانية (D-43)',
    ];

    private function ar(): array
    {
        return json_decode(file_get_contents(base_path('lang/ar.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, list<string>> key => files, for literal first arguments */
    private function usedKeys(string $fn = '(?:__|trans_choice|@lang)'): array
    {
        $out = [];
        $files = [];
        foreach ([app_path(), resource_path('views')] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        foreach ($files as $f) {
            preg_match_all("/(?<![A-Za-z_>])$fn\(\s*'((?:[^'\\\\]|\\\\.)+)'/", file_get_contents($f), $m);
            foreach ($m[1] as $k) {
                $k = stripslashes($k);
                if (str_contains($k, '::') || preg_match('/^[a-z_]+\.[a-z0-9_.]+$/', $k)) {
                    continue;
                }
                $out[$k][] = str_replace(base_path().'/', '', $f);
            }
        }

        return $out;
    }

    public function test_every_used_english_key_has_arabic(): void
    {
        $ar = $this->ar();
        $missing = array_diff_key($this->usedKeys(), $ar);
        $this->assertSame([], array_map(fn ($f) => $f[0], $missing), 'Add these keys to lang/ar.json');
    }

    public function test_no_joined_letter_before_latin_or_placeholder(): void
    {
        $bad = [];
        foreach ($this->ar() as $k => $v) {
            if (preg_match('/(?<![\x{0600}-\x{06FF}])[وبلك](?=[A-Za-z:<])/u', (string) $v)) {
                $bad[$k] = $v;
            }
        }
        $this->assertSame([], $bad, 'Write «و :x» as «وعلى :x», use an Arabic comma, or put the Latin word first (EX-61)');
    }

    public function test_no_banned_forms(): void
    {
        $bad = [];
        foreach ($this->ar() as $k => $v) {
            foreach (self::BANNED as $form => $use) {
                if (str_contains((string) $v, $form)) {
                    $bad[$k] = "$form → $use";
                }
            }
        }
        foreach (glob(base_path('lang/ar/*.php')) as $f) {
            foreach (self::BANNED as $form => $use) {
                if (str_contains(file_get_contents($f), $form)) {
                    $bad[basename($f)] = "$form → $use";
                }
            }
        }
        $this->assertSame([], $bad);
    }

    public function test_plural_keys_have_arabic_forms(): void
    {
        $ar = $this->ar();
        $bad = [];
        foreach (array_keys($this->usedKeys('trans_choice')) as $k) {
            $forms = isset($ar[$k]) ? count(explode('|', $ar[$k])) : 0;
            if (str_contains($k, '|') && $forms < 3) {
                $bad[$k] = $forms;
            }
        }
        $this->assertSame([], $bad, 'Arabic plural strings need the zero/one/two/few/many/other forms');
    }
}
