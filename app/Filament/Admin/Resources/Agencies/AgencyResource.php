<?php

namespace App\Filament\Admin\Resources\Agencies;

use App\Filament\Admin\Resources\Agencies\Pages\EditAgency;
use App\Filament\Admin\Resources\Agencies\Pages\ListAgencies;
use App\Models\Agency;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use App\Support\DemoData;
use App\Alerts\AlertEngine;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
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

class AgencyResource extends Resource
{
    protected static ?string $model = Agency::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Agencies';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

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
        return $schema->columns(2)->components([
            Section::make('Agency')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('slug')->disabled()->helperText('Used in the agency\'s /app URL.'),
                Select::make('country')->options(config('adsranger.countries'))->searchable(),
                Select::make('currency')->options(collect(config('adsranger.currencies'))->mapWithKeys(fn ($c) => [$c => $c])),
            ])->columnSpan(2),
            Section::make('Plan and status')->columns(2)->schema([
                Select::make('plan')->options(fn () => \App\Models\Plan::orderBy('sort')->pluck('name', 'key'))->required()
                    ->helperText('Controls which features and networks the agency gets.'),
                Select::make('status')->options(config('adsranger.agency_statuses'))->required()
                    ->helperText('Suspended agencies cannot sign in to their workspace.'),
                DateTimePicker::make('trial_ends_at')->label('Trial ends')->seconds(false),
            ])->columnSpan(2),
            Section::make('Network overrides')->description('Layer 3 of the access rules. "Always allow" turns a network on for this agency even when it is off globally or not in its plan (good for beta tests). "Block" hides it, and wins over everything.')->columns(2)->schema([
                CheckboxList::make('allowedNetworks')->label('Always allow')->relationship('allowedNetworks', 'name'),
                CheckboxList::make('blockedNetworks')->label('Block')->relationship('blockedNetworks', 'name'),
            ])->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('owner.email')->label('Owner')->searchable(),
                TextColumn::make('members_count')->counts('members')->label('Members'),
                TextColumn::make('plan')->badge()->formatStateUsing(fn (?string $state) => config('adsranger.plans')[$state] ?? $state),
                TextColumn::make('status')->badge()->color(fn (?string $state) => match ($state) {
                    'active' => 'success',
                    'trial' => 'warning',
                    'suspended' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('country'),
                TextColumn::make('created_at')->label('Created')->since(),
            ])
            ->filters([
                SelectFilter::make('plan')->options(config('adsranger.plans')),
                SelectFilter::make('status')->options(config('adsranger.agency_statuses')),
            ])
            ->recordActions([EditAction::make(), ActionGroup::make([
                Action::make('demo')->label('Load demo data')->icon('heroicon-o-beaker')->requiresConfirmation()
                    ->modalDescription('Adds 3 sample clients with 90 days of Meta-style results and runs the alert rules. Remove it any time.')
                    ->action(function (Agency $r) {
                        $rows = array_sum(DemoData::seed($r));
                        $s = AlertEngine::run($r, now($r->timezone ?: 'UTC')->subDay()->toDateString());
                        Notification::make()->title("Demo data loaded: $rows rows, {$s['opened']} alerts")->success()->send();
                    }),
                Action::make('undemo')->label('Remove demo data')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->action(function (Agency $r) {
                        DemoData::remove($r);
                        Notification::make()->title('Demo data removed')->success()->send();
                    }),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgencies::route('/'),
            'edit' => EditAgency::route('/{record}/edit'),
        ];
    }
}
