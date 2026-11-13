<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\PlatformSetting;
use App\Support\Announcements;
use App\Support\Ops\ErrorLookup;
use App\Support\Ops\Heartbeat;
use App\Support\Ops\JobRuns;
use App\Support\Ops\LaunchReadiness;
use App\Support\Ops\LiveSiteCheck;
use App\Support\Ops\NetworkDeadlines;
use App\Support\Ops\Prune;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/** R1 (D-289): heartbeat, automatic launch checks (EG-13), scheduled task runs (EG-07), retention (EG-01), error lookup (EG-08). */
class OperationsBoard extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-server-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Operations';

    protected static ?string $title = 'Operations';

    protected static ?string $slug = 'operations';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.admin.pages.operations';

    public string $ref = '';

    /** @var list<array{file: string, lines: list<string>}>|null */
    public ?array $found = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = count(JobRuns::problems()) + (Heartbeat::fresh() || Heartbeat::last() === null ? 0 : 1);

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public string $noteAr = '';

    public string $noteEn = '';

    public string $noteTone = 'info';

    public int $noteDays = 3;

    /** EH-17 (D-293): post the one app-wide announcement line (ends the previous one). */
    public function postAnnouncement(): void
    {
        $this->validate([
            'noteAr' => ['required', 'string', 'max:240'],
            'noteEn' => ['required', 'string', 'max:240'],
            'noteTone' => ['required', 'in:info,warning'],
            'noteDays' => ['required', 'integer', 'min:1', 'max:30'],
        ]);
        Announcements::post($this->noteAr, $this->noteEn, $this->noteTone, $this->noteDays, auth()->id());
        $this->reset('noteAr', 'noteEn');
    }

    public function endAnnouncement(): void
    {
        Announcements::end();
    }

    public function lookup(): void
    {
        $this->found = ErrorLookup::find($this->ref);
    }

    protected function getViewData(): array
    {
        return [
            'heartbeat' => Heartbeat::status(),
            'checks' => LaunchReadiness::checks(),
            'jobs' => JobRuns::board(),
            'retention' => Prune::table(),
            'lastPrune' => PlatformSetting::get(Prune::LAST_KEY),
            'deadlines' => NetworkDeadlines::all(),
            'announcement' => Announcements::current(),
            'liveSite' => PlatformSetting::get(LiveSiteCheck::LAST_KEY),
            'drivers' => ['lanes' => (bool) config('ops.lanes_enabled'), 'cache' => (string) config('cache.default'), 'session' => (string) config('session.driver')],
        ];
    }
}
