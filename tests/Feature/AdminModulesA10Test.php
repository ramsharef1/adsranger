<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\PlatformSettings;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Modules;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

/** A-10 (FR-030): super admin switches modules and preview workspaces in /admin without a deploy. */
class AdminModulesA10Test extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_turns_a_module_on_and_adds_a_preview_workspace(): void
    {
        config(['modules.enabled.seo_audit' => false, 'modules.enabled.organic' => true]);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create();
        $agency = Agency::create(['name' => 'Product test', 'slug' => 'product-test', 'plan' => 'agency', 'status' => 'active', 'owner_id' => $owner->id]);

        $this->assertFalse(Modules::on('seo_audit', $owner, $agency));

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(PlatformSettings::class)
            ->assertSet('moduleSwitches.seo_audit', 'default')
            ->set('moduleSwitches.organic', 'off')
            ->set('previewSlugs', "product-test\nBad_Slug!")
            ->call('saveModules');

        Once::flush();
        $this->assertFalse(Modules::enabled('organic'));
        $this->assertSame(['product-test'], Modules::previewAgencies());
        $this->assertTrue(Modules::on('seo_audit', $owner, $agency));
        $this->assertTrue(AuditLog::where('action', 'platform.modules_updated')->exists());

        Livewire::test(PlatformSettings::class)->set('moduleSwitches.seo_audit', 'on')->call('saveModules');
        Once::flush();
        $this->assertTrue(Modules::enabled('seo_audit'));
    }

    public function test_workspace_users_cannot_save_modules(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin/platform-settings')->assertStatus(403);
    }
}
