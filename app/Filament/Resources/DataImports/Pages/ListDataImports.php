<?php

namespace App\Filament\Resources\DataImports\Pages;

use App\Filament\Resources\DataImports\DataImportResource;
use App\Models\Client;
use App\Models\DataImport;
use App\Support\Import\ImportSheet;
use App\Support\MetricsImporter;
use App\Support\TeamRoles;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                    Select::make('network')->label(__('Network'))->options(DataImportResource::networkOptions())->searchable()
                        ->placeholder(__('Detect from the file'))->helperText(__('Leave empty: Meta, Google, TikTok and Snapchat exports are recognised by their columns, in English or Arabic.')),
                    Select::make('client_id')->label(__('Client'))->options(fn () => Client::where('agency_id', Filament::getTenant()->id)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->helperText(__('Rows count toward this client\'s results. Results = the conversions / results column.')),
                    FileUpload::make('file')->label(__('CSV or Excel file'))->disk('local')->directory('imports')->preserveFilenames(false)->storeFileNamesIn('file_name')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->maxSize(10240)
                        ->live()
                        ->helperText(__('Export from the network as CSV or .xlsx (up to 10 MB, 50,000 rows).')),
                    // PS-01: a 5-row preview of what will be saved, before saving.
                    Placeholder::make('preview')->label(__('Preview'))
                        ->visible(fn (Get $get) => filled($get('file')))
                        ->content(fn (Get $get) => static::previewHtml($get('file'))),
                    TextInput::make('sheet')->label(__('…or a Google Sheets link'))->url()
                        ->helperText(__('Share the sheet as "Anyone with the link can view". The first tab is used unless the link has #gid=.')),
                ])
                ->action(function (array $data) {
                    $agency = Filament::getTenant();
                    try {
                        if (! empty($data['file'])) {
                            $path = Storage::disk('local')->path($data['file']);
                            $name = (string) ($data['file_name'] ?? basename($data['file']));
                            try {
                                $rows = ImportSheet::fromFile($path, $name);
                            } finally {
                                Storage::disk('local')->delete($data['file']);
                            }
                            $import = MetricsImporter::importRows($agency, auth()->user(), $data['network'] ?? null, $rows, $name, 'file', $data['client_id'] ?? null);
                        } elseif (! empty($data['sheet'])) {
                            $import = MetricsImporter::importRows($agency, auth()->user(), $data['network'] ?? null, ImportSheet::fromCsv(MetricsImporter::fetchSheet($data['sheet'])), $data['sheet'], 'sheet', $data['client_id'] ?? null);
                        } else {
                            Notification::make()->title(__('Choose a CSV file or paste a Google Sheets link'))->warning()->send();

                            return;
                        }
                        Notification::make()->title(__('Import saved'))->success()
                            ->body(static::resultLine($import))->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('Import failed'))->body($e->getMessage())->danger()->persistent()->send();
                    }
                }),
        ];
    }

    /** PS-07 / PS-26: what the import did, in one line. */
    public static function resultLine(DataImport $import): string
    {
        $parts = [__('New rows: :n', ['n' => $import->rows])];
        if ($import->rows_updated) {
            $parts[] = __('Updated since the last upload: :n', ['n' => $import->rows_updated]);
        }
        if ($import->rows_skipped) {
            $parts[] = __('Already there, skipped: :n', ['n' => $import->rows_skipped]);
        }
        if ($import->names_cleaned) {
            $parts[] = __('Names cleaned of hidden marks: :n', ['n' => $import->names_cleaned]);
        }

        return implode(' · ', $parts).' · '.__('Network: :name', ['name' => DataImportResource::networkOptions()[$import->network] ?? $import->network]);
    }

    /** @param  mixed  $file  the upload state (a temporary file while the modal is open) */
    public static function previewHtml(mixed $file): HtmlString
    {
        $f = is_array($file) ? reset($file) : $file;
        try {
            if ($f instanceof TemporaryUploadedFile) {
                $rows = ImportSheet::fromFile($f->getRealPath(), $f->getClientOriginalName());
            } elseif (is_string($f) && Storage::disk('local')->exists($f)) {
                $rows = ImportSheet::fromFile(Storage::disk('local')->path($f), $f);
            } else {
                return new HtmlString('');
            }
            $p = MetricsImporter::preview($rows);
        } catch (\Throwable $e) {
            return new HtmlString('<span style="color:#dc2626">'.e($e->getMessage()).'</span>');
        }

        return new HtmlString(view('filament.partials.import-preview', ['p' => $p, 'networks' => DataImportResource::networkOptions()])->render());
    }
}
