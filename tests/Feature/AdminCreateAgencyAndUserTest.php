<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Agencies\Pages\CreateAgency;
use App\Filament\Admin\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCreateAgencyAndUserTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $u = User::factory()->create();
        $u->forceFill([
            'role' => 'super_admin',
            'app_authentication_secret' => 'JBSWY3DPEHPK3PXP',
        ])->save();

        return $u;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Plan::firstOrCreate(['key' => 'pro'], ['name' => 'Pro', 'sort' => 2]);
        Plan::firstOrCreate(['key' => 'agency'], ['name' => 'Agency', 'sort' => 3]);
    }

    public function test_super_admin_can_create_agency_with_new_owner_and_audit_log(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateAgency::class)
            ->fillForm([
                'name' => 'Telescope Digital',
                'slug' => '',
                'country' => 'JO',
                'currency' => 'JOD',
                'language' => 'ar',
                'plan' => 'agency',
                'status' => 'active',
                'owner_email' => 'founder@telescopedigital.jo',
                'owner_name' => 'Agency Founder',
                'owner_password' => 'StrongPass#2026!',
                'owner_workspace_role' => 'owner',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $agency = Agency::query()->where('slug', 'telescope-digital')->firstOrFail();
        $this->assertSame('admin', $agency->signup_source);
        $this->assertSame('agency', $agency->plan);
        $this->assertSame('JOD', $agency->currency);

        $owner = User::query()->where('email', 'founder@telescopedigital.jo')->firstOrFail();
        $this->assertSame($owner->id, $agency->owner_id);
        $this->assertSame('owner', $owner->rawRoleIn($agency));
        $this->assertTrue($owner->hasVerifiedEmail());
        $this->assertTrue(Hash::check('StrongPass#2026!', $owner->password));
        $this->assertTrue(AuditLog::query()->where('action', 'agency.created_by_admin')->exists());
    }

    public function test_super_admin_can_create_platform_super_admin_and_workspace_admin(): void
    {
        $admin = $this->superAdmin();
        $existingAgency = Agency::create([
            'name' => 'Amman Growth',
            'slug' => 'amman-growth',
            'plan' => 'pro',
            'status' => 'active',
            'signup_source' => 'admin',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // 1. Create a second Platform Super Admin
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Platform Co-Admin',
                'email' => 'coadmin@adsranger.com',
                'password' => 'SuperAdmin#2026!',
                'role' => 'super_admin',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coAdmin = User::query()->where('email', 'coadmin@adsranger.com')->firstOrFail();
        $this->assertTrue($coAdmin->isSuperAdmin());
        $this->assertTrue(AuditLog::query()->where('action', 'user.created_by_admin')->where('subject_id', $coAdmin->id)->exists());

        // 2. Create a Workspace Admin directly attached to Amman Growth
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ops Admin',
                'email' => 'ops@ammangrowth.com',
                'password' => 'WorkspaceAdmin#2026!',
                'role' => 'member',
                'agency_id' => $existingAgency->id,
                'workspace_role' => 'admin',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $opsAdmin = User::query()->where('email', 'ops@ammangrowth.com')->firstOrFail();
        $this->assertFalse($opsAdmin->isSuperAdmin());
        $this->assertSame('admin', $opsAdmin->rawRoleIn($existingAgency));

        // 3. Attach an existing user to an agency from ListAgencies table action
        Livewire::test(ListAgencies::class)
            ->callTableAction('attachMember', $existingAgency, data: [
                'email' => 'coadmin@adsranger.com',
                'role' => 'owner',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('owner', $coAdmin->fresh()->rawRoleIn($existingAgency));
        $this->assertSame($coAdmin->id, $existingAgency->fresh()->owner_id);
    }

    public function test_regular_members_cannot_access_admin_create_routes(): void
    {
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->get('/admin/agencies/create')->assertForbidden();
        $this->actingAs($member)->get('/admin/users/create')->assertForbidden();
    }
}
