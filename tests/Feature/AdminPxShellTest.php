<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use App\Support\AdminShell;
use App\Support\Ops\Heartbeat;
use App\Support\Site\CopyLint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2-068: /admin dense PX command-center shell (4-area switcher + All, live telemetry ribbon,
 * compact/comfortable row density, AR/EN parity, Register-to-code guard viewer, and CR-01 token compliance).
 */
class AdminPxShellTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        return $admin;
    }

    private function adminPage(User $admin, string $path, string $locale = 'en'): string
    {
        AdminShell::forget();

        return $this->withSession(['locale' => $locale])
            ->actingAs($admin)
            ->get($path)
            ->assertOk()
            ->getContent();
    }

    public function test_telemetry_ribbon_renders_ground_truth_metrics_and_app_jump(): void
    {
        $admin = $this->superAdmin();
        Agency::create(['name' => 'Amman Alpha', 'slug' => 'amman-alpha', 'plan' => 'pro', 'status' => 'active', 'language' => 'ar']);
        Agency::create(['name' => 'Paused Co', 'slug' => 'paused-co', 'plan' => 'starter', 'status' => 'suspended', 'language' => 'en']);

        config([
            'modules.enabled.panel_shell' => true,
            'modules.preview_agencies' => ['testagency', 'amman-alpha'],
            'reach.cache_store' => 'redis',
        ]);

        $beforeBeat = $this->adminPage($admin, '/admin');
        $this->assertStringContainsString('data-admin-shell-ribbon', $beforeBeat);
        $this->assertStringContainsString('1/2', $beforeBeat);
        $this->assertStringContainsString('panel_shell', $beforeBeat);
        $this->assertStringContainsString('testagency, amman-alpha', $beforeBeat);
        $this->assertStringContainsString('No beat yet', $beforeBeat);
        $this->assertStringContainsString('>redis<', $beforeBeat);
        $this->assertStringContainsString('data-admin-jump-app', $beforeBeat);
        $this->assertStringContainsString(url('/app'), $beforeBeat);

        Heartbeat::beat();
        config(['reach.cache_store' => 'file']);

        $afterBeat = $this->adminPage($admin, '/admin');
        $this->assertMatchesRegularExpression('/data-telemetry="heartbeat".*?>OK</s', $afterBeat);
        $this->assertStringContainsString('>file<', $afterBeat);
    }

    public function test_area_switcher_filters_sidebar_by_area_and_all_exposes_every_route(): void
    {
        $admin = $this->superAdmin();

        $overview = $this->adminPage($admin, '/admin');
        $this->assertStringContainsString('data-admin-shell-areas', $overview);
        $this->assertMatchesRegularExpression('/data-admin-area="overview"[^>]*aria-current="page"/', $overview);
        $this->assertStringContainsString('/admin/live-verification', $overview);
        $this->assertStringContainsString('/admin/operations', $overview);
        $this->assertStringContainsString('/admin/ai-scorecard', $overview);
        $this->assertStringContainsString('/admin/costs-launch', $overview);

        $growth = $this->adminPage($admin, '/admin/waitlist');
        $this->assertMatchesRegularExpression('/data-admin-area="growth"[^>]*aria-current="page"/', $growth);
        $this->assertStringContainsString('/admin/agencies', $growth);
        $this->assertStringContainsString('/admin/customers', $growth);
        $this->assertStringContainsString('/admin/partner-applications', $growth);
        $this->assertStringContainsString('/admin/reach', $growth);
        $this->assertStringNotContainsString('/admin/tool-guard', $growth);

        $platform = $this->adminPage($admin, '/admin/platform-settings');
        $this->assertMatchesRegularExpression('/data-admin-area="platform"[^>]*aria-current="page"/', $platform);
        $this->assertStringContainsString('/admin/costs-launch', $platform);
        $this->assertStringContainsString('/admin/reindex-gate', $platform);
        $this->assertStringContainsString('/admin/links', $platform);
        $this->assertStringNotContainsString('/admin/waitlist', $platform);

        $guards = $this->adminPage($admin, '/admin/register-guard');
        $this->assertMatchesRegularExpression('/data-admin-area="guards"[^>]*aria-current="page"/', $guards);
        $this->assertStringContainsString('/admin/audit-log', $guards);
        $this->assertStringContainsString('/admin/tool-guard', $guards);
        $this->assertStringContainsString('/admin/users', $guards);
        $this->assertStringContainsString('FR-ADMIN-PX-SHELL', $guards);

        $all = $this->adminPage($admin, '/admin?area=all');
        $this->assertMatchesRegularExpression('/data-admin-area="all"[^>]*aria-current="page"/', $all);
        foreach ([
            '/admin/live-verification',
            '/admin/agencies',
            '/admin/waitlist',
            '/admin/reach',
            '/admin/platform-settings',
            '/admin/costs-launch',
            '/admin/reindex-gate',
            '/admin/audit-log',
            '/admin/tool-guard',
            '/admin/register-guard',
            '/admin/users',
        ] as $expectedPath) {
            $this->assertStringContainsString($expectedPath, $all, "All mode must list {$expectedPath}");
        }

        $this->actingAs(User::factory()->create())->get('/admin/register-guard')->assertForbidden();
    }

    public function test_density_toggle_switches_between_comfortable_and_compact_while_keeping_touch_targets(): void
    {
        $admin = $this->superAdmin();

        $comfortable = $this->adminPage($admin, '/admin');
        $this->assertStringContainsString('data-admin-density="comfortable"', $comfortable);
        $this->assertStringContainsString('Compact rows', $comfortable);
        $this->assertStringNotContainsString('.fi-sidebar-item-btn{min-height:34px}', $comfortable);
        $this->assertStringContainsString('--adm-touch:44px', $comfortable);

        $admin->forceFill(['ui_prefs' => ['density' => 'compact']])->save();

        $compact = $this->adminPage($admin->fresh(), '/admin');
        $this->assertStringContainsString('data-admin-density="compact"', $compact);
        $this->assertStringContainsString('Comfortable rows', $compact);
        $this->assertStringContainsString('.fi-sidebar-item-btn{min-height:34px}', $compact);
        $this->assertStringContainsString('ov--compact', $compact);
        $this->assertStringContainsString('min-height:52px;min-width:44px', $compact);
    }

    public function test_arabic_and_english_rendering_and_cr01_brand_token_compliance(): void
    {
        $admin = $this->superAdmin();
        Agency::create(['name' => 'وكالة الرياض', 'slug' => 'riyadh-agency', 'plan' => 'agency', 'status' => 'active', 'language' => 'ar']);
        config(['modules.preview_agencies' => ['riyadh-agency'], 'reach.cache_store' => 'redis']);

        $arDashboard = $this->adminPage($admin, '/admin?lang=ar', 'ar');
        $this->assertStringContainsString('lang="ar"', $arDashboard);
        $this->assertStringContainsString('dir="rtl"', $arDashboard);
        $this->assertStringContainsString('مساحات العمل النشطة', $arDashboard);
        $this->assertStringContainsString('التخزين المؤقت في <bdi dir="ltr">Reach</bdi>', $arDashboard);
        $this->assertStringContainsString('نظرة عامة', $arDashboard);
        $this->assertStringContainsString('الحماية والتدقيق', $arDashboard);
        $this->assertDoesNotMatchRegularExpression('/[٠-٩۰-۹]/u', $arDashboard);

        $arRegister = $this->adminPage($admin, '/admin/register-guard?lang=ar', 'ar');
        $this->assertStringContainsString('حارس مطابقة السجل بالكود', $arRegister);
        $this->assertDoesNotMatchRegularExpression('/[٠-٩۰-۹]/u', $arRegister);

        $enDashboard = $this->adminPage($admin, '/admin?lang=en', 'en');
        $this->assertStringContainsString('lang="en"', $enDashboard);
        $this->assertStringContainsString('dir="ltr"', $enDashboard);
        $this->assertStringContainsString('Active workspaces', $enDashboard);
        $this->assertStringContainsString('#0f172a', $enDashboard);
        $this->assertStringContainsString('#1e293b', $enDashboard);
        $this->assertStringContainsString('#0d9488', $enDashboard);
        $this->assertStringContainsString('#14b8a6', $enDashboard);

        $cr01Pattern = collect(CopyLint::textRules())->firstWhere('id', 'CR-01')['pattern'];
        $filesToCheck = [
            app_path('Support/AdminShell.php'),
            app_path('Filament/Admin/Pages/RegisterGuardBoard.php'),
            app_path('Filament/Admin/Widgets/SystemOverviewWidget.php'),
            resource_path('views/panels/admin-head.blade.php'),
            resource_path('views/panels/admin-shell-areas.blade.php'),
            resource_path('views/panels/admin-shell-ribbon.blade.php'),
            resource_path('views/panels/admin-shell-bottom.blade.php'),
            resource_path('views/filament/admin/pages/register-guard.blade.php'),
            resource_path('views/filament/admin/widgets/system-overview-widget.blade.php'),
        ];

        foreach ($filesToCheck as $file) {
            $contents = (string) file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression($cr01Pattern, $contents, $file);
            $this->assertDoesNotMatchRegularExpression('/\bsmr-[a-z0-9_-]+|#ff642d|#111322|#f97316|17,\s*19,\s*34/i', $contents, $file);
        }

        foreach ([$arDashboard, $enDashboard, $arRegister] as $rendered) {
            $this->assertDoesNotMatchRegularExpression($cr01Pattern, $rendered);
            $this->assertDoesNotMatchRegularExpression('/\bsmr-[a-z0-9_-]+|#ff642d|#111322|#f97316/i', $rendered);
        }
    }
}
