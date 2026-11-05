<?php

namespace App\Filament\Admin\Resources\Releases\Pages;

use App\Filament\Admin\Resources\Releases\ReleaseResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\CreateRecord;

class CreateRelease extends CreateRecord
{
    protected static string $resource = ReleaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ReleaseResource::packItems($data);
    }

    protected function afterCreate(): void
    {
        AuditLog::record('release.created', $this->record, ['version' => $this->record->version]);
    }
}
