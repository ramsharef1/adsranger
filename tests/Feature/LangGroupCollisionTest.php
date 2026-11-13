<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A bare key like __('Plan') loads lang/{locale}/plan.php on case-insensitive filesystems (macOS APFS)
 * and returns an array instead of a string. Group files must be reached by their dotted keys only.
 */
class LangGroupCollisionTest extends TestCase
{
    public function test_no_bare_translation_key_matches_a_lang_group_file(): void
    {
        $groups = collect(File::files(lang_path('en')))
            ->map(fn ($file): string => strtolower($file->getFilenameWithoutExtension()))
            ->all();

        $collisions = [];

        foreach ([app_path(), resource_path('views')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                preg_match_all("/(?:__|trans|@lang)\\(\\s*['\"]([A-Za-z_]+)['\"]\\s*[,)]/", $file->getContents(), $matches);

                foreach ($matches[1] as $key) {
                    if (in_array(strtolower($key), $groups, true)) {
                        $collisions[] = $file->getRelativePathname().": {$key}";
                    }
                }
            }
        }

        $this->assertSame([], $collisions);
    }

    public function test_plan_label_renders_the_same_copy_in_both_locales(): void
    {
        $this->assertSame('Plan', __('plan.plan', [], 'en'));
        $this->assertSame('الباقة', __('plan.plan', [], 'ar'));
        $this->assertSame('الباقة', __('Plan', [], 'ar'));
    }
}
