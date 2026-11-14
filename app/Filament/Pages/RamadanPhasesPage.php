<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\RamadanPhases;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Step 1b (E2-079, EH-19): Cross-client Ramadan 6-phase performance & pacing board across visible workspace clients.
 */
class RamadanPhasesPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Ramadan phase board';

    protected static ?string $title = 'Ramadan phase board';

    protected static ?string $slug = 'ramadan-phases';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.ramadan-phases';

    public string $cycleStart = '';

    public string $clientFilter = '';

    public function mount(): void
    {
        $agency = Filament::getTenant();
        if ($agency === null) {
            return;
        }

        $requestedCycle = trim((string) request()->query('cycleStart', ''));
        $requestedClient = trim((string) request()->query('clientFilter', ''));
        if ($requestedClient !== '' && ctype_digit($requestedClient)) {
            $this->clientFilter = $requestedClient;
        }

        $initial = RamadanPhases::board($agency, auth()->user(), $requestedCycle !== '' ? $requestedCycle : null);
        $this->cycleStart = (string) ($initial['selected_cycle']['key'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $clientId = $this->clientFilter !== '' && ctype_digit($this->clientFilter) ? (int) $this->clientFilter : null;
        $board = RamadanPhases::board(
            $agency,
            auth()->user(),
            $this->cycleStart !== '' ? $this->cycleStart : null,
            $clientId,
        );

        return [
            'board' => $board,
        ];
    }
}
