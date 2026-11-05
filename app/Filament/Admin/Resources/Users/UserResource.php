<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Mail\TwoStepResetMail;
use App\Models\AuditLog;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
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
        ])->recordActions([EditAction::make(), static::resetTwoStepAction()]);
    }

    /**
     * Super admin: "Reset two-step verification" for a user who lost their authenticator and recovery codes.
     * EX-32 confirm naming what changes; never for your own account; audit `auth.2fa_reset_by_admin`; the user is
     * told by email. An agency that requires two-step sends them to set it up again at the next sign-in.
     */
    public static function resetTwoStepAction(): Action
    {
        return Action::make('resetTwoStep')->label(__('Reset two-step verification'))->icon('heroicon-o-shield-exclamation')->color('danger')
            ->visible(fn (User $record) => $record->hasTwoStep() && $record->id !== auth()->id())
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => __('Reset two-step verification for :email?', ['email' => $record->email]))
            ->modalDescription(__('This turns off two-step verification and deletes the recovery codes. They sign in with their password only until they set it up again; a workspace that requires it asks them at the next sign-in. We email them about the reset. Check who is asking before you do this.'))
            ->modalSubmitActionLabel(__('Reset two-step verification'))
            ->action(function (User $record) {
                abort_if($record->id === auth()->id() || auth()->user()?->role !== 'super_admin', 403);
                $record->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
                AuditLog::record('auth.2fa_reset_by_admin', $record, ['by' => auth()->id()]);
                $lang = $record->agencies()->first()?->language === 'en' ? 'en' : 'ar';
                Mail::to($record->email)->queue(new TwoStepResetMail($record, $lang, url($record->isSuperAdmin() ? '/admin/login' : '/app/login')));
                Notification::make()->title(__('Two-step verification reset for :email', ['email' => $record->email]))->success()->send();
            });
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'edit' => EditUser::route('/{record}/edit')];
    }
}
