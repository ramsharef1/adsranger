<?php

namespace App\Filament\Admin\Resources\Releases\Pages;

use App\Filament\Admin\Resources\Releases\ReleaseResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\EditRecord;

class EditRelease extends EditRecord
{
    protected static string $resource = ReleaseResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ReleaseResource::packItems($data);
    }

    protected function afterSave(): void
    {
        AuditLog::record('release.updated', $this->record, ['version' => $this->record->version]);
    }
}
