<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\PartnerApplications;
use App\Filament\Admin\Pages\WaitlistEntries;
use App\Filament\Admin\Widgets\SystemOverviewWidget;
use App\Models\PartnerApplication;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAndPublicUxPolishTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_system_overview_widget_and_upgraded_admin_tables(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        WaitlistEntry::create([
            'name' => 'Rami',
            'agency_name' => 'Amman Digital',
            'email' => 'rami@amman.test',
            'lang' => 'ar',
            'consent_at' => now(),
            'consent_text_version' => 'v1',
        ]);

        PartnerApplication::create([
            'agency_name' => 'Petra Media',
            'name' => 'Zaid',
            'email' => 'zaid@petra.test',
            'country' => 'JO',
            'clients_range' => '6-15',
            'lang' => 'ar',
            'consent_at' => now(),
            'consent_text_version' => 'v1',
        ]);

        $this->actingAs($admin);

        Livewire::test(SystemOverviewWidget::class)
            ->assertSee('/admin/waitlist', false)
            ->assertSee('/admin/partner-applications', false)
            ->assertSee('/admin/platform-settings', false)
            ->assertStatus(200);

        Livewire::test(WaitlistEntries::class)
            ->assertSee('Amman Digital')
            ->assertSee('AR: 1 · EN: 0')
            ->assertStatus(200);

        Livewire::test(PartnerApplications::class)
            ->assertSee('Petra Media')
            ->assertSee('AR: 1 · EN: 0')
            ->assertStatus(200);
    }
}
