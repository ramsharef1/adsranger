<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\AgencyInvitation;
use App\Support\PlanLimits;
use App\Support\TeamRoles;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')->label('Invite member')->icon('heroicon-o-envelope')
                ->visible(fn () => TeamRoles::canManageTeam(auth()->user(), Filament::getTenant()))
                ->schema([
                    TextInput::make('email')->label('Email address')->email()->required()->maxLength(190),
                    Select::make('role')->options(TeamRoles::invitable())->default('manager')->required()
                        ->helperText('Admin: everything except billing. Account manager: clients, ads and reports. Analyst: view only.'),
                ])
                ->action(function (array $data) {
                    $agency = Filament::getTenant();
                    if ($agency->members()->where('email', strtolower(trim($data['email'])))->exists()) {
                        Notification::make()->title('Already a member of this workspace')->warning()->send();

                        return;
                    }
                    if (! PlanLimits::canAddSeat($agency)) {
                        Notification::make()->title('No free seats')->body('Your plan includes '.PlanLimits::seatsLimit($agency).' seats (members + pending invites). Revoke an invite, remove a member, or upgrade.')->warning()->send();

                        return;
                    }
                    AgencyInvitation::send($agency, $data['email'], $data['role'], auth()->user());
                    Notification::make()->title('Invitation sent')->body('It is valid for 7 days. Ask them to check spam if it does not arrive.')->success()->send();
                }),
        ];
    }
}
