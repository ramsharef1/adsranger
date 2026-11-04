<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\AdAccount;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $tenantRelationshipName = 'clients';

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'Results';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canManage(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit($record): bool
    {
        return static::canManage();
    }

    public static function canDelete($record): bool
    {
        return TeamRoles::canManageTeam(auth()->user(), Filament::getTenant());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user(), Filament::getTenant());
    }

    public static function form(Schema $schema): Schema
    {
        $agency = Filament::getTenant();
        $cur = collect(config('adsranger.currencies'))->mapWithKeys(fn ($c) => [$c => $c])->all();

        return $schema->components([
            Section::make('Client')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                Select::make('status')->options(['active' => 'Active', 'paused' => 'Paused', 'archived' => 'Archived'])->default('active')->required(),
                Select::make('vertical')->options(config('adsranger.verticals'))->searchable(),
                Select::make('market')->label('Main market')->options(config('adsranger.countries'))->searchable()->default($agency?->country),
                Select::make('currency')->options($cur)->default($agency?->currency ?? 'USD')->required()
                    ->helperText('Results and spend are shown in this currency. Accounts in other currencies are converted with daily rates.'),
                Toggle::make('hide_spend')->label('Hide spend from this client')->helperText('For client links and reports.')->inline(false),
            ]),
            Section::make('Targets')->description('Used for pacing, alerts and (from Wave 3) the health score. Leave empty if unknown.')->columns(4)->schema([
                TextInput::make('monthly_budget')->label('Monthly budget')->numeric()->minValue(0),
                TextInput::make('target_cpr')->label('Target cost per result')->numeric()->minValue(0),
                TextInput::make('target_results')->label('Target results / month')->numeric()->integer()->minValue(0),
                TextInput::make('target_roas')->label('Target ROAS')->numeric()->minValue(0)->helperText('e.g. 3 = 3× revenue'),
            ]),
            Section::make('Accounts and access')->columns(2)->schema([
                Select::make('ad_account_ids')->label('Ad accounts')->multiple()->searchable()
                    ->options(fn () => AdAccount::with('network')->where('agency_id', $agency?->id)->get()
                        ->mapWithKeys(fn ($a) => [$a->id => ($a->network?->name ?? '?').' · '.($a->name ?: $a->external_id)])->all())
                    ->afterStateHydrated(fn (Select $c, ?Client $record) => $c->state($record ? $record->adAccounts()->pluck('id')->all() : []))
                    ->dehydrated(false)
                    ->saveRelationshipsUsing(function (Client $record, $state) use ($agency) {
                        $ids = AdAccount::where('agency_id', $agency->id)->whereIn('id', (array) $state)->pluck('id');
                        $removed = $record->adAccounts()->whereNotIn('id', $ids)->pluck('id');
                        AdAccount::whereIn('id', $removed)->update(['client_id' => null]);
                        DailyMetric::whereIn('ad_account_id', $removed)->update(['client_id' => null]);
                        AdAccount::whereIn('id', $ids)->update(['client_id' => $record->id]);
                        DailyMetric::whereIn('ad_account_id', $ids)->update(['client_id' => $record->id]);
                    })
                    ->helperText('An ad account belongs to one client. Its history moves with it.'),
                Select::make('users')->label('Team access')->multiple()->relationship('users', 'name',
                    fn (Builder $q) => $q->whereIn('users.id', $agency?->members()->pluck('users.id') ?? []))
                    ->visible(fn () => TeamRoles::canManageTeam(auth()->user(), $agency))
                    ->helperText('Leave empty so everyone in the workspace sees this client. Owners and admins always do.'),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->emptyStateHeading('No clients yet')
            ->emptyStateDescription('Add a client, then link its ad accounts to see goal-based results.')
            ->recordUrl(fn (Client $r) => static::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('vertical')->formatStateUsing(fn ($state) => config('adsranger.verticals')[$state] ?? $state)->toggleable(),
                TextColumn::make('market')->toggleable(),
                TextColumn::make('currency'),
                TextColumn::make('health_score')->label('Health')->badge()->sortable()
                    ->formatStateUsing(fn ($state) => $state === null ? '–' : $state.' · '.\App\Support\HealthScore::band((int) $state)[0])
                    ->color(fn ($state) => \App\Support\HealthScore::band($state === null ? null : (int) $state)[1])
                    ->tooltip(fn ($record) => collect((array) $record->health_parts)->map(fn ($x, $k) => ucfirst($k).': '.$x['points'].'/'.$x['max'].' · '.$x['note'])->implode("\n") ?: 'Scores run nightly.'),
                TextColumn::make('ad_accounts_count')->counts('adAccounts')->label('Accounts'),
                TextColumn::make('monthly_budget')->label('Budget / month')->numeric(2)->toggleable(),
                TextColumn::make('target_cpr')->label('Target CPR')->numeric(2)->toggleable(),
                TextColumn::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([SelectFilter::make('status')->options(['active' => 'Active', 'paused' => 'Paused', 'archived' => 'Archived'])->default('active')])
            ->recordActions([ViewAction::make()->label('Results'), EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'view' => ViewClient::route('/{record}'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
