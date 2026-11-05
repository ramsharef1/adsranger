<?php

namespace App\Filament\Resources\Connections\Pages;

use App\Filament\Resources\Connections\ConnectionResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListConnections extends ListRecords
{
    protected static string $resource = ConnectionResource::class;

    public function mount(): void
    {
        parent::mount();
        if ($m = session('connect_ok')) {
            Notification::make()->title($m)->success()->send();
        }
        if ($m = session('connect_error')) {
            Notification::make()->title(__('Connection failed'))->body($m)->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('networks')->label(__('Connect a network'))->icon('heroicon-o-plus')
                ->url(fn () => url('/app/'.Filament::getTenant()->slug.'/networks')),
        ];
    }
}
