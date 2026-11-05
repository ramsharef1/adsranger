<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\GatedByPlan;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\Benchmarks;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/** Internal benchmarks (#41) and the opt-in MENA benchmarks network (#42). */
class BenchmarksPage extends Page
{
    use GatedByPlan;
    use TranslatesPageLabels;

    protected static string $planFeature = 'benchmarks';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup = 'Planning';

    protected static ?string $navigationLabel = 'Benchmarks';

    protected static ?string $title = 'Benchmarks';

    protected static ?string $slug = 'benchmarks';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.benchmarks';

    /** Owners and admins switch the MENA network on or off for the workspace. */
    public function toggleNetwork(): void
    {
        $a = Filament::getTenant();
        if (! TeamRoles::canManageTeam(auth()->user(), $a)) {
            abort(403);
        }
        $on = ! $a->benchmarks_opt_in;
        $a->forceFill(['benchmarks_opt_in' => $on, 'benchmarks_opt_in_at' => $on ? now() : null])->save();
        Cache::forget('benchmarks:network:v1');
        AuditLog::record($on ? 'benchmarks.opted_in' : 'benchmarks.opted_out', $a, []);
        Notification::make()->title($on ? __('Joined the MENA benchmarks network') : __('Left the MENA benchmarks network'))
            ->body($on ? __('Your anonymised medians are included from the next refresh.') : __('Your data is no longer included from the next refresh (within 12 hours).'))->success()->send();
    }

    protected function getViewData(): array
    {
        $a = Filament::getTenant();
        $visible = Client::visibleTo(auth()->user(), $a)->pluck('id')->map(fn ($i) => (int) $i)->all();

        return [
            'agency' => $a,
            'internal' => Benchmarks::internal($a, $visible),
            'network' => $a->benchmarks_opt_in ? Benchmarks::network() : null,
            'canToggle' => TeamRoles::canManageTeam(auth()->user(), $a),
            'verticals' => config('adsranger.verticals'), 'countries' => config('adsranger.countries'),
        ];
    }
}
