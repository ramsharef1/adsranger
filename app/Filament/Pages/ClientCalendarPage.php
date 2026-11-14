<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Support\ClientCalendar;
use App\Support\CurrentClient;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/** XS-12 (R4): one calendar for all clients: seasons, campaigns ending, reports, follow-ups, licences, pauses ending. */
class ClientCalendarPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Client calendar';

    protected static ?string $title = 'Client calendar';

    protected static ?string $slug = 'client-calendar';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.client-calendar';

    public int $days = 30;

    public ?int $clientId = null;

    /** @var list<string> */
    public array $kinds = [];

    public function mount(): void
    {
        $this->clientId = CurrentClient::id() ?: null;
        $this->kinds = ClientCalendar::KINDS;
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $today = CarbonImmutable::now($a->timezone ?: 'UTC');
        $days = in_array($this->days, [14, 30, 60, 90, 365], true) ? $this->days : 30;
        $events = collect(ClientCalendar::events($a, auth()->user(), $today->toDateString(), $today->addDays($days)->toDateString(), $this->clientId ?: null))
            ->whereIn('kind', $this->kinds)->groupBy('date');

        return ['events' => $events, 'clients' => Client::visibleTo(auth()->user(), $a)->whereIn('status', ['active', 'paused'])->orderBy('name')->pluck('name', 'id'),
            'labels' => ClientCalendar::kindLabels(), 'today' => $today->toDateString()];
    }
}
