<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\Campaign;
use App\Support\HealthScore;
use App\Support\MetricsQuery;
use App\Support\Pacing;
use App\Support\Periods;
use App\Support\PlanGate;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Client results: KPIs by goal, 6-month trend, campaigns → ad sets → ads drill-down, top ads. */
class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected string $view = 'filament.pages.client-results';

    public string $period = '30d';

    public ?int $campaign = null;

    public ?int $adSet = null;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Settings & targets')];
    }

    public function setPeriod(string $p): void
    {
        $this->period = array_key_exists($p, Periods::options()) ? $p : '30d';
    }

    public function openCampaign(?int $id): void
    {
        $this->campaign = $id;
        $this->adSet = null;
    }

    public function openAdSet(?int $id): void
    {
        $this->adSet = $id;
    }

    protected function getViewData(): array
    {
        $c = $this->record;
        [$from, $to] = Periods::range($this->period, $c->agency->timezone ?? 'UTC');
        [$pf, $pt] = Periods::previous($from, $to);
        $level = $this->adSet ? 'ad' : ($this->campaign ? 'ad_set' : 'campaign');

        $t = MetricsQuery::totals($c, $from, $to);

        return [
            'client' => $c,
            'health' => HealthScore::compute($c),
            'pace' => Pacing::month($c),
            'forecastLocked' => PlanGate::locked($c->agency, 'forecast'),
            'canUpgrade' => PlanGate::canUpgrade(auth()->user(), $c->agency),
            'funnel' => MetricsQuery::funnel($t),
            'actions' => MetricsQuery::conversionActions($c, $from, $to),
            'from' => $from, 'to' => $to,
            'periods' => Periods::options(),
            't' => $t,
            'prev' => MetricsQuery::totals($c, $pf, $pt),
            'months' => MetricsQuery::monthly($c, 6),
            'level' => $level,
            'rows' => MetricsQuery::breakdown($c, $from, $to, $level, $this->adSet ?: $this->campaign),
            'crumbs' => [
                'campaign' => $this->campaign ? Campaign::find($this->campaign)?->name : null,
                'ad_set' => $this->adSet ? AdSet::find($this->adSet)?->name : null,
            ],
            'top' => MetricsQuery::topAds($c, $from, $to),
            'alerts' => Alert::where('client_id', $c->id)->where('status', 'open')->latest()->limit(5)->get(),
            'synced' => MetricsQuery::lastSynced($c),
            'accounts' => $c->adAccounts()->with('network')->get(),
        ];
    }
}
