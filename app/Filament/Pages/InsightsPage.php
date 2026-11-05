<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\GatedByPlan;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Support\Insights;
use App\Support\Periods;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/** Client insights: budget planner (#38), reallocation (#39), hours (#43), placements + audience (#44), geo (#97), saturation + overlap (#98). */
class InsightsPage extends Page
{
    use GatedByPlan;
    use \App\Filament\Concerns\FollowsCurrentClient;
    use TranslatesPageLabels;

    protected static string $planFeature = 'insights';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Insights';

    protected static ?string $title = 'Insights';

    protected static ?string $slug = 'insights';

    protected static ?int $navigationSort = 23;

    protected string $view = 'filament.pages.insights';

    public ?int $clientId = null;

    public string $period = '30d';

    public string $heat = 'results';

    public function mount(): void
    {
        $this->clientId = \App\Support\CurrentClient::id() ?: $this->clients()->first()?->id;
    }

    protected function clients()
    {
        return Client::visibleTo(auth()->user(), Filament::getTenant())->where('status', 'active')->orderBy('name')->get();
    }

    public function setPeriod(string $p): void
    {
        $this->period = in_array($p, ['7d', '14d', '30d', '90d'], true) ? $p : '30d';
    }

    public function setHeat(string $h): void
    {
        $this->heat = in_array($h, ['results', 'cpr', 'spend'], true) ? $h : 'results';
    }

    protected function getViewData(): array
    {
        $clients = $this->clients();
        $c = $this->clientId ? $clients->firstWhere('id', $this->clientId)?->loadMissing('agency') : null;
        $data = ['clients' => $clients, 'client' => $c, 'periods' => ['7d' => __('Last 7 days'), '14d' => __('Last 14 days'), '30d' => __('Last 30 days'), '90d' => __('Last 90 days')]];
        if (! $c) {
            return $data;
        }
        [$from, $to] = Periods::range($this->period, $c->agency->timezone ?: 'UTC');

        return $data + [
            'from' => $from, 'to' => $to,
            'curve' => Insights::curve($c),
            'realloc' => Insights::reallocation($c),
            'heatmap' => Insights::heatmap($c, $from, $to),
            'placements' => Insights::breakdown($c, 'placement', $from, $to),
            'devices' => Insights::breakdown($c, 'device', $from, $to),
            'geo' => Insights::breakdown($c, 'region', $from, $to),
            'audience' => Insights::breakdown($c, 'age_gender', $from, $to),
            'saturation' => Insights::saturation($c),
        ];
    }
}
