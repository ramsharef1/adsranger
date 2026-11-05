<?php

namespace App\Filament\Resources\Members;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Members of the current agency workspace. */
class MemberResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = User::class;

    protected static ?string $tenantRelationshipName = 'members';

    protected static ?string $tenantOwnershipRelationshipName = 'agencies';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Team';

    protected static ?string $navigationLabel = 'Members';

    protected static ?string $modelLabel = 'member';

    protected static ?string $slug = 'members';

    protected static ?int $navigationSort = 80;

    public static function canCreate(): bool
    {
        return false;
    }

    protected static function manageable(User $record): bool
    {
        $agency = Filament::getTenant();

        return TeamRoles::canManageTeam(auth()->user(), $agency)
            && $record->id !== auth()->id()
            && $record->roleIn($agency) !== 'owner';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->weight('bold'),
                TextColumn::make('email')->searchable(),
                TextColumn::make('team_role')->label(__('Role'))->badge()
                    ->state(fn (User $record) => __(TeamRoles::label($record->roleIn(Filament::getTenant()))))
                    ->color(fn (User $record) => match ($record->roleIn(Filament::getTenant())) {
                        'owner' => 'success',
                        'admin' => 'info',
                        'analyst' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->recordActions([
                Action::make('changeRole')->label(__('Change role'))->icon('heroicon-o-arrows-right-left')
                    ->visible(fn (User $record) => static::manageable($record))
                    ->schema([
                        Select::make('role')->options(TeamRoles::invitable())->required()
                            ->default(fn (User $record) => $record->roleIn(Filament::getTenant())),
                    ])
                    ->action(function (User $record, array $data) {
                        $agency = Filament::getTenant();
                        $agency->members()->updateExistingPivot($record->id, ['role' => $data['role']]);
                        AuditLog::record('team.role_changed', $agency, ['user_id' => $record->id, 'role' => $data['role']]);
                        Notification::make()->title(__('Role updated'))->success()->send();
                    }),
                Action::make('remove')->label(__('Remove'))->icon('heroicon-o-user-minus')->color('danger')
                    ->visible(fn (User $record) => static::manageable($record))
                    ->requiresConfirmation()
                    ->modalDescription(__('They lose access to this workspace straight away. Their account itself is not deleted.'))
                    ->action(function (User $record) {
                        $agency = Filament::getTenant();
                        $agency->members()->detach($record->id);
                        AuditLog::record('team.removed', $agency, ['user_id' => $record->id]);
                        Notification::make()->title(__('Member removed'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMembers::route('/')];
    }
}
