<?php

namespace App\Filament\Admin\Resources\AdNetworks\Pages;

use App\Filament\Admin\Resources\AdNetworks\AdNetworkResource;
use Filament\Resources\Pages\EditRecord;

class EditAdNetwork extends EditRecord
{
    protected static string $resource = AdNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /** Secrets are never sent to the browser: blank them before the form fills. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['live', 'sandbox'] as $env) {
            $column = "credentials_{$env}";
            $data[$column] = (array) ($data[$column] ?? []);
            foreach ($this->record->secretKeys() as $name) {
                $data[$column][$name] = null;
            }
        }

        return $data;
    }

    /** An empty secret field means "keep the saved value". */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $secrets = $this->record->secretKeys();

        foreach (['live', 'sandbox'] as $env) {
            $column = "credentials_{$env}";
            $old = (array) ($this->record->{$column} ?? []);
            $new = array_map(fn ($v) => is_string($v) ? trim($v) : $v, (array) ($data[$column] ?? []));

            foreach ($secrets as $name) {
                if (blank($new[$name] ?? null)) {
                    $new[$name] = $old[$name] ?? null;
                }
            }

            $data[$column] = array_filter($new, fn ($v) => filled($v));
        }

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Keys saved (encrypted)';
    }
}
