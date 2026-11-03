<?php

namespace App\Filament\Admin\Resources\Agencies\Pages;

use App\Filament\Admin\Resources\Agencies\AgencyResource;
use Filament\Resources\Pages\EditRecord;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
