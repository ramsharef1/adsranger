<?php

namespace App\Support;

use App\Filament\Pages\ExportData;
use App\Filament\Pages\PlanUsage;
use App\Filament\Pages\TrialEnded;
use App\Models\Agency;
use Filament\Facades\Filament;
use Filament\Pages\BasePage;
use Filament\Widgets\Widget;

use function Livewire\on;

/**
 * D-61 read-only workspace (self-signup trial ended): every Livewire call on an /app page or widget that could
 * change something is refused with the EX-50 style notice (TrialNotice). Viewing keeps working: period / sort /
 * pagination / filter calls, opening view-only modals, and downloads (PDF, CSV, workspace ZIP).
 *
 * It is an allow-list: a method or action not listed here is refused, so a new write action is read-only-safe by
 * default. Property updates (wire:model) only change on-screen state; nothing is saved without a call.
 */
class ReadOnlyGuard
{
    /** Page / table methods that only change what is shown. */
    public const VIEW_METHODS = [
        // our pages
        'setDay', 'setPeriod', 'setHeat', 'setSort', 'setYear', 'openCampaign', 'openAdSet', 'reportsLocked', 'canManageLinks', 'canEdit',
        // Filament tables / schemas / actions (view side)
        'sortTable', 'gotoPage', 'nextPage', 'previousPage', 'setPage', 'resetPage', 'loadTable', 'loadMoreTableRecords',
        'removeTableFilter', 'removeTableFilters', 'resetTableFiltersForm', 'applyTableFilters', 'resetTableSearch',
        'resetTableColumnSearch', 'toggleAllTableColumns', 'applyTableColumnManager', 'resetTableColumnManager',
        'getAllSelectableTableRecordKeys', 'getAllSelectableTableRecordsCount', 'getGroupedSelectableTableRecordKeys',
        'deselectAllTableRecords', 'callSchemaComponentMethod', 'unmountAction', 'getActiveTableLocale', 'setActiveTab',
    ];

    /** Actions that only show or download (their modal may open and submit). */
    public const VIEW_ACTIONS = ['view', 'monthlyPdf', 'exportCsv', 'mapping'];

    /** Pages that stay fully usable: they only read or download. */
    public const OPEN_COMPONENTS = [ExportData::class, PlanUsage::class, TrialEnded::class];

    public static function register(): void
    {
        on('call', function (object $component, string $method, array $params, $context, $returnEarly) {
            if (static::allows($component, $method, $params)) {
                return;
            }
            $agency = Filament::getTenant();
            TrialNotice::readOnly($agency, auth()->user())->send();
            $returnEarly(null);
        });
    }

    public static function allows(object $component, string $method, array $params = []): bool
    {
        if (! ($component instanceof BasePage || $component instanceof Widget)) {
            return true;
        }
        if (Filament::getCurrentPanel()?->getId() !== 'app') {
            return true;
        }
        $agency = Filament::getTenant();
        if (! $agency instanceof Agency || ! Trial::isReadOnly($agency)) {
            return true; // invited / admin / paying agencies are never read-only here
        }
        if (str_starts_with($method, '$') || str_starts_with($method, '__') || in_array($method, static::VIEW_METHODS, true)) {
            return true;
        }
        foreach (static::OPEN_COMPONENTS as $open) {
            if ($component instanceof $open) {
                return true;
            }
        }
        if ($method === 'mountAction') {
            return in_array($params[0] ?? null, static::VIEW_ACTIONS, true);
        }
        if ($method === 'callMountedAction') {
            $mounted = property_exists($component, 'mountedActions') ? (array) $component->mountedActions : [];
            $last = end($mounted) ?: [];

            return in_array($last['name'] ?? null, static::VIEW_ACTIONS, true);
        }

        return false;
    }
}
