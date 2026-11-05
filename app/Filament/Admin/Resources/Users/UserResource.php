<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $recordTitleAttribute = 'email';

    protected static ?int $navigationSort = 30;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('User'))->columns(2)->schema([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(120),
                TextInput::make('email')->disabled(),
                Select::make('role')->label(__('Platform role'))->options(['member' => __('Member'), 'super_admin' => __('Super admin')])->required()
                    ->disabled(fn ($record) => $record?->id === auth()->id())
                    ->helperText(__('Super admins can open /admin and manage the whole platform. You cannot change your own role.')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('name')->label(__('Name'))->searchable()->weight('bold'),
            TextColumn::make('email')->searchable(),
            TextColumn::make('role')->badge()->color(fn (?string $state) => $state === 'super_admin' ? 'danger' : 'gray'),
            TextColumn::make('agencies_count')->counts('agencies')->label(__('Agencies')),
            TextColumn::make('last_login_at')->label(__('Last sign-in'))->since()->placeholder('—'),
            TextColumn::make('created_at')->label(__('Joined'))->since(),
        ])->filters([
            SelectFilter::make('role')->options(['member' => __('Member'), 'super_admin' => __('Super admin')]),
        ])->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'edit' => EditUser::route('/{record}/edit')];
    }
}
