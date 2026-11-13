<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ReachControlBoard;
use App\Models\Agency;
use App\Models\ReachLedgerEntry;
use App\Models\ReachOffer;
use App\Models\ReachPostbackEndpoint;
use App\Models\ReachPublisher;
use App\Models\ReachWidget;
use App\Models\User;
use App\Support\Reach\Postbacks;
use App\Support\Reach\PublisherKit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/** FR-REACH R-4 (E2-064): network Sub-ID / postback presets with per-endpoint overrides, WordPress plugin + Cloudflare Worker kits. */
class ReachR4Test extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: ReachPublisher, 2: ReachWidget} */
    private function publisher(): array
    {
        config(['modules.enabled.reach' => true]);
        $user = User::factory()->create();
        $agency = Agency::create(['name' => 'Pub', 'slug' => 'pub-r4', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'UTC', 'language' => 'ar']);
        $agency->members()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);
        $p = ReachPublisher::factory()->verified()->create(['user_id' => $user->id, 'agency_id' => $agency->id, 'domain_or_app' => 'travel.example']);

        return [$user, $p, $p->widgets()->create(['type' => 'web_js', 'placement' => 'in-article'])];
    }

    public function test_network_presets_set_the_click_subid_and_read_each_networks_postback_names(): void
    {
        $this->assertSame(['arabclicks' => 'aff_sub', 'linkaraby' => 'aff_sub', 'admitad' => 'subid', 'impact' => 'subId1', 'direct_client' => 'subid'], config('reach.subid_params'));
        foreach (config('reach.networks') as $network) {
            $this->assertSame(config('reach.subid_params.'.$network), config('reach.network_presets.'.$network.'.subid'), "$network preset and click param agree");
        }

        [, $p] = $this->publisher();
        ReachOffer::factory()->create(['network' => 'impact', 'external_id' => 'C-77', 'payout_amount' => 20, 'publisher_payout_amount' => 14]);
        [$endpoint, $token, $secret] = ReachPostbackEndpoint::issue('impact', 'Impact main');
        $this->assertStringContainsString('subId1={SubId1}', Postbacks::template($endpoint, 'https://x.test/pb'));
        $this->assertStringContainsString('action_id={ActionId}', Postbacks::template($endpoint, 'https://x.test/pb'));

        // Impact sends its own names; ActionStatus-style values in any case.
        $params = ['action_id' => 'imp-1', 'SubId1' => $p->ref.'_in-article', 'campaign_id' => 'C-77', 'payout' => '20', 'status' => 'APPROVED'];
        $this->get('/reach/v1/postback/'.$token.'?'.http_build_query($params + ['sig' => Postbacks::sign($params, $secret)]))->assertOk()->assertJson(['result' => 'credited']);
        $entry = ReachLedgerEntry::sole();
        $this->assertSame([$p->id, 'subid', 'in-article', 14.0], [$entry->publisher_id, $entry->attribution_tier, $entry->placement, $entry->net_commission]);

        $reverse = ['action_id' => 'imp-1', 'status' => 'REVERSED'];
        $this->get('/reach/v1/postback/'.$token.'?'.http_build_query($reverse + ['sig' => Postbacks::sign($reverse, $secret)]))->assertOk()->assertJson(['result' => 'refunded']);
    }

    public function test_endpoint_override_replaces_the_preset_from_the_admin_board(): void
    {
        [, $p] = $this->publisher();
        [$endpoint, $token, $secret] = ReachPostbackEndpoint::issue('admitad', 'Admitad custom');
        $this->assertSame('subid', Postbacks::preset($endpoint)['subid']);

        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        Livewire::test(ReachControlBoard::class)
            ->set("overrides.{$endpoint->id}", ['subid' => 'sub2', 'conversion_id' => 'txn', 'payout' => '', 'status' => 'bad param!', 'offer_id' => ''])
            ->call('saveOverride', $endpoint->id);
        $endpoint->refresh();
        $this->assertSame('sub2', $endpoint->subid_param);
        $this->assertSame(['conversion_id' => 'txn'], $endpoint->param_map, 'empty or unsafe fields keep the preset');
        $this->assertSame('payment', Postbacks::preset($endpoint)['fields']['payout']);

        $params = ['txn' => 'adm-9', 'sub2' => $p->ref, 'payment' => '5'];
        $this->get('/reach/v1/postback/'.$token.'?'.http_build_query($params + ['sig' => Postbacks::sign($params, $secret)]))->assertOk()->assertJson(['result' => 'credited']);
        $this->assertSame('adm-9', ReachLedgerEntry::sole()->external_conversion_id);
    }

    public function test_wordpress_plugin_zip_is_deterministic_valid_and_prefilled(): void
    {
        [$user, $p, $web] = $this->publisher();
        $zip = PublisherKit::wordpressZip($p, $web);
        $this->assertSame($zip, PublisherKit::wordpressZip($p->fresh(), $web->fresh()), 'same publisher, same bytes');

        $path = tempnam(sys_get_temp_dir(), 'wp').'.zip';
        file_put_contents($path, $zip);
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path, ZipArchive::CHECKCONS) === true, 'a valid zip archive');
        $php = (string) $archive->getFromName('adsranger-reach/adsranger-reach.php');
        $this->assertNotSame('', (string) $archive->getFromName('adsranger-reach/readme.txt'));
        $archive->close();
        @unlink($path);

        $this->assertStringContainsString("define('ADSRANGER_REACH_REF', '{$p->ref}');", $php);
        $this->assertStringContainsString("define('ADSRANGER_REACH_WIDGET', '{$web->public_key}');", $php);
        $this->assertStringContainsString("'strategy' => 'async'", $php);
        $this->assertStringContainsString("/reach/v1/llms/' . ADSRANGER_REACH_REF . '.txt", $php);
        $this->assertStringContainsString('application/ld+json', $php);
        $this->assertStringContainsString("empty(\$GLOBALS['adsranger_reach_shown'])", $php, 'Offer markup only where the offers show');
        $lint = tempnam(sys_get_temp_dir(), 'plugin').'.php';
        file_put_contents($lint, $php);
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($lint).' 2>&1', $out, $code);
        @unlink($lint);
        $this->assertSame(0, $code, implode("\n", $out));
        foreach (['adsense', 'admob', 'taboola', 'semrush', 'ahrefs'] as $name) {
            $this->assertStringNotContainsStringIgnoringCase($name, $php);
        }

        $this->actingAs($user)->get('/reach/portal/wordpress-plugin.zip?w='.$web->public_key)->assertOk()
            ->assertHeader('Content-Type', 'application/zip')->assertHeader('Content-Disposition', 'attachment; filename="adsranger-reach-in-article.zip"');
        $this->actingAs($user)->get('/reach/portal/wordpress-plugin.zip?w=rw_notmine')->assertNotFound();
        $other = ReachPublisher::factory()->verified()->create()->widgets()->create(['type' => 'web_js', 'placement' => 'x']);
        $this->actingAs($user)->get('/reach/portal/wordpress-plugin.zip?w='.$other->public_key)->assertNotFound();
    }

    public function test_placements_tab_shows_kits_and_everything_404s_while_reach_is_off(): void
    {
        [$user, $p, $web] = $this->publisher();
        $worker = PublisherKit::cloudflareWorker($p, $web);
        $this->assertStringContainsString('const REF = "'.$p->ref.'";', $worker);
        $this->assertStringContainsString('HTMLRewriter', $worker);
        $this->assertStringContainsString('<script async src="', $worker);

        $this->actingAs($user)->get('/reach/portal?tab=placements')->assertOk()
            ->assertSee('إضافة ووردبريس')->assertSee('wordpress-plugin.zip?w='.$web->public_key, false)->assertSee('HTMLRewriter');

        config(['modules.enabled.reach' => false]);
        $this->actingAs($user)->get('/reach/portal/wordpress-plugin.zip?w='.$web->public_key)->assertNotFound();
        auth()->logout();
        $this->get('/reach/portal/wordpress-plugin.zip?w='.$web->public_key)->assertNotFound();
    }
}
