<?php

namespace App\Filament\Pages;

use App\Alerts\AlertEngine;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Support\PlanGate;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

/** Per-agency alert rule switches and thresholds. Owners, admins and managers can edit. */
class AlertRules extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Alert rules';

    protected static ?string $title = 'Alert rules';

    protected static ?string $slug = 'alert-rules';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.alert-rules';

    public array $rules = [];

    public function mount(): void
    {
        foreach (AlertEngine::rules(Filament::getTenant()) as $k => $r) {
            $this->rules[$k] = ['enabled' => $r['enabled'], 't' => $r['t']];
        }
    }

    public function canEdit(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);
        $a = Filament::getTenant();
        foreach (config('alerts.rules') as $k => $def) {
            if (! PlanGate::allowsAlertRule($a, $k)) {
                continue; // locked by plan: keep whatever was stored (EX-50: existing data is never touched)
            }
            $in = $this->rules[$k] ?? [];
            $t = [];
            foreach ($def['thresholds'] as $tk => $dv) {
                $v = $in['t'][$tk] ?? $dv;
                $t[$tk] = is_numeric($v) && $v >= 0 ? $v + 0 : $dv;
            }
            AlertRule::updateOrCreate(['agency_id' => $a->id, 'rule_key' => $k], ['enabled' => (bool) ($in['enabled'] ?? true), 'thresholds' => $t]);
        }
        AuditLog::record('alerts.rules_updated', $a, []);
        Notification::make()->title('Alert rules saved')->body('They apply from the next run (every morning after the sync).')->success()->send();
    }

    public function runNow(): void
    {
        abort_unless($this->canEdit(), 403);
        $a = Filament::getTenant();
        $s = AlertEngine::run($a, now($a->timezone ?: 'UTC')->subDay()->toDateString());
        Notification::make()->title("Rules ran: {$s['opened']} new, {$s['refreshed']} still open, {$s['resolved']} resolved")->success()->send();
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $locked = collect(array_keys(config('alerts.rules')))->filter(fn ($k) => ! PlanGate::allowsAlertRule($a, $k))->values()->all();

        return ['defs' => config('alerts.rules'), 'edit' => $this->canEdit(), 'locked' => $locked,
            'canUpgrade' => PlanGate::canUpgrade(auth()->user(), $a)];
    }
}
