<?php

namespace Tests\Feature;

use App\Filament\Pages\TopAdsPage;
use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use App\Support\DemoData;
use App\Support\TopAds;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** #51 Top ads with thumbnails (Starter). */
class TopAdsTest extends TestCase
{
    use RefreshDatabase;

    private function agency(string $role = 'owner'): array
    {
        $a = Agency::create(['name' => 'Acme', 'slug' => 'acme-top-'.$role, 'plan' => 'starter', 'status' => 'active', 'currency' => 'USD']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => $role]);
        DemoData::seed($a, 20);

        return [$a, $u];
    }

    public function test_list_ranks_ads_across_clients_with_network_and_currency(): void
    {
        [$a] = $this->agency();
        $clients = Client::where('agency_id', $a->id)->get();
        [$from, $to] = TopAds::range('30');
        $ads = TopAds::list($a, $clients, $from, $to);

        $this->assertCount(18, $ads);                                   // 3 clients × 2 campaigns × 3 ads
        $this->assertSame('meta', $ads[0]['network']);
        $this->assertGreaterThanOrEqual($ads[1]['main_results'], $ads[0]['main_results']);
        $this->assertCount(3, array_unique(array_column($ads, 'currency')));

        $bySpend = TopAds::list($a, $clients, $from, $to, null, 'spend');
        $this->assertGreaterThanOrEqual($bySpend[1]['spend_base'], $bySpend[0]['spend_base']);
        $byCpr = array_values(array_filter(TopAds::list($a, $clients, $from, $to, null, 'cpr'), fn ($r) => $r['cpr_base'] !== null));
        $this->assertLessThanOrEqual($byCpr[1]['cpr_base'], $byCpr[0]['cpr_base']);

        $this->assertSame([], TopAds::list($a, $clients, $from, $to, 'tiktok'));
        $this->assertSame(['meta' => 'Meta'], array_map(fn ($n) => explode(' ', $n)[0], TopAds::networks($ads)));
    }

    public function test_restricted_member_sees_only_assigned_clients(): void
    {
        [$a, $u] = $this->agency('analyst');
        $mine = Client::where('agency_id', $a->id)->orderBy('id')->first();
        $mine->users()->attach($u->id);
        foreach (Client::where('agency_id', $a->id)->where('id', '!=', $mine->id)->get() as $other) {
            $other->users()->attach(User::factory()->create()->id);
        }
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);

        $c = Livewire::test(TopAdsPage::class)->assertOk()->assertSee($mine->name.' · ', false);
        foreach (Client::where('agency_id', $a->id)->where('id', '!=', $mine->id)->get() as $other) {
            $c->assertDontSee($other->name);
        }
    }

    public function test_page_renders_thumbnails_and_placeholders_in_both_languages(): void
    {
        [$a, $u] = $this->agency();
        DB::table('ads')->where('agency_id', $a->id)->orderBy('id')->limit(1)->update(['thumbnail_url' => 'https://example.com/thumb.jpg']);

        $this->actingAs($u)->withSession(['locale' => 'en'])->get("/app/{$a->slug}/top-ads")
            ->assertOk()->assertSee('Top ads')->assertSee('https://example.com/thumb.jpg', false)->assertSee('No image from Meta')->assertSee('Lowest cost per result');
        $this->actingAs($u)->withSession(['locale' => 'ar'])->get("/app/{$a->slug}/top-ads")
            ->assertOk()->assertSee('أفضل الإعلانات')->assertSee('الأقل تكلفة لكل نتيجة');
    }

    public function test_empty_state(): void
    {
        $a = Agency::create(['name' => 'Empty', 'slug' => 'empty-top', 'plan' => 'starter', 'status' => 'active']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'owner']);
        $this->actingAs($u)->withSession(['locale' => 'en'])->get("/app/{$a->slug}/top-ads")
            ->assertOk()->assertSee('No clients yet. Add a client and link its ad accounts first.');
    }
}
