<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\Client;
use App\Support\GoalCalculator;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Reverse-funnel goal calculator (#88, Starter). Pick a client and a target; the page works back to the clicks,
 * impressions and budget needed. Rates start from the client's last 90 days (or marked starting assumptions) and can
 * be changed. Nothing is saved, so every role can use it.
 */
class GoalCalculatorPage extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Goal calculator';

    protected static ?string $title = 'Goal calculator';

    protected static ?string $slug = 'goal-calculator';

    protected static ?int $navigationSort = 22;

    protected string $view = 'filament.pages.goal-calculator';

    public ?int $clientId = null;

    /** purchases | revenue | leads */
    public string $goal = 'purchases';

    public string $target = '';

    public int $days = 30;

    /** @var array<string, string> % per step */
    public array $rates = [];

    public string $cpm = '';

    public string $aov = '';

    /** @var array<string, bool> which rates came from the client's own data */
    public array $fromHistory = [];

    public function mount(): void
    {
        $this->clientId = (int) request()->query('client') ?: $this->clients()->first()?->id;
        $this->resetInputs();
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
        $this->resetInputs();
    }

    public function updatedGoal(): void
    {
        if (! in_array($this->goal, ['purchases', 'revenue', 'leads'], true)) {
            $this->goal = 'purchases';
        }
        $this->resetInputs();
    }

    /** Refill rates, CPM and AOV from the client's history (or the starting assumptions). */
    public function resetInputs(): void
    {
        $c = $this->client();
        $h = $c ? GoalCalculator::history($c) : ['rates' => [], 'cpm' => null, 'aov' => null];
        $this->rates = [];
        $this->fromHistory = [];
        foreach (GoalCalculator::steps($this->goal) as $i => $k) {
            if ($i === 0) {
                continue;
            }
            $has = isset($h['rates'][$k]);
            $this->rates[$k] = (string) ($has ? $h['rates'][$k] : GoalCalculator::DEFAULT_RATES[$k]);
            $this->fromHistory[$k] = $has;
        }
        $this->cpm = $h['cpm'] !== null ? (string) $h['cpm'] : '';
        $this->fromHistory['cpm'] = $h['cpm'] !== null;
        $this->aov = $h['aov'] !== null ? (string) $h['aov'] : '';
        $this->fromHistory['aov'] = $h['aov'] !== null;
    }

    protected function num(string $v): ?float
    {
        $v = str_replace([',', '٬'], '', trim($v));

        return is_numeric($v) ? (float) $v : null;
    }

    protected function getViewData(): array
    {
        $c = $this->client();
        $h = $c ? GoalCalculator::history($c) : null;
        $this->days = max(1, min(366, (int) $this->days));
        $target = $this->num($this->target);
        $plan = $target !== null ? GoalCalculator::plan($this->goal, $target, $this->rates, $this->num($this->cpm), $this->num($this->aov), $this->days) : null;
        $goalKey = $this->goal === 'leads' ? 'leads' : 'purchases';
        $recentCost = $h['cost_per'][$goalKey] ?? null;

        return [
            'clients' => $this->clients(), 'client' => $c, 'history' => $h, 'plan' => $plan,
            'atRecentCost' => $plan && $recentCost ? round($plan['goal_count'] * $recentCost, 2) : null,
            'needsAov' => $this->goal === 'revenue' && ! $this->num($this->aov),
            'needsCpm' => $target !== null && ! $this->num($this->cpm),
        ];
    }
}
