<?php

namespace App\Filament\Admin\Resources\AdNetworks\Pages;

use App\Connectors\ConnectorRegistry;
use App\Filament\Admin\Resources\AdNetworks\AdNetworkResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAdNetwork extends EditRecord
{
    protected static string $resource = AdNetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')->label(__('Test keys'))->icon('heroicon-o-beaker')
                ->action(function () {
                    $n = $this->record->fresh();
                    $lines = [];
                    $best = null;
                    foreach (['live', 'sandbox'] as $env) {
                        if (empty(array_filter((array) ($n->{"credentials_{$env}"} ?? []))) && ! isset($n::REUSES[$n->key])) {
                            continue;
                        }
                        $probe = clone $n;
                        $probe->environment = $env;
                        [$ok, $msg] = ConnectorRegistry::for($probe)->test();
                        $lines[] = ucfirst($env).': '.($ok === true ? 'OK' : ($ok === false ? 'FAILED' : 'n/a')).' — '.$msg;
                        $best = $best === false ? false : ($ok === false ? false : ($ok ?? $best));
                    }
                    if (! $lines) {
                        Notification::make()->title(__('No keys saved yet'))->warning()->send();

                        return;
                    }
                    $n->forceFill(['last_tested_at' => now(), 'last_test_result' => mb_substr(implode(' | ', $lines), 0, 900)])->saveQuietly();
                    Notification::make()->title($best === false ? __('Key test failed') : ($best ? __('Keys work') : __('Keys saved (cannot be fully tested)')))
                        ->body(implode("\n", $lines))->{$best === false ? 'danger' : ($best ? 'success' : 'warning')}()->persistent()->send();
                }),
        ];
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
        return __('Keys saved (encrypted)');
    }
}
