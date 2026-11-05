<?php

namespace App\Filament\Admin\Resources\Agencies;

use App\Alerts\AlertEngine;
use App\Filament\Admin\Resources\Agencies\Pages\EditAgency;
use App\Filament\Admin\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Models\Agency;
use App\Models\Plan;
use App\Support\DemoData;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
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
use UnitEnum;

class AgencyResource extends Resource
{
    use TranslatesResourceLabels;

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
            Section::make(__('Agency'))->columns(2)->schema([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(120),
                TextInput::make('slug')->disabled()->helperText(__('Used in the agency\'s /app URL.')),
                Select::make('country')->label(__('Country'))->options(config('adsranger.countries'))->searchable(),
                Select::make('currency')->options(collect(config('adsranger.currencies'))->mapWithKeys(fn ($c) => [$c => $c])),
            ])->columnSpan(2),
            Section::make(__('Plan and status'))->columns(2)->schema([
                Select::make('plan')->options(fn () => Plan::orderBy('sort')->pluck('name', 'key'))->required()
                    ->helperText(__('Controls which features and networks the agency gets.')),
                Select::make('status')->options(config('adsranger.agency_statuses'))->required()
                    ->helperText(__('Suspended agencies cannot sign in to their workspace.')),
                DateTimePicker::make('trial_ends_at')->label(__('Trial ends'))->seconds(false),
            ])->columnSpan(2),
            Section::make(__('Network overrides'))->description(__('Layer 3 of the access rules. "Always allow" turns a network on for this agency even when it is off globally or not in its plan (good for beta tests). "Block" hides it, and wins over everything.'))->columns(2)->schema([
                CheckboxList::make('allowedNetworks')->label(__('Always allow'))->relationship('allowedNetworks', 'name'),
                CheckboxList::make('blockedNetworks')->label(__('Block'))->relationship('blockedNetworks', 'name'),
            ])->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->weight('bold'),
                TextColumn::make('owner.email')->label(__('Owner'))->searchable(),
                TextColumn::make('members_count')->counts('members')->label(__('Members')),
                TextColumn::make('plan')->badge()->formatStateUsing(fn (?string $state) => config('adsranger.plans')[$state] ?? $state),
                TextColumn::make('status')->label(__('Status'))->badge()->color(fn (?string $state) => match ($state) {
                    'active' => 'success',
                    'trial' => 'warning',
                    'suspended' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('country'),
                TextColumn::make('created_at')->label(__('Created'))->since(),
            ])
            ->filters([
                SelectFilter::make('plan')->options(config('adsranger.plans')),
                SelectFilter::make('status')->options(config('adsranger.agency_statuses')),
            ])
            ->recordActions([EditAction::make(), ActionGroup::make([
                Action::make('demo')->label(__('Load demo data'))->icon('heroicon-o-beaker')->requiresConfirmation()
                    ->modalDescription(__('Adds 3 sample clients with 90 days of Meta-style results and runs the alert rules. Remove it any time.'))
                    ->action(function (Agency $r) {
                        $rows = array_sum(DemoData::seed($r));
                        $s = AlertEngine::run($r, now($r->timezone ?: 'UTC')->subDay()->toDateString());
                        Notification::make()->title(__('Demo data loaded: :rows rows, :alerts alerts', ['rows' => $rows, 'alerts' => $s['opened']]))->success()->send();
                    }),
                Action::make('undemo')->label(__('Remove demo data'))->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->action(function (Agency $r) {
                        DemoData::remove($r);
                        Notification::make()->title(__('Demo data removed'))->success()->send();
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
