<?php

namespace Tests\Feature;

use App\Alerts\AlertEngine;
use App\Filament\Admin\Pages\OperationsBoard;
use App\Filament\Admin\Pages\ReindexGateBoard;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\Alert;
use App\Models\LandingPage;
use App\Models\User;
use App\Support\Announcements;
use App\Support\LandingPages;
use App\Support\PublicSite;
use App\Support\Site\ReindexGate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

/** R1 adds (D-290 · D-291 · D-292 · D-293): invite by link, app announcement, CS-20 re-index gate, DB-01 landing page down. */
class R1AddsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ReindexGate::$fetch = null;
        parent::tearDown();
    }

    private function agency(): Agency
    {
        return Agency::create(['name' => 'Souq Ads', 'slug' => 'souq-'.uniqid(), 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Amman', 'currency' => 'USD']);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    public function test_invite_link_works_once_within_24_hours_for_a_new_person(): void
    {
        $agency = $this->agency();
        $owner = User::factory()->create();
        [$invite, $url] = AgencyInvitation::link($agency, 'manager', $owner);
        $path = parse_url($url, PHP_URL_PATH);

        $this->assertNull($invite->email);
        $this->assertTrue($invite->expires_at->between(now()->addHours(23), now()->addHours(25)));
        $this->get($path)->assertOk()->assertSee('name="email"', false);

        $this->post($path, ['email' => 'New@Souq.test', 'name' => 'Lina', 'password' => 'long-password-1', 'password_confirmation' => 'long-password-1'])
            ->assertRedirect('/app/'.$agency->slug);
        $lina = User::where('email', 'new@souq.test')->firstOrFail();
        $this->assertNotNull($lina->email_verified_at, 'D-293: the link is the proof');
        $this->assertTrue($agency->members()->whereKey($lina->id)->exists());
        $this->assertSame('new@souq.test', $invite->fresh()->email);

        // One use: a second person gets the invalid page.
        auth()->logout();
        $this->get($path)->assertStatus(410);
    }

    public function test_invite_link_lets_a_signed_in_user_join_and_expires(): void
    {
        $agency = $this->agency();
        $owner = User::factory()->create();
        $sam = User::factory()->create();
        [, $url] = AgencyInvitation::link($agency, 'analyst', $owner);
        $path = parse_url($url, PHP_URL_PATH);

        $this->actingAs($sam)->get($path)->assertOk()->assertSee('/invite/', false);
        $this->actingAs($sam)->post($path)->assertRedirect('/app/'.$agency->slug);
        $this->assertSame('analyst', $agency->members()->whereKey($sam->id)->first()->pivot->role);

        [, $old] = AgencyInvitation::link($agency, 'analyst', $owner);
        $this->travel(25)->hours();
        $this->get(parse_url($old, PHP_URL_PATH))->assertStatus(410);
    }

    public function test_announcement_one_at_a_time_and_shown_in_the_app(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(OperationsBoard::class)
            ->set('noteAr', 'صيانة قصيرة الليلة')->set('noteEn', 'Short maintenance tonight')->set('noteDays', 2)->call('postAnnouncement')->assertHasNoErrors();
        Announcements::post('سطر ثانٍ', 'Second line', 'warning', 1, null);
        Once::flush();

        $this->assertSame('Second line', Announcements::current()->body_en);
        $this->assertSame(1, DB::table('announcements')->where('ends_at', '>', now())->count());

        $html = view('panels.announcement')->render();
        $this->assertStringContainsString('data-announcement', $html);

        Announcements::end();
        Once::flush();
        $this->assertNull(Announcements::current());
        $this->assertStringNotContainsString('data-announcement', view('panels.announcement')->render());
    }

    public function test_reindex_gate_releases_only_a_passing_page_that_is_ticked_ready(): void
    {
        config(['seo_tools.articles_public' => false, 'app.url' => 'https://adsranger.test']);
        $candidates = ReindexGate::candidates();
        $term = array_search('term', $candidates, true);
        $this->assertNotFalse($term);
        $this->assertTrue(PublicSite::held($term));

        $good = '<html lang="ar"><head><title>عنوان المصطلح</title><meta name="description" content="'.str_repeat('وصف ', 25).'">'
            .'<link rel="alternate" hreflang="ar" href="https://a/x"><link rel="alternate" hreflang="en" href="https://a/en/x">'
            .'<script type="application/ld+json">{"@type":"DefinedTerm"}</script></head><body><main><p>'.str_repeat('كلمة ', 300).'</p>'
            .'<a href="/tools/roas-calculator">أداة</a> <a href="https://adsranger.test/en/guides/x">دليل</a> <a href="https://other.test/">خارجي</a></main></body></html>';
        ReindexGate::$fetch = fn (string $url) => ['status' => 200, 'body' => str_ends_with($url, $term) ? $good : '<main></main>'];
        ReindexGate::run();

        $row = DB::table('page_gate')->where('path', $term)->first();
        $this->assertSame(303, (int) $row->words); // 300 body words + 3 link words
        $this->assertTrue(ReindexGate::passes($row));
        $this->assertSame(2, (int) $row->out_links, 'only links on our own site count');
        $other = DB::table('page_gate')->where('path', '!=', $term)->first();
        $this->assertFalse(ReindexGate::passes($other));

        $this->actingAs($this->admin());
        $this->get('/admin/reindex-gate')->assertOk();
        Livewire::test(ReindexGateBoard::class)->call('release', $term);
        $this->assertNull(DB::table('page_gate')->where('path', $term)->value('released_at'), 'not ticked ready yet');

        Livewire::test(ReindexGateBoard::class)->call('ready', $term)->call('release', $term)->call('release', $other->path);
        $this->assertNotNull(DB::table('page_gate')->where('path', $term)->value('released_at'));
        $this->assertNull(DB::table('page_gate')->where('path', $other->path)->value('released_at'));

        Once::flush();
        $this->assertFalse(PublicSite::held($term));
        $this->assertFalse(PublicSite::held('/en'.$term), 'both languages follow');
        $this->assertTrue(PublicSite::held($other->path));
    }

    public function test_landing_page_down_since_and_offsite_redirect_alert(): void
    {
        $agency = $this->agency();
        $make = fn (string $url) => LandingPage::create(['agency_id' => $agency->id, 'url' => $url, 'url_hash' => sha1($url), 'source' => 'meta', 'clicks_30d' => 12, 'last_seen_at' => now()]);

        Http::fake([
            'https://93.184.216.34/down' => Http::response('', 500),
            'https://93.184.216.34/moved' => Http::response('', 301, ['Location' => 'https://93.184.216.35/']),
            'https://93.184.216.35/' => Http::response('<html>ok</html>', 200),
            'https://93.184.216.34/ok' => Http::response('<html>ok</html>', 200),
        ]);
        foreach (['https://93.184.216.34/down', 'https://93.184.216.34/moved', 'https://93.184.216.34/ok'] as $u) {
            LandingPages::check($make($u));
        }
        $first = LandingPage::where('url', 'https://93.184.216.34/down')->first()->down_since;
        $this->assertNotNull($first);
        $this->assertNull(LandingPage::where('url', 'https://93.184.216.34/ok')->first()->down_since);
        $this->assertNotNull(LandingPage::where('url', 'https://93.184.216.34/moved')->first()->down_since);

        $this->travel(1)->days();
        LandingPages::check(LandingPage::where('url', 'https://93.184.216.34/down')->first());
        $this->assertTrue($first->equalTo(LandingPage::where('url', 'https://93.184.216.34/down')->first()->down_since), 'first failure time is kept');

        AlertEngine::run($agency, now()->toDateString());
        $alerts = Alert::where('agency_id', $agency->id)->where('rule_key', 'landing_page')->get();
        $this->assertCount(2, $alerts);
        $this->assertStringContainsString('Down since', $alerts->firstWhere('entity_name', 'https://93.184.216.34/down')->detail);
        $this->assertStringContainsString('redirects to another site', $alerts->firstWhere('entity_name', 'https://93.184.216.34/moved')->detail);
    }
}
