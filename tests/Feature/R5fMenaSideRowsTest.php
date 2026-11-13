<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\HolidayStatusBoard;
use App\Models\Agency;
use App\Models\User;
use App\Support\DataExport;
use App\Support\MenaCalendar;
use App\Support\PrayerWindows;
use App\Support\ResultResolver;
use App\Support\WorkingDays;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** R5 side rows (D-308): DS-24 3-decimal currencies, DS-26 working days, WK-12 prayer windows, DS-25 holiday status. */
class R5fMenaSideRowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_decimal_currencies(): void
    {
        $this->assertSame('1,234.500 KWD', ResultResolver::money(1234.5, 'KWD'));
        $this->assertSame('12.000 JOD', ResultResolver::money(12, 'JOD'));
        $this->assertSame('1,234.50 SAR', ResultResolver::money(1234.5, 'SAR'));
        $this->assertSame('0.450 SAR', ResultResolver::money(0.45, 'SAR'));
        $money = (new \ReflectionMethod(DataExport::class, 'money'));
        $this->assertSame('99.100', $money->invoke(null, 99.1, 'BHD'));
        $this->assertSame('99.10', $money->invoke(null, 99.1, 'USD'));
    }

    public function test_working_days_follow_the_uae_weekend_change(): void
    {
        $this->assertSame([5, 6], WorkingDays::weekendOn('AE', '2021-06-01'));
        $this->assertSame([6, 7], WorkingDays::weekendOn('AE', '2022-06-01'));
        $this->assertSame([5, 6], WorkingDays::weekendOn('SA', '2022-06-01'));
        // 2026-11-01 (Sun) … 2026-11-07 (Sat): KSA has 5 working days (Sun–Thu).
        $this->assertSame(5, WorkingDays::count('SA', '2026-11-01', '2026-11-07'));
        $now = ['spend' => 1100.0, 'main_results' => 22.0];
        $before = ['spend' => 1000.0, 'main_results' => 20.0];
        $this->travelTo(Carbon::parse('2026-12-01'));
        $c = WorkingDays::compare('SA', '2026-11-01', '2026-11-11', '2026-10-22', '2026-10-31', $now, $before);
        $this->assertNotNull($c);
        $this->assertSame(9, $c['days_now']);
        $this->assertSame(6, $c['days_before']);
        $this->assertLessThan(0, $c['spend_pct'], '10% more spend over 2 more working days is less per day');
        $this->assertNull(WorkingDays::compare('SA', '2026-11-01', '2026-11-07', '2026-10-25', '2026-10-31', $now, $before), 'same working days: no line');
    }

    public function test_prayer_windows(): void
    {
        $a = Agency::create(['name' => 'Acme', 'slug' => 'acme-r5f', 'plan' => 'pro', 'status' => 'active', 'timezone' => 'Asia/Riyadh']);
        $this->assertSame('jumuah', PrayerWindows::now($a, Carbon::parse('2026-11-13 12:00', 'Asia/Riyadh')));
        $this->assertNull(PrayerWindows::now($a, Carbon::parse('2026-11-12 12:00', 'Asia/Riyadh')), 'Thursday');
        $this->assertSame('taraweeh', PrayerWindows::now($a, Carbon::parse('2027-02-15 21:00', 'Asia/Riyadh')));
        $this->assertNull(PrayerWindows::now($a, Carbon::parse('2026-11-12 21:00', 'Asia/Riyadh')), 'not Ramadan');
        $a->forceFill(['prayer_quiet' => false])->save();
        $this->assertNull(PrayerWindows::now($a, Carbon::parse('2026-11-13 12:00', 'Asia/Riyadh')));
    }

    public function test_admin_confirms_a_holiday_and_calendars_follow(): void
    {
        $this->travelTo(Carbon::parse('2027-01-20'));
        $admin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $ramadan = collect(MenaCalendar::events('2027-02-01', '2027-02-28'))->firstWhere('key', 'ramadan');
        $this->assertSame('expected', $ramadan['status']);
        $key = 'ramadan|'.substr($ramadan['calc_start'], 0, 4);

        Livewire::test(HolidayStatusBoard::class)->assertSee('2027-02-08')
            ->set("rows.$key.status", 'confirmed')->set("rows.$key.start_date", '2027-02-09')->set("rows.$key.source", 'Supreme Court')
            ->call('save', $key);

        $after = collect(MenaCalendar::events('2027-02-01', '2027-02-28'))->firstWhere('key', 'ramadan');
        $this->assertSame('confirmed', $after['status']);
        $this->assertSame('2027-02-09', $after['start']);
        $this->assertSame('Supreme Court', $after['source']);
    }
}
