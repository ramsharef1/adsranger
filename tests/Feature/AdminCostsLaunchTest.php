<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\CostsAndLaunch;
use App\Models\CostLine;
use App\Models\LaunchCheck;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** A-4 cost page vs the cap · A-8 launch go / no-go list. */
class AdminCostsLaunchTest extends TestCase
{
    use RefreshDatabase;

    public function test_costs_add_up_against_the_cap_and_launch_lines_change_status(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'super_admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame(10, LaunchCheck::count());

        $lw = Livewire::test(CostsAndLaunch::class)
            ->set('costCategory', 'server')->set('costAmount', 620)->call('addCost')
            ->set('costCategory', 'ai')->set('costAmount', 230.5)->call('addCost')
            ->assertSee('850.50 USD')
            ->assertSee('85%')
            ->assertSee('reached 85% of the cap');
        $this->assertSame(2, CostLine::count());

        $id = LaunchCheck::orderBy('sort')->value('id');
        $lw->call('setCheckStatus', $id, 'done')->call('setCheckDue', $id, '2027-01-15')
            ->assertSee('1 of 10 lines done');
        $this->assertSame('2027-01-15', LaunchCheck::find($id)->due_on->format('Y-m-d'));

        $lw->set('costAmount', -5)->call('addCost');
        $this->assertSame(2, CostLine::count());
    }

    public function test_only_super_admins_open_the_page(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/admin/costs-launch')->assertStatus(403);
    }
}
