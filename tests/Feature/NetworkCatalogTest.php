<?php

namespace Tests\Feature;

use App\Filament\Pages\NetworkCatalogPage;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\NetworkRequest;
use App\Models\User;
use App\Support\NetworkCatalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** #127 Network catalog + request-a-network votes (Starter). */
class NetworkCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function workspace(string $plan = 'starter', string $role = 'analyst', string $slug = 'acme-nc'): array
    {
        AdNetwork::query()->update(['enabled' => true]);
        $agency = Agency::create(['name' => 'Acme', 'slug' => $slug, 'plan' => $plan, 'status' => 'active']);
        $user = User::factory()->create();
        $agency->members()->attach($user->id, ['role' => $role]);

        return [$agency, $user];
    }

    private function act(Agency $a, User $u): void
    {
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Filament::setTenant($a);
    }

    private function row(array $groups, string $key): ?array
    {
        return collect($groups)->flatten(1)->firstWhere('key', $key);
    }

    public function test_statuses_follow_plan_and_include_planned_networks(): void
    {
        [$a, $u] = $this->workspace('starter');
        $g = NetworkCatalog::forMember($u, $a);

        $this->assertSame('available', $this->row($g, 'meta')['status']);
        $this->assertSame('upgrade', $this->row($g, 'linkedin')['status']);      // Pro+ (D-32)
        $this->assertSame('pro', $this->row($g, 'linkedin')['tier']);
        $this->assertSame('planned', $this->row($g, 'noon')['status']);
        $this->assertArrayHasKey('Push & pop', $g);

        AdNetwork::where('key', 'tiktok')->update(['enabled' => false]);
        $this->assertSame('soon', $this->row(NetworkCatalog::forMember($u, $a), 'tiktok')['status']);
    }

    public function test_member_can_request_planned_network_once_and_counts_are_per_workspace(): void
    {
        [$a, $u] = $this->workspace();
        $this->act($a, $u);

        Livewire::test(NetworkCatalogPage::class)->call('toggleRequest', 'noon')->assertSee('Requested');
        $this->assertSame(1, NetworkRequest::where('network_key', 'noon')->count());

        $other = User::factory()->create();
        $a->members()->attach($other->id, ['role' => 'analyst']);
        $this->act($a, $other);
        Livewire::test(NetworkCatalogPage::class)->call('toggleRequest', 'noon');
        $this->assertSame(1, NetworkCatalog::requestCounts()['noon']); // two members, one workspace

        Livewire::test(NetworkCatalogPage::class)->call('toggleRequest', 'noon'); // toggle off
        $this->assertSame(1, NetworkRequest::where('network_key', 'noon')->count());
    }

    public function test_available_or_unknown_networks_cannot_be_requested(): void
    {
        [$a, $u] = $this->workspace();
        $this->act($a, $u);

        Livewire::test(NetworkCatalogPage::class)->call('toggleRequest', 'meta')->assertStatus(422);
        Livewire::test(NetworkCatalogPage::class)->call('toggleRequest', 'nope')->assertNotFound();
        $this->assertSame(0, NetworkRequest::count());
    }

    public function test_suggestion_is_stored_and_grouped_by_name(): void
    {
        [$a, $u] = $this->workspace();
        $this->act($a, $u);
        Livewire::test(NetworkCatalogPage::class)->set('suggestName', 'Criteo')->set('suggestNote', 'Retargeting for 3 shops')->call('suggest')->assertHasNoErrors();

        [$b, $v] = $this->workspace('pro', 'owner', 'beta-nc');
        $this->act($b, $v);
        Livewire::test(NetworkCatalogPage::class)->set('suggestName', ' criteo ')->call('suggest');

        $this->assertSame(2, NetworkCatalog::requestCounts()['other:criteo']);
        Livewire::test(NetworkCatalogPage::class)->set('suggestName', 'سناب')->call('suggest')->assertHasNoErrors();
        $this->assertSame(3, NetworkRequest::count());
        Livewire::test(NetworkCatalogPage::class)->set('suggestName', '')->call('suggest')->assertHasErrors('suggestName');
    }

    public function test_page_renders_in_both_languages_and_admin_tally_is_super_admin_only(): void
    {
        [$a, $u] = $this->workspace('starter', 'owner');
        $this->withSession(['locale' => 'ar'])->actingAs($u)->get("/app/{$a->slug}/network-catalog")
            ->assertOk()->assertSee('دليل المنصات الإعلانية')->assertSee('مخطط لها');
        $this->withSession(['locale' => 'en'])->actingAs($u)->get("/app/{$a->slug}/network-catalog")
            ->assertOk()->assertSee('Network catalog')->assertSee('Noon Ads')->assertSee('On Pro');

        $this->actingAs($u)->get('/admin/network-requests')->assertForbidden();
    }
}
