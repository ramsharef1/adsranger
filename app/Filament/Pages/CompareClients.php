<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Support\MetricsQuery;
use App\Support\Periods;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/** Compare two clients over 7 / 14 / 30 / 90 days. */
class CompareClients extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|UnitEnum|null $navigationGroup = 'Results';

    protected static ?string $navigationLabel = 'Compare clients';

    protected static ?string $title = 'Compare two clients';

    protected static ?string $slug = 'compare';

    protected static ?int $navigationSort = 14;

    protected string $view = 'filament.pages.compare-clients';

    public ?int $a = null;

    public ?int $b = null;

    public string $period = '30d';

    public function mount(): void
    {
        $ids = $this->clients()->pluck('id');
        $this->a = $ids[0] ?? null;
        $this->b = $ids[1] ?? null;
    }

    protected function clients()
    {
        return Client::visibleTo(auth()->user(), Filament::getTenant())->where('status', 'active')->orderBy('name')->get();
    }

    protected function getViewData(): array
    {
        $tz = Filament::getTenant()->timezone ?: 'UTC';
        [$from, $to] = Periods::range(in_array($this->period, ['7d', '14d', '30d', '90d'], true) ? $this->period : '30d', $tz);
        $list = $this->clients();
        $pick = fn ($id) => $list->firstWhere('id', $id);
        $side = fn ($c) => $c ? ['client' => $c, 't' => MetricsQuery::totals($c, $from, $to), 'days' => MetricsQuery::daily($c, $from, $to)] : null;

        return ['list' => $list, 'A' => $side($pick($this->a)), 'B' => $side($pick($this->b)), 'from' => $from, 'to' => $to];
    }
}
