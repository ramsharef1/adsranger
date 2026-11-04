<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\GatedByPlan;
use App\Models\AuditLog;
use App\Models\BudgetPlan;
use App\Models\Client;
use App\Support\MetricsQuery;
use App\Support\Pacing;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/** Annual budget plan vs actual (#45): 12 months per client, plan vs actual vs forecast. */
class BudgetPlanner extends Page
{
    use GatedByPlan;

    protected static string $planFeature = 'annual_budget';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Budget plan';

    protected static ?string $title = 'Budget plan';

    protected static ?string $slug = 'budget-plan';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.pages.budget-planner';

    public ?int $clientId = null;

    public int $year;

    /** @var array<string, array{budget:?string, results:?string}> keyed by Y-m */
    public array $plan = [];

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->clientId = (int) request()->query('client') ?: $this->clients()->first()?->id;
        $this->loadPlan();
    }

    protected function clients()
    {
        return Client::visibleTo(auth()->user(), Filament::getTenant())->orderBy('name')->get();
    }

    protected function client(): ?Client
    {
        return $this->clientId ? Client::visibleTo(auth()->user(), Filament::getTenant())->with('agency')->find($this->clientId) : null;
    }

    public function updatedClientId(): void
    {
        $this->loadPlan();
    }

    public function setYear(int $y): void
    {
        $this->year = max(2020, min(2035, $y));
        $this->loadPlan();
    }

    protected function loadPlan(): void
    {
        $this->plan = [];
        $c = $this->client();
        $rows = $c ? BudgetPlan::where('client_id', $c->id)->whereYear('month', $this->year)->get()->keyBy(fn ($r) => $r->month->format('Y-m')) : collect();
        for ($m = 1; $m <= 12; $m++) {
            $k = sprintf('%04d-%02d', $this->year, $m);
            $this->plan[$k] = ['budget' => isset($rows[$k]) && $rows[$k]->budget !== null ? (string) $rows[$k]->budget : '',
                'results' => isset($rows[$k]) && $rows[$k]->target_results !== null ? (string) $rows[$k]->target_results : ''];
        }
    }

    /** Fill every empty month of the year with the client's monthly budget / targets. */
    public function fillFromClient(): void
    {
        $c = $this->client();
        if (! $c) {
            return;
        }
        foreach ($this->plan as $k => $v) {
            $this->plan[$k]['budget'] = $v['budget'] !== '' ? $v['budget'] : (string) ($c->monthly_budget ?? '');
            $this->plan[$k]['results'] = $v['results'] !== '' ? $v['results'] : (string) ($c->target_results ?? '');
        }
    }

    public function save(): void
    {
        $a = Filament::getTenant();
        $c = $this->client();
        if (! $c || ! TeamRoles::canManageData(auth()->user(), $a)) {
            abort(403);
        }
        $this->validate([
            'plan' => 'array|size:12',
            'plan.*.budget' => 'nullable|numeric|min:0|max:999999999',
            'plan.*.results' => 'nullable|integer|min:0|max:999999999',
        ], [], ['plan.*.budget' => 'budget', 'plan.*.results' => 'target results']);
        $n = 0;
        foreach ($this->plan as $k => $v) {
            if (! preg_match('/^'.$this->year.'-(0[1-9]|1[0-2])$/', $k)) {
                continue;
            }
            $budget = $v['budget'] === '' || $v['budget'] === null ? null : (float) $v['budget'];
            $results = $v['results'] === '' || $v['results'] === null ? null : (int) $v['results'];
            if ($budget === null && $results === null) {
                BudgetPlan::where('client_id', $c->id)->whereDate('month', "$k-01")->delete();

                continue;
            }
            BudgetPlan::updateOrCreate(['client_id' => $c->id, 'month' => "$k-01"], ['agency_id' => $a->id, 'budget' => $budget, 'target_results' => $results]);
            $n++;
        }
        AuditLog::record('budget_plan.saved', $a, ['client_id' => $c->id, 'year' => $this->year, 'months' => $n]);
        Notification::make()->title('Budget plan saved')->success()->send();
    }

    protected function getViewData(): array
    {
        $c = $this->client();
        $actual = [];
        $pace = null;
        if ($c) {
            $from = "{$this->year}-01-01";
            $to = min("{$this->year}-12-31", now($c->agency->timezone ?: 'UTC')->subDay()->toDateString());
            if ($to >= $from) {
                foreach (MetricsQuery::daily($c, $from, $to) as $d => $t) {
                    $m = substr($d, 0, 7);
                    $actual[$m]['spend'] = ($actual[$m]['spend'] ?? 0) + $t['spend'];
                    $actual[$m]['results'] = ($actual[$m]['results'] ?? 0) + $t['main_results'];
                }
            }
            $pace = Pacing::month($c);
        }

        return ['clients' => $this->clients(), 'client' => $c, 'actual' => $actual, 'pace' => $pace,
            'canManage' => TeamRoles::canManageData(auth()->user(), Filament::getTenant())];
    }
}
