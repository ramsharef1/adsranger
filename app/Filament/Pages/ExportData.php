<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Http\Controllers\ExportController;
use App\Models\Client;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use UnitEnum;

/**
 * #79 Data › Export data: CSV downloads of what this user can see (daily metrics for a period, clients, ad
 * accounts) and, for the owner, the whole workspace as a ZIP of CSVs ("download my data").
 */
class ExportData extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Export data';

    protected static ?string $title = 'Export data';

    protected static ?string $slug = 'export-data';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.export-data';

    public static function exportUrl(string $name, array $query = []): string
    {
        return route('filament.app.export.'.$name, ['tenant' => Filament::getTenant()->slug] + $query);
    }

    /** Period fields shared by the workspace and client exports (default: last 30 days to yesterday). */
    public static function periodFields(): array
    {
        $tz = Filament::getTenant()?->timezone ?: 'UTC';
        $yesterday = CarbonImmutable::now($tz)->subDay();

        return [
            DatePicker::make('from')->label(__('From'))->native()->required()->default($yesterday->subDays(29)->toDateString())
                ->maxDate($yesterday->addDay()->toDateString()),
            DatePicker::make('to')->label(__('To'))->native()->required()->default($yesterday->toDateString())->afterOrEqual('from')
                ->maxDate($yesterday->addDay()->toDateString()),
        ];
    }

    public function metricsAction(): Action
    {
        $agency = Filament::getTenant();

        return Action::make('metrics')
            ->label(__('Download daily metrics'))
            ->icon('heroicon-o-arrow-down-tray')
            ->modalHeading(__('Daily metrics CSV'))
            ->modalDescription(__('One row per day, ad account and campaign, in each account\'s own currency.'))
            ->schema([
                Select::make('client')->label(__('Client'))->placeholder(__('All clients you can see'))
                    ->options(fn () => Client::visibleTo(auth()->user(), $agency)->orderBy('name')->pluck('name', 'id'))->searchable(),
                ...static::periodFields(),
            ])
            ->modalSubmitActionLabel(__('Download CSV'))
            ->action(function (array $data) {
                $q = ['from' => $data['from'], 'to' => $data['to']];
                if (! empty($data['client'])) {
                    $q['client'] = $data['client'];
                }
                $this->redirect(static::exportUrl('metrics', $q));
            });
    }

    public function clientsAction(): Action
    {
        return Action::make('clients')->label(__('Download clients'))->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->url(fn () => static::exportUrl('clients'));
    }

    public function accountsAction(): Action
    {
        return Action::make('accounts')->label(__('Download ad accounts'))->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->url(fn () => static::exportUrl('accounts'));
    }

    public function workspaceAction(): Action
    {
        return Action::make('workspace')->label(__('Download all workspace data'))->icon('heroicon-o-archive-box-arrow-down')->color('gray')
            ->visible(fn () => ExportController::canDownloadWorkspace(auth()->user(), Filament::getTenant()))
            ->url(fn () => static::exportUrl('workspace'));
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $role = auth()->user()?->roleIn($agency);

        return [
            'restricted' => ! in_array($role, ['owner', 'admin'], true) && ! auth()->user()?->isSuperAdmin(),
            'canZip' => ExportController::canDownloadWorkspace(auth()->user(), $agency),
        ];
    }
}
