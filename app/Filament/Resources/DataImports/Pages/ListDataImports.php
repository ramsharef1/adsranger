<?php

namespace App\Filament\Resources\DataImports\Pages;

use App\Filament\Resources\DataImports\DataImportResource;
use App\Models\Client;
use App\Support\MetricsImporter;
use App\Support\TeamRoles;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListDataImports extends ListRecords
{
    protected static string $resource = DataImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')->label(__('Import data'))->icon('heroicon-o-arrow-up-tray')
                ->visible(fn () => TeamRoles::canManageData(auth()->user(), Filament::getTenant()))
                ->modalDescription(__('Columns are matched automatically (English or Arabic headers). A date and a spend column are required; impressions, clicks, results, revenue and campaign are optional.'))
                ->schema([
                    Select::make('network')->label(__('Network'))->options(DataImportResource::networkOptions())->searchable()->required(),
                    Select::make('client_id')->label(__('Client'))->options(fn () => Client::where('agency_id', Filament::getTenant()->id)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->helperText(__('Rows count toward this client\'s results. Results = the conversions / results column.')),
                    FileUpload::make('file')->label(__('CSV file'))->disk('local')->directory('imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])->maxSize(10240)
                        ->helperText(__('Export from the network as CSV (up to 10 MB, 50,000 rows).')),
                    TextInput::make('sheet')->label(__('…or a Google Sheets link'))->url()
                        ->helperText(__('Share the sheet as "Anyone with the link can view". The first tab is used unless the link has #gid=.')),
                ])
                ->action(function (array $data) {
                    $agency = Filament::getTenant();
                    try {
                        if (! empty($data['file'])) {
                            $csv = Storage::disk('local')->get($data['file']);
                            Storage::disk('local')->delete($data['file']);
                            $import = MetricsImporter::import($agency, auth()->user(), $data['network'], (string) $csv, basename($data['file']), 'file', $data['client_id'] ?? null);
                        } elseif (! empty($data['sheet'])) {
                            $import = MetricsImporter::import($agency, auth()->user(), $data['network'], MetricsImporter::fetchSheet($data['sheet']), $data['sheet'], 'sheet', $data['client_id'] ?? null);
                        } else {
                            Notification::make()->title(__('Choose a CSV file or paste a Google Sheets link'))->warning()->send();

                            return;
                        }
                        Notification::make()->title(trans_choice('{1} :count row imported|[2,*] :count rows imported', $import->rows))->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('Import failed'))->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }
}
