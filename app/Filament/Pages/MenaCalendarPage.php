<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Support\MenaCalendar;
use App\Support\PlanGate;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/** MENA calendar with Hijri dates and seasonality (#40) + Ramadan / Eid scenario planner (#96). */
class MenaCalendarPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-moon';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'MENA calendar';

    protected static ?string $title = 'MENA calendar & season planner';

    protected static ?string $slug = 'calendar';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.mena-calendar';

    public ?string $market = null;

    public ?int $clientId = null;

    public ?string $event = null; // "key|start"

    public float $change = 0;

    public function mount(): void
    {
        $this->market = Filament::getTenant()->country ?? null;
    }

    public function plan(string $key, string $start): void
    {
        // #96 scenario planner is Pro; the button opens the locked modal instead (EX-50), so this is only a guard.
        abort_if(PlanGate::locked(Filament::getTenant(), 'scenario_planner'), 403);
        $this->event = $key.'|'.$start;
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $today = now($a->timezone ?: 'UTC')->toDateString();
        $events = MenaCalendar::events($today, now()->addMonths(12)->toDateString(), $this->market ?: null);
        $clients = Client::visibleTo(auth()->user(), $a)->where('status', 'active')->orderBy('name')->get();
        $client = $this->clientId ? $clients->firstWhere('id', $this->clientId) : null;

        $scenario = null;
        $picked = null;
        if ($this->event) {
            [$k, $s] = explode('|', $this->event) + [null, null];
            $picked = collect($events)->first(fn ($e) => $e['key'] === $k && $e['start'] === $s);
            if ($picked && $client) {
                $scenario = MenaCalendar::scenario($client->loadMissing('agency'), $picked, max(-90, min(300, $this->change)));
            }
        }
        $seasons = [];
        if ($client) {
            foreach (array_unique(array_column($events, 'key')) as $k) {
                $seasons[$k] = MenaCalendar::seasonality($client->loadMissing('agency'), $k);
            }
        }

        return ['events' => $events, 'today' => $today, 'clients' => $clients, 'client' => $client, 'picked' => $picked, 'scenario' => $scenario,
            'seasons' => $seasons, 'countries' => config('adsranger.countries'), 'intl' => MenaCalendar::hasIntl(),
            'scenarioLocked' => PlanGate::lockedShown($a, 'scenario_planner'), 'canUpgrade' => PlanGate::canUpgrade(auth()->user(), $a)];
    }
}
