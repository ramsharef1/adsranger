<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\MenaCalendar;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** DS-25 (R5): Hijri holidays with «متوقع / مؤكد» status, the announced first day and a source. Feeds every calendar. */
class HolidayStatusBoard extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-moon';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Holiday status';

    protected static ?string $title = 'Holiday status';

    protected static ?string $slug = 'holiday-status';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.admin.pages.holiday-status';

    /** @var array<string, array{status: string, start_date: ?string, source: ?string}> */
    public array $rows = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public function mount(): void
    {
        foreach ($this->events() as $e) {
            $k = $e['key'].'|'.substr($e['calc_start'], 0, 4);
            $this->rows[$k] = ['status' => $e['status'], 'start_date' => $e['start'], 'source' => $e['source'] ?? ''];
        }
    }

    /** @return list<array<string, mixed>> Hijri events of the next 13 months (calc_start = the calculated first day) */
    protected function events(): array
    {
        return collect(MenaCalendar::events(now()->subDays(10)->toDateString(), now()->addMonths(13)->toDateString()))->where('kind', 'hijri')->values()->all();
    }

    public function save(string $key): void
    {
        abort_unless(static::canAccess(), 403);
        [$event, $year] = explode('|', $key) + [null, null];
        $r = $this->rows[$key] ?? null;
        if (! $event || ! $year || ! $r || ! in_array($r['status'], ['expected', 'confirmed'], true)) {
            return;
        }
        $date = $r['start_date'] ? Carbon::parse($r['start_date'])->toDateString() : null;
        DB::table('holiday_statuses')->updateOrInsert(['event_key' => $event, 'year' => (int) $year], [
            'status' => $r['status'], 'start_date' => $date, 'source' => trim(strip_tags((string) $r['source'])) ?: null,
            'updated_by' => auth()->id(), 'updated_at' => now(), 'created_at' => now(),
        ]);
        Notification::make()->title(__('Saved.'))->success()->send();
    }

    protected function getViewData(): array
    {
        return ['events' => $this->events(), 'ar' => app()->getLocale() === 'ar'];
    }
}
