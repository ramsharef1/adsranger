<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use App\Support\DemoData;
use App\Support\Pacing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Month names follow the panel language (Carbon translatedFormat), with Western digits (EX-20). */
class ArabicMonthNamesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pacing_and_budget_planner_show_arabic_month_names(): void
    {
        Carbon::setTestNow('2026-11-15 12:00:00');
        $a = Agency::create(['name' => 'Acme', 'slug' => 'acme', 'plan' => 'agency', 'status' => 'active', 'timezone' => 'UTC']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'owner']);
        DemoData::seed($a, 40);
        Client::where('agency_id', $a->id)->update(['monthly_budget' => 1000]);

        $this->actingAs($u)->withSession(['locale' => 'ar'])->get('/app/acme/pacing')->assertOk()
            ->assertSee('نوفمبر 2026')->assertDontSee('November 2026');
        $this->actingAs($u)->withSession(['locale' => 'en'])->get('/app/acme/pacing')->assertOk()
            ->assertSee('November 2026');

        $this->actingAs($u)->withSession(['locale' => 'ar'])->get('/app/acme/budget-plan')->assertOk()
            ->assertSee('يناير 2026')->assertDontSee('Jan 2026');

        app()->setLocale('ar');
        $this->assertSame('نوفمبر 2026', Pacing::month(Client::where('agency_id', $a->id)->first())['month_label']);
    }
}
