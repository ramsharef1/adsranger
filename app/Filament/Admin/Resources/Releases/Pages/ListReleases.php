<?php

namespace App\Filament\Admin\Resources\Releases\Pages;

use App\Filament\Admin\Resources\Releases\ReleaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReleases extends ListRecords
{
    protected static string $resource = ReleaseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
