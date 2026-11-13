<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Support\Import\ClientSheet;
use App\Support\Import\ImportSheet;
use App\Support\TeamRoles;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListClients extends ListRecords
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('Add client')),
            // WS-03 / H-14 (D-297): many clients from a sheet, with a preview; existing names are skipped.
            Action::make('importClients')->label(__('Import clients from a sheet'))->icon('heroicon-o-table-cells')->color('gray')
                ->visible(fn () => TeamRoles::canManageData(auth()->user(), Filament::getTenant()))
                ->modalDescription(new HtmlString(e(__('One row per client. Arabic or English headers; only the name is required.')).' <a style="text-decoration:underline" href="'
                    .e(route('filament.app.clients-template', ['tenant' => Filament::getTenant()?->slug])).'">'.e(__('Download the template')).'</a>'))
                ->schema([
                    FileUpload::make('file')->label(__('CSV or Excel file'))->disk('local')->directory('imports')->storeFileNamesIn('file_name')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(2048)->required()->live(),
                    Placeholder::make('preview')->label(__('Preview'))->visible(fn (Get $get) => filled($get('file')))
                        ->content(fn (Get $get) => static::previewHtml($get('file'))),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);
                    try {
                        $rows = ImportSheet::fromFile($path, (string) ($data['file_name'] ?? $data['file']));
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }
                    $r = ClientSheet::import(Filament::getTenant(), $rows);
                    Notification::make()->title(__('Clients added: :n', ['n' => $r['created']]))
                        ->body(collect([
                            $r['skipped'] ? __('Already there, skipped: :n', ['n' => $r['skipped']]) : null,
                            $r['over_limit'] ? __('Not added, over the plan limit: :n', ['n' => $r['over_limit']]) : null,
                        ])->filter()->implode(' · ') ?: null)
                        ->{$r['over_limit'] ? 'warning' : 'success'}()->send();
                }),
        ];
    }

    public static function previewHtml(mixed $file): HtmlString
    {
        $f = is_array($file) ? reset($file) : $file;
        try {
            $rows = match (true) {
                $f instanceof TemporaryUploadedFile => ImportSheet::fromFile($f->getRealPath(), $f->getClientOriginalName()),
                is_string($f) && Storage::disk('local')->exists($f) => ImportSheet::fromFile(Storage::disk('local')->path($f), $f),
                default => null,
            };
            if ($rows === null) {
                return new HtmlString('');
            }
            $p = ClientSheet::parse(Filament::getTenant(), $rows);
        } catch (\Throwable $e) {
            return new HtmlString('<span style="color:#dc2626">'.e($e->getMessage()).'</span>');
        }
        if ($p['missing_name']) {
            return new HtmlString('<span style="color:#dc2626">'.e(__('No client name column was found. Use the template headers.')).'</span>');
        }
        $list = collect($p['clients'])->take(8)->map(fn ($c) => '<li><bdi>'.e($c['name']).'</bdi>'.($c['monthly_budget'] ? ' · <bdi dir="ltr">'.e(number_format($c['monthly_budget'], 2).' '.$c['currency']).'</bdi>' : '').'</li>')->implode('');

        return new HtmlString('<div data-client-preview style="font-size:13px">'.e(__('New clients: :n', ['n' => count($p['clients'])]))
            .($p['skipped'] ? ' · '.e(__('Already there, skipped: :n', ['n' => count($p['skipped'])])) : '').'<ul style="margin:6px 0 0;padding-inline-start:18px">'.$list.'</ul></div>');
    }
}
