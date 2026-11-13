<?php

namespace Tests\Feature;

use App\Jobs\RunAlerts;
use App\Mail\AlertsDigestMail;
use App\Models\AdAccount;
use App\Models\AdNetwork;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Connection;
use App\Models\DailyMetric;
use App\Models\User;
use App\Support\CriticalPush;
use App\Support\Pacing;
use App\Support\RamadanHours;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** R5 part 1 (D-307): PS-19 Ramadan hours mode, PS-20 weekday curve in pacing. */
class R5aRamadanPacingTest extends TestCase
{
    use RefreshDatabase;

    private function agencyWithAccount(): array
    {
        $a = Agency::create(['name' => 'Souq', 'slug' => 'souq-r5', 'plan' => 'pro', 'status' => 'active', 'currency' => 'SAR', 'timezone' => 'Asia/Riyadh', 'language' => 'en', 'country' => 'SA']);
        $u = User::factory()->create(['email_digest' => true]);
        $a->members()->attach($u->id, ['role' => 'owner']);
        $c = Client::create(['agency_id' => $a->id, 'name' => 'Zad Store', 'currency' => 'SAR', 'status' => 'active']);
        $network = AdNetwork::firstOrCreate(['key' => 'meta'], ['name' => 'Meta', 'category' => 'social']);
        $conn = Connection::create(['agency_id' => $a->id, 'ad_network_id' => $network->id, 'status' => 'active', 'environment' => 'live']);
        $acc = AdAccount::create(['agency_id' => $a->id, 'connection_id' => $conn->id, 'client_id' => $c->id, 'ad_network_id' => $network->id, 'external_id' => 'act_1', 'name' => 'Zad', 'currency' => 'SAR', 'is_active' => true]);

        return [$a, $u, $c, $acc];
    }

    public function test_digest_waits_for_iftar_in_ramadan_and_quiet_hours_shift(): void
    {
        Mail::fake();
        [$a, $u] = $this->agencyWithAccount();

        $this->travelTo(Carbon::parse('2027-02-15 09:00', 'Asia/Riyadh'));
        $this->assertTrue(RamadanHours::active($a));
        (new RunAlerts)->handle();
        Mail::assertNotQueued(AlertsDigestMail::class);
        $this->assertTrue(CriticalPush::isQuiet($u, $a, now()), '09:00 in Ramadan is quiet by default');

        $this->travelTo(Carbon::parse('2027-02-15 21:05', 'Asia/Riyadh'));
        (new RunAlerts)->handle();
        Mail::assertQueued(AlertsDigestMail::class, 1);
        $this->assertFalse(CriticalPush::isQuiet($u, $a, now()));

        $u->forceFill(['push_quiet_start' => '22:00', 'push_quiet_end' => '06:00'])->save();
        $this->travelTo(Carbon::parse('2027-02-16 09:00', 'Asia/Riyadh'));
        $this->assertFalse(CriticalPush::isQuiet($u->fresh(), $a, now()), 'a member\'s own quiet hours stay');

        $a->forceFill(['ramadan_hours' => false])->save();
        $this->assertFalse(RamadanHours::active($a));
        $this->travelTo(Carbon::parse('2027-03-20 09:00', 'Asia/Riyadh'));
        $a->forceFill(['ramadan_hours' => null])->save();
        $this->assertFalse(RamadanHours::active($a), 'off outside Ramadan');
    }

    public function test_pacing_follows_the_clients_weekday_pattern(): void
    {
        [$a, , $c, $acc] = $this->agencyWithAccount();
        $today = Carbon::parse('2026-11-13', 'Asia/Riyadh'); // a Friday
        $this->travelTo($today->copy()->setTime(10, 0));
        for ($i = 1; $i <= 56; $i++) {
            $d = $today->copy()->subDays($i);
            $spend = $d->isFriday() ? 20 : 120; // Fridays dip
            DailyMetric::create(['agency_id' => $a->id, 'client_id' => $c->id, 'ad_account_id' => $acc->id, 'date' => $d->toDateString(), 'network' => 'meta', 'currency' => 'SAR', 'spend' => $spend, 'results' => 1]);
        }
        $c->forceFill(['monthly_budget' => 3000])->save();

        $curve = Pacing::weekdayCurve($c, $today);
        $this->assertNotNull($curve);
        $this->assertLessThan(0.3, $curve[5], 'Friday is well below an average day');

        $p = Pacing::month($c->fresh(), $today);
        $this->assertTrue($p['weekday_curve']);
        // 12 complete days (Nov 1–12), one of them a Friday (Nov 6): expected is above the flat 12/30 share.
        $this->assertGreaterThan(3000 * 12 / 30, $p['expected']);
    }

    public function test_short_history_keeps_pacing_flat(): void
    {
        [, , $c] = $this->agencyWithAccount();
        $c->forceFill(['monthly_budget' => 3000])->save();
        $today = Carbon::parse('2026-11-13', 'Asia/Riyadh');
        $this->assertNull(Pacing::weekdayCurve($c, $today));
        $p = Pacing::month($c->fresh(), $today);
        $this->assertFalse($p['weekday_curve']);
        $this->assertEqualsWithDelta(3000 * 12 / 30, $p['expected'], 0.01);
    }
}
