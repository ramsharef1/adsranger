<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Support\PlanLimits;
use App\Support\QuotaNotice;
use App\Support\TwoPerson;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        $agency = $this->record->agency;
        $user = auth()->user();
        $two = TwoPerson::needed($agency);
        $approved = $two && TwoPerson::approved($agency, 'delete_client', $this->record->id, $user);
        $pending = $two && ! $approved && TwoPerson::pending($agency, 'delete_client', $this->record->id, $user);

        return [
            ViewAction::make()->label(__('Results')),
            // DS-35 (D-305): with 2+ owners / admins, deleting a client waits for a second owner or admin.
            Action::make('askDelete')->label($pending ? __('Waiting for approval to delete') : __('Ask to delete'))->icon('heroicon-o-trash')->color('danger')
                ->visible(fn () => $two && ! $approved && ClientResource::canDelete($this->record))->disabled($pending)
                ->requiresConfirmation()
                ->modalDescription(__('Another owner or admin must approve before this client can be deleted. The request expires in 48 hours.'))
                ->action(function () use ($agency, $user): void {
                    TwoPerson::request($agency, 'delete_client', $this->record->id, $this->record->name, $user);
                    Notification::make()->title(__('Request sent. Another owner or admin can approve it on their Desk.'))->success()->send();
                }),
            DeleteAction::make()
                ->visible(fn () => ! $two || $approved)
                // One-person agency: type the client's name to confirm.
                ->schema($two ? [] : [TextInput::make('confirm')->label(__('Type the client name to confirm'))->required()->in([$this->record->name])
                    ->validationMessages(['in' => __('The name does not match.')])])
                ->before(function (DeleteAction $action) use ($agency, $user): void {
                    if (! TwoPerson::consume($agency, 'delete_client', $this->record->id, $user)) {
                        Notification::make()->title(__('This needs approval from another owner or admin first.'))->danger()->send();
                        $action->halt();
                    }
                }),
        ];
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
