<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Releases\ReleaseResource;
use App\Models\Agency;
use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** #76 What's new page (/app/{agency}/whats-new) and /admin/releases resource. */
class WhatsNewTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $plan = 'starter', string $role = 'owner'): array
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-'.$plan.'-'.$role, 'plan' => $plan, 'status' => 'active']);
        $user = User::factory()->create();
        $agency->members()->attach($user->id, ['role' => $role]);

        return [$agency, $user];
    }

    public function test_whats_new_renders_empty_state_in_arabic_and_english_on_starter(): void
    {
        [$agency, $analyst] = $this->workspace('starter', 'analyst');

        $this->withSession(['locale' => 'ar'])->actingAs($analyst)
            ->get("/app/{$agency->slug}/whats-new")
            ->assertOk()
            ->assertSee('ما الجديد')
            ->assertSee('المزايا الجديدة وتحديثات الربط والتحسينات في AdsRanger.')
            ->assertSee('لم يُنشر أي إصدار بعد.');

        $this->withSession(['locale' => 'en'])->actingAs($analyst)
            ->get("/app/{$agency->slug}/whats-new")
            ->assertOk()
            ->assertSee("What's new")
            ->assertSee('No releases published yet.');
    }

    public function test_published_database_releases_render_and_unpublished_stay_hidden(): void
    {
        [$agency, $owner] = $this->workspace('starter', 'owner');

        Release::create([
            'version' => 'v2.0',
            'date' => '2026-11-05',
            'title_en' => 'Custom DB release title',
            'title_ar' => 'عنوان إصدار مخصص من قاعدة البيانات',
            'items' => ReleaseResource::packItems([
                'items_en' => "First English item\nSecond English item",
                'items_ar' => "النقطة الأولى بالعربية\nالنقطة الثانية بالعربية",
            ])['items'],
            'is_published' => true,
        ]);

        Release::create([
            'version' => 'v2.1-draft',
            'date' => '2026-11-06',
            'title_en' => 'Secret draft release',
            'title_ar' => 'إصدار مسودة سري',
            'items' => ['en' => ['Draft item'], 'ar' => ['نقطة مسودة']],
            'is_published' => false,
        ]);

        $this->withSession(['locale' => 'ar'])->actingAs($owner)
            ->get("/app/{$agency->slug}/whats-new")
            ->assertOk()
            ->assertSee('v2.0')
            ->assertSee('عنوان إصدار مخصص من قاعدة البيانات')
            ->assertSee('النقطة الأولى بالعربية')
            ->assertDontSee('لم يُنشر أي إصدار بعد.')
            ->assertDontSee('v2.1-draft')
            ->assertDontSee('إصدار مسودة سري');

        $this->withSession(['locale' => 'en'])->actingAs($owner)
            ->get("/app/{$agency->slug}/whats-new")
            ->assertOk()
            ->assertSee('Custom DB release title')
            ->assertSee('First English item')
            ->assertDontSee('No releases published yet.')
            ->assertDontSee('Secret draft release');
    }

    public function test_only_super_admin_can_access_release_resource(): void
    {
        [, $owner] = $this->workspace('agency', 'owner');
        $this->actingAs($owner);
        $this->assertFalse(ReleaseResource::canAccess());

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        $this->assertTrue(ReleaseResource::canAccess());
    }
}
