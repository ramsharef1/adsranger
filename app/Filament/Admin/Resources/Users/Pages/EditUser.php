<?php

namespace App\Filament\Admin\Resources\Users\Pages;

use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->id === auth()->id()) {
            unset($data['role']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->record->wasChanged('role')) {
            AuditLog::record('user.role_changed', $this->record, ['role' => $this->record->role]);
        }
    }
}
