<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Jobs\SnapshotHealth;
use App\Models\Client;
use App\Support\HealthScore;
use App\Support\Pacing;
use App\Support\PlanGate;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/** Pacing board: every client's month so far, forecast to month end, and health score (#35, #36, #37). */
class PacingBoard extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Pacing & health';

    protected static ?string $title = 'Pacing & health';

    protected static ?string $slug = 'pacing';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.pages.pacing-board';

    public string $sort = 'health';

    public function setSort(string $s): void
    {
        $this->sort = in_array($s, ['health', 'pace', 'name'], true) ? $s : 'health';
    }

    /** Recalculate health scores now (at most every 10 minutes per workspace). */
    public function recalc(): void
    {
        $a = Filament::getTenant();
        if (! TeamRoles::canManageData(auth()->user(), $a)) {
            return;
        }
        $key = 'health-recalc:'.$a->id;
        if (Cache::has($key)) {
            Notification::make()->title(__('Scores were recalculated less than 10 minutes ago'))->info()->send();

            return;
        }
        Cache::put($key, 1, 600);
        $today = now($a->timezone ?: 'UTC')->startOfDay();
        Client::visibleTo(auth()->user(), $a)->where('status', 'active')->get()->each(fn ($c) => SnapshotHealth::store($c, $today));
        Notification::make()->title(__('Health scores updated'))->success()->send();
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $clients = Client::visibleTo(auth()->user(), $a)->where('status', 'active')->with('agency')->orderBy('name')->get();
        $rows = $clients->map(fn (Client $c) => ['client' => $c, 'p' => Pacing::month($c), 'band' => HealthScore::band($c->health_score)])->all();
        usort($rows, match ($this->sort) {
            'pace' => fn ($x, $y) => abs(($y['p']['pace'] ?? 100) - 100) <=> abs(($x['p']['pace'] ?? 100) - 100),
            'name' => fn ($x, $y) => strcasecmp($x['client']->name, $y['client']->name),
            default => fn ($x, $y) => ($x['client']->health_score ?? 101) <=> ($y['client']->health_score ?? 101),
        });

        return ['rows' => $rows, 'canManage' => TeamRoles::canManageData(auth()->user(), $a), 'tz' => $a->timezone ?: 'UTC',
            'scored' => $clients->max('health_at'),
            // #37 month-end forecast is Pro (EX-50: the column stays, the value locks).
            'forecastLocked' => PlanGate::locked($a, 'forecast'), 'canUpgrade' => PlanGate::canUpgrade(auth()->user(), $a)];
    }
}
