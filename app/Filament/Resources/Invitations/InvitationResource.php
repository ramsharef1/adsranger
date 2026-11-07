<?php

namespace App\Filament\Resources\Invitations;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\Invitations\Pages\ListInvitations;
use App\Models\AgencyInvitation;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Pending invitations of the current agency workspace. */
class InvitationResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = AgencyInvitation::class;

    protected static ?string $tenantRelationshipName = 'invitations';

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static string|UnitEnum|null $navigationGroup = 'Team';

    protected static ?string $navigationLabel = 'Invitations';

    protected static ?string $slug = 'invitations';

    protected static ?int $navigationSort = 81;

    public static function canAccess(): bool
    {
        return TeamRoles::canManageTeam(auth()->user(), Filament::getTenant());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('accepted_at');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No pending invitations'))
            ->emptyStateDescription(__('Invite people from Team → Members.'))
            ->columns([
                TextColumn::make('email')->searchable()->weight('bold'),
                TextColumn::make('role')->label(__('Role'))->badge()->formatStateUsing(fn (?string $state) => __(TeamRoles::label($state))),
                TextColumn::make('inviter.name')->label(__('Invited by')),
                TextColumn::make('expires_at')->label(__('Expires'))->since()
                    ->color(fn (AgencyInvitation $record) => $record->expires_at->isPast() ? 'danger' : null),
            ])
            ->recordActions([
                Action::make('resend')->label(__('Resend'))->requiresConfirmation()->icon('heroicon-o-paper-airplane')
                    ->action(function (AgencyInvitation $record) {
                        AgencyInvitation::send($record->agency, $record->email, $record->role, auth()->user());
                        Notification::make()->title(__('Invitation sent again'))->success()->send();
                    }),
                Action::make('revoke')->label(__('Revoke'))->icon('heroicon-o-x-mark')->color('danger')
                    ->requiresConfirmation()
                    ->action(function (AgencyInvitation $record) {
                        $record->delete();
                        Notification::make()->title(__('Invitation revoked'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInvitations::route('/')];
    }
}
