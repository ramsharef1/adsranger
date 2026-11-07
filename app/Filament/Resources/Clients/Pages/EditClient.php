<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Support\PlanLimits;
use App\Support\QuotaNotice;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label(__('Results')), DeleteAction::make()];
    }

    /** Bringing an archived client back counts against the plan's client limit. */
    protected function beforeSave(): void
    {
        $reactivating = $this->record->status === 'archived' && ($this->data['status'] ?? 'archived') !== 'archived';
        if ($reactivating && ! PlanLimits::canAddClient($this->record->agency)) {
            QuotaNotice::make($this->record->agency, auth()->user(), 'clients')->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return ClientResource::getUrl('view', ['record' => $this->record]);
    }
}
