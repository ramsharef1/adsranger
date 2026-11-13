<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Support\RamadanReadiness;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/** FS-39 (R4): is each client ready for Ramadan? One score and the missing steps. */
class RamadanReadinessPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Ramadan readiness';

    protected static ?string $title = 'Ramadan readiness';

    protected static ?string $slug = 'ramadan-readiness';

    protected static ?int $navigationSort = 23;

    protected string $view = 'filament.pages.ramadan-readiness';

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $next = RamadanReadiness::next(now($a->timezone ?: 'UTC'));

        return ['next' => $next, 'rows' => $next ? RamadanReadiness::for($a, auth()->user(), $next['start']) : []];
    }
}
