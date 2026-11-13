<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\MemberResource;
use App\Models\AgencyInvitation;
use App\Support\PlanLimits;
use App\Support\QuotaNotice;
use App\Support\TeamRoles;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')->label(__('Invite member'))->icon('heroicon-o-envelope')
                ->visible(fn () => TeamRoles::canManageTeam(auth()->user(), Filament::getTenant()))
                ->schema([
                    TextInput::make('email')->label(__('Email address'))->email()->required()->maxLength(190),
                    Select::make('role')->options(TeamRoles::invitable())->default('manager')->required()
                        ->helperText(__('Admin: everything except billing. Account manager: clients, ads and reports. Analyst: view only.')),
                ])
                ->action(function (array $data) {
                    $agency = Filament::getTenant();
                    if ($agency->members()->where('email', strtolower(trim($data['email'])))->exists()) {
                        Notification::make()->title(__('Already a member of this workspace'))->warning()->send();

                        return;
                    }
                    if (! PlanLimits::canAddSeat($agency)) {
                        QuotaNotice::make($agency, auth()->user(), 'seats')->send();

                        return;
                    }
                    AgencyInvitation::send($agency, $data['email'], $data['role'], auth()->user());
                    Notification::make()->title(__('Invitation sent'))->body(__('It is valid for 60 minutes. Ask them to check spam if it does not arrive.'))->success()->send();
                }),
            // EH-11 (D-291 / D-293): owner and admins make a link that works once within 24 hours; no email is sent.
            Action::make('inviteLink')->label(__('Copy an invite link'))->icon('heroicon-o-link')->color('gray')
                ->visible(fn () => TeamRoles::canManageTeam(auth()->user(), Filament::getTenant()))
                ->schema([
                    Select::make('role')->options(TeamRoles::invitable())->default('manager')->required()
                        ->helperText(__('Admin: everything except billing. Account manager: clients, ads and reports. Analyst: view only.')),
                ])
                ->action(function (array $data) {
                    $agency = Filament::getTenant();
                    if (! PlanLimits::canAddSeat($agency)) {
                        QuotaNotice::make($agency, auth()->user(), 'seats')->send();

                        return;
                    }
                    [, $url] = AgencyInvitation::link($agency, $data['role'], auth()->user());
                    Notification::make()->title(__('Invite link ready'))->persistent()->success()
                        ->body(new HtmlString(e(__('Send it to one person. It works once, within 24 hours, and holds a seat until then.'))
                            .'<input readonly dir="ltr" onclick="this.select()" value="'.e($url).'" style="width:100%;margin-top:8px;padding:6px;border-radius:6px;border:1px solid rgba(127,127,127,.4);background:transparent;font-size:12px">'))
                        ->send();
                }),
        ];
    }
}
