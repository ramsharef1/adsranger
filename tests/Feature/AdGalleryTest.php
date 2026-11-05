<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\User;
use App\Support\DemoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** #100 Shareable ad preview gallery for clients (Starter), behind the #56 signed link. */
class AdGalleryTest extends TestCase
{
    use RefreshDatabase;

    private function link(): array
    {
        $a = Agency::create(['name' => 'Acme Agency', 'slug' => 'acme-gal', 'plan' => 'starter', 'status' => 'active', 'language' => 'en']);
        $u = User::factory()->create();
        $a->members()->attach($u->id, ['role' => 'owner']);
        DemoData::seed($a, 20);
        $client = Client::where('agency_id', $a->id)->orderBy('id')->first();

        return [$a, $client, ClientLink::issue($client, $u)];
    }

    public function test_gallery_shows_only_this_clients_ads_without_login(): void
    {
        [$a, $client, $link] = $this->link();
        $ad = DB::table('ads')->join('ad_accounts', 'ad_accounts.id', '=', 'ads.ad_account_id')
            ->where('ad_accounts.client_id', $client->id)->select('ads.id', 'ads.name')->first();
        DB::table('ads')->where('id', $ad->id)->update(['thumbnail_url' => 'https://example.com/creative.jpg']);
        $otherName = DB::table('ads')->join('ad_accounts', 'ad_accounts.id', '=', 'ads.ad_account_id')
            ->where('ad_accounts.client_id', '!=', $client->id)->value('ads.name');

        $r = $this->get('/r/'.$link->token.'/ads?lang=en')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('https://example.com/creative.jpg', false)
            ->assertSee($ad->name)->assertSee('Cost per result')->assertSee('No image from Meta')
            ->assertDontSee($otherName);
        $this->get('/r/'.$link->token.'?lang=en')->assertOk()->assertSee('See the ads');
        $this->get('/r/'.$link->token.'/ads?lang=ar')->assertOk()->assertSee('الإعلانات التي عُرضت في هذه الفترة مع نتائجها.');
    }

    public function test_hide_spend_and_revoked_links(): void
    {
        [$a, $client, $link] = $this->link();
        $client->update(['hide_spend' => true]);
        $this->get('/r/'.$link->token.'/ads?lang=en')->assertOk()->assertDontSee('Cost per result');

        $link->update(['revoked_at' => now()]);
        $this->get('/r/'.$link->token.'/ads')->assertNotFound();
        $this->get('/r/'.str_repeat('a', 40).'/ads')->assertNotFound();
    }
}
