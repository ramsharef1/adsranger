<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\LinksBoard;
use App\Models\AuditLog;
use App\Models\LinkRedirect;
use App\Models\OwnLink;
use App\Models\User;
use App\Support\Links\OwnLinks;
use App\Support\Links\Reclaim;
use App\Support\Seo\SafeFetcher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** cl109: BX-07 link monitor (own links, monthly recheck) + BX-09 link reclamation (404 log × GSC → 301s, rename check). */
class LinksMonitorBx07Bx09Test extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> host → page HTML served by the fake */
    private array $pages = [];

    protected function setUp(): void
    {
        parent::setUp();
        SafeFetcher::$resolver = fn (string $host) => '93.184.216.34';
        Http::fake(fn ($request) => Http::response($this->pages[parse_url($request->url(), PHP_URL_HOST)] ?? '', 200, ['Content-Type' => 'text/html']));
    }

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    public function test_known_links_are_checked_for_rel_anchor_and_kind_and_a_lost_one_alerts(): void
    {
        $this->assertSame(2, OwnLinks::add("https://blog.example.com/post | Example blog | guest post\nhttps://dir.example.org/list\nnot a url\nhttps://adsranger.com/pricing"));
        $this->pages = [
            'blog.example.com' => '<html><body><p>See <a href="https://adsranger.com/en/" rel="nofollow ugc">AdsRanger</a></p></body></html>',
            'dir.example.org' => '<a href="https://www.adsranger.com/tools"><img src="x.png" alt=""></a> <a href="https://adsranger.com/">ad reporting software</a>',
        ];
        $r = OwnLinks::checkAll();
        $this->assertSame(['checked' => 2, 'live' => 2, 'lost' => [], 'newly_lost' => []], $r);
        $blog = OwnLink::where('name', 'Example blog')->first();
        $this->assertSame(['live', 'nofollow ugc', 'AdsRanger', 'brand', 'guest post'], [$blog->status, $blog->rel, $blog->anchor, $blog->anchor_kind, $blog->note]);
        $dir = OwnLink::where('source_url', 'https://dir.example.org/list')->first();
        $this->assertSame(['image', 2, 'follow'], [$dir->anchor_kind, $dir->links_found, $dir->rel]);
        $this->assertSame('exact', OwnLinks::anchorKind('Ad reporting software'));
        $this->assertSame('brand', OwnLinks::anchorKind('منصة أدز رينجر'));
        $this->assertSame(['brand', 'image'], array_keys(OwnLinks::mix()));

        // The blog drops the link: gone on both tries → lost, audit row, red badge.
        $this->pages = ['blog.example.com' => '<p>No links here any more</p>', 'dir.example.org' => '<a href="https://adsranger.com/">AdsRanger</a>'];
        $r = OwnLinks::checkAll();
        $this->assertSame(['https://blog.example.com/post'], $r['newly_lost']);
        $this->assertSame('lost', $blog->fresh()->status);
        $this->assertNotNull($blog->fresh()->lost_at);
        $this->assertTrue(AuditLog::where('action', 'links.lost')->exists());
        $this->assertSame('1', LinksBoard::getNavigationBadge());

        $this->artisan('links:verify')->expectsOutputToContain('1 lost')->assertExitCode(1);
        $this->assertSame(0, AuditLog::where('action', 'links.lost')->count() - 1, 'still lost is not a new alert');
    }

    public function test_404s_are_logged_without_ip_or_query_and_noise_is_skipped(): void
    {
        $this->get('/tools/old-roas-calc?utm_source=x', ['Referer' => 'https://forum.example.net/thread/9'])->assertNotFound();
        $this->get('/tools/old-roas-calc/')->assertNotFound();
        $this->get('/wp-login.php')->assertNotFound();
        $this->get('/admin/nothing-here');
        $row = DB::table('missing_pages')->where('path', '/tools/old-roas-calc')->first();
        $this->assertSame(2, (int) $row->hits);
        $this->assertSame('forum.example.net', $row->last_referrer_host);
        $this->assertSame(1, DB::table('missing_pages')->count(), 'only the real page address is kept');
        $this->assertSame([], array_diff(array_keys((array) $row), ['id', 'path', 'hits', 'last_referrer_host', 'first_seen_at', 'last_seen_at', 'ignored']), 'no IP, no full referrer');
    }

    public function test_gsc_import_and_404_log_give_301_suggestions_and_an_added_301_is_served(): void
    {
        $this->get('/en/tools/roas-calculatr')->assertNotFound();
        $this->get('/blog/gone-post')->assertNotFound();
        $csv = "\xEF\xBB\xBFTarget page,Incoming links,Linking sites\nhttps://adsranger.com/en/tools/roas-calculatr,\"1,204\",12\nhttps://adsranger.com/pricing,40,8\nhttps://other.com/x,5,1\n";
        $this->assertSame(2, Reclaim::importGsc($csv));
        $s = collect(Reclaim::suggestions())->keyBy('path');
        $this->assertFalse($s->has('/pricing'), 'a live page is not a suggestion');
        $first = Reclaim::suggestions()[0];
        $this->assertSame(['/en/tools/roas-calculatr', true, 1204, 12], [$first['path'], $first['both'], $first['links'], $first['sites']]);
        $this->assertSame('/en/tools/roas-calculator', $first['suggest']);

        $this->assertSame('from_is_live', Reclaim::addRedirect('/pricing', '/features'));
        $this->assertSame('target_not_live', Reclaim::addRedirect('/en/tools/roas-calculatr', '/en/nowhere'));
        $this->assertNull(Reclaim::addRedirect('/en/tools/roas-calculatr', '/en/tools/roas-calculator'));
        $this->get('/en/tools/roas-calculatr')->assertStatus(301)->assertRedirect('/en/tools/roas-calculator');
        $this->assertSame(1, LinkRedirect::first()->hits);
        $this->assertFalse(collect(Reclaim::suggestions())->contains('path', '/en/tools/roas-calculatr'));

        Reclaim::ignore('/blog/gone-post');
        $this->assertFalse(collect(Reclaim::suggestions())->contains('path', '/blog/gone-post'));
        Reclaim::disableRedirect(LinkRedirect::first()->id);
        $this->get('/en/tools/roas-calculatr')->assertNotFound();
    }

    /** Every slug rename keeps its 301 (AR + EN) to a live page: add renames to config/redirects.php, never ad hoc. */
    public function test_every_rename_keeps_its_301(): void
    {
        $this->assertNotEmpty(config('redirects.renames'));
        foreach (Reclaim::renameCheck() as $r) {
            $this->assertTrue($r['ok'], $r['from'].' → '.$r['to']);
            $this->get($r['from'])->assertStatus(301)->assertRedirect($r['to']);
            $this->get($r['to'])->assertOk();
        }
        $this->assertCount(2 * count(config('redirects.renames')), Reclaim::renameCheck());
    }

    public function test_admin_board_for_super_admin_only(): void
    {
        // #74: /admin requires two-step verification, so this admin has it set up.
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        OwnLink::create(['source_url' => 'https://lost.example.com/a', 'name' => 'Lost site', 'status' => 'lost', 'http_status' => 404]);
        $this->get('/admin/links')->assertOk()->assertSee('Lost site')->assertSee('روابط معروفة إلى AdsRanger اختفت: 1')->assertSee('capi-pixel-checker');
        Livewire::test(LinksBoard::class)->set('paste', 'https://new.example.com/p | New')->call('addLinks')->assertHasNoErrors();
        $this->assertTrue(OwnLink::where('name', 'New')->exists());
        Livewire::test(LinksBoard::class)->call('archive', OwnLink::where('name', 'New')->value('id'));
        $this->assertSame('archived', OwnLink::where('name', 'New')->value('status'));

        $this->actingAs(User::factory()->create(['role' => 'agency_owner']));
        $this->get('/admin/links')->assertForbidden();
    }
}
