<?php

namespace App\Filament\Resources\Alerts\Pages;

use App\Filament\Pages\AlertRules;
use App\Filament\Resources\Alerts\AlertResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAlerts extends ListRecords
{
    protected static string $resource = AlertResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rules')->label('Alert rules')->icon('heroicon-o-adjustments-horizontal')->color('gray')->url(fn () => AlertRules::getUrl()),
        ];
    }
}
