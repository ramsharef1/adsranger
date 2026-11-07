<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Support\PlanLimits;
use App\Support\QuotaNotice;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    /** The plan's client limit (Solo 5, Starter 10, Pro 50, Agency 300): stop at the form, not after typing it. */
    public function mount(): void
    {
        $agency = Filament::getTenant();
        if (! PlanLimits::canAddClient($agency)) {
            QuotaNotice::make($agency, auth()->user(), 'clients')->send();
            $this->redirect(ClientResource::getUrl('index'));

            return;
        }

        parent::mount();
    }

    protected function beforeCreate(): void
    {
        $agency = Filament::getTenant();
        if (! PlanLimits::canAddClient($agency)) {
            QuotaNotice::make($agency, auth()->user(), 'clients')->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return ClientResource::getUrl('view', ['record' => $this->record]);
    }
}
