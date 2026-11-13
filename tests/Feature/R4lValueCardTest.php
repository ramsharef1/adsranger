<?php

namespace Tests\Feature;

use App\Filament\Pages\DailyDesk;
use App\Models\Agency;
use App\Models\Alert;
use App\Models\Client;
use App\Models\User;
use App\Support\ValueCard;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R4 (D-306): PS-29 owner value card — counts from the app × fixed minutes, labelled «تقدير», owners and admins only. */
class R4lValueCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hours_follow_the_minutes_and_only_owners_see_the_card(): void
    {
        $agency = Agency::create(['name' => 'Acme', 'slug' => 'acme-r4l', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'en']);
        $owner = User::factory()->create();
        $agency->members()->attach($owner->id, ['role' => 'owner']);
        $c = Client::create(['agency_id' => $agency->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        foreach (range(1, 6) as $i) {
            Alert::create(['agency_id' => $agency->id, 'client_id' => $c->id, 'rule_key' => 'x', 'severity' => 'warning', 'title' => 't', 'fingerprint' => 'f'.$i,
                'status' => $i <= 3 ? 'resolved' : 'open', 'resolved_at' => $i <= 3 ? now() : null, 'resolved_by' => $i <= 3 ? $owner->id : null]);
        }
        $card = ValueCard::month($agency);
        $this->assertSame(6, collect($card['items'])->firstWhere('key', 'alerts_caught')['count']);
        $this->assertSame(3, collect($card['items'])->firstWhere('key', 'alerts_handled')['count']);
        $this->assertSame(1.0, $card['hours'], '6 × 5 min + 3 × 10 min = 60 min');

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($agency);
        Livewire::test(DailyDesk::class)->assertSee('What AdsRanger did for you this month')->assertSee('× 5 minutes');

        $analyst = User::factory()->create();
        $agency->members()->attach($analyst->id, ['role' => 'analyst']);
        $this->actingAs($analyst);
        Livewire::test(DailyDesk::class)->assertDontSee('What AdsRanger did for you this month');
    }
}
