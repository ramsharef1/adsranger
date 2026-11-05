<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Arabic nav groups, page titles and resource labels (follow-up to UX-3). */
class ArabicLabelsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $a = Agency::create(['name' => 'Acme', 'slug' => 'acme', 'plan' => 'agency', 'status' => 'active']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'owner']);

        return [$a, $u];
    }

    public function test_nav_and_titles_are_arabic_by_default(): void
    {
        [$a, $u] = $this->owner();
        $this->actingAs($u)->get("/app/{$a->slug}/pacing")->assertOk()
            ->assertSee('وتيرة الإنفاق ومؤشر الصحة')
            ->assertSee('التخطيط')
            ->assertSee('المكتب اليومي')
            ->assertSee('الباقة والاستخدام')
            ->assertSee('أعضاء الفريق')
            ->assertDontSee('>Pacing &amp; health<', false);
    }

    public function test_english_stays_english(): void
    {
        [$a, $u] = $this->owner();
        $this->withSession(['locale' => 'en'])->actingAs($u)->get("/app/{$a->slug}/pacing")->assertOk()
            ->assertSee('Pacing &amp; health', false)
            ->assertSee('Planning')
            ->assertDontSee('وتيرة الإنفاق ومؤشر الصحة');
    }

    public function test_every_label_has_an_arabic_translation(): void
    {
        app()->setLocale('ar');
        $ar = json_decode(file_get_contents(lang_path('ar.json')), true);
        $classes = [];
        foreach (array_merge(glob(app_path('Filament/Pages/*.php')), glob(app_path('Filament/Resources/*/*Resource.php')), glob(app_path('Filament/Admin/Resources/*/*Resource.php'))) as $f) {
            $classes[] = 'App\\'.str_replace(['/', '.php'], ['\\', ''], Str::after($f, app_path().'/'));
        }
        $missing = [];
        foreach ($classes as $c) {
            $labels = [$c::getNavigationLabel(), $c::getNavigationGroup()];
            if (method_exists($c, 'getModelLabel')) {
                $labels[] = $c::getModelLabel();
                $labels[] = $c::getPluralModelLabel();
            }
            foreach (array_filter($labels, 'is_string') as $l) {
                if (preg_match('/^[\x20-\x7E]+$/', $l) && $l !== 'Google Ads' && ! in_array($l, $ar, true)) {
                    $missing[] = "$c: $l";
                }
            }
        }
        $this->assertSame([], $missing, 'labels still in English under ar');
    }
}
