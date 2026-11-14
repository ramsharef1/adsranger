<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\Clients\Pages\CreateClient;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Clients\Pages\ListClients;
use App\Filament\Resources\Clients\Pages\ViewClient;
use App\Models\AdAccount;
use App\Models\Agency;
use App\Models\Client;
use App\Models\DailyMetric;
use App\Support\Arabic\Phrases;
use App\Support\CallResults;
use App\Support\ClientSetupScore;
use App\Support\HealthScore;
use App\Support\PanelSearch;
use App\Support\TeamRoles;
use App\Support\TextClean;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

class ClientResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Client::class;

    protected static ?string $tenantRelationshipName = 'clients';

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'Results';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'name';

    /** PS-23 / XS-46 / FS-52 (R5): Arabic-aware search for clients and their campaigns («الريّان» = "al rayyan"). */
    public static function getGlobalSearchResults(string $search): Collection
    {
        $agency = Filament::getTenant();
        if (! $agency instanceof Agency) {
            return collect();
        }
        $hits = PanelSearch::find($agency, auth()->user(), $search);
        $out = collect();
        foreach ($hits['clients'] as $c) {
            $out->push(new GlobalSearchResult(title: $c->name, url: static::getUrl('view', ['record' => $c->id]),
                details: [__('Client') => $c->status === 'paused' ? __('Paused') : __('Active')]));
        }
        foreach ($hits['campaigns'] as $h) {
            $out->push(new GlobalSearchResult(title: (string) $h['campaign']->name, url: static::getUrl('view', ['record' => $h['client']->id]),
                details: [__('Campaign') => $h['client']->name]));
        }

        return $out;
    }

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
            Section::make(__('Client'))->columns(2)->schema([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(120),
                Select::make('status')->label(__('Status'))->options(['active' => __('Active'), 'paused' => __('Paused'), 'archived' => __('Archived')])->default('active')->required(),
                Select::make('vertical')->options(config('adsranger.verticals'))->searchable()->live(),
                Select::make('market')->label(__('Main market'))->options(config('adsranger.countries'))->searchable()->default($agency?->country),
                TextInput::make('launch_project_name')->label(__('Off-plan project name (optional)'))->maxLength(160)
                    ->visible(fn (Get $get): bool => $get('vertical') === 'real_estate' || filled($get('launch_project_name')) || filled($get('launch_date')))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? TextClean::invisible((string) $state) : null),
                DatePicker::make('launch_date')->label(__('Project launch date (optional)'))->native(false)
                    ->visible(fn (Get $get): bool => $get('vertical') === 'real_estate' || filled($get('launch_project_name')) || filled($get('launch_date')))
                    ->dehydrateStateUsing(fn ($state) => filled($state) ? TextClean::western((string) $state) : null),
                // XS-16 (R5): one dialect per client; empty = from the market.
                Select::make('dialect')->label(__('Dialect'))->options(Phrases::dialectLabels())->placeholder(__('From the main market'))
                    ->helperText(__('Used for phrases, ad line variants and AI drafts for this client.')),
                Select::make('currency')->options($cur)->default($agency?->currency ?? 'USD')->required()
                    ->helperText(__('Results and spend are shown in this currency. Accounts in other currencies are converted with daily rates.')),
                Toggle::make('hide_spend')->label(__('Hide spend from this client'))->helperText(__('For client links and reports.'))->inline(false),
            ]),
            Section::make(__('Targets'))->description(__('Used for pacing, alerts and the health score. Leave empty if unknown.'))->columns(4)->schema([
                TextInput::make('monthly_budget')->label(__('Monthly budget'))->numeric()->minValue(0),
                TextInput::make('target_cpr')->label(__('Target cost per result'))->numeric()->minValue(0),
                TextInput::make('target_results')->label(__('Target results / month'))->numeric()->integer()->minValue(0),
                TextInput::make('target_roas')->label(__('Target ROAS'))->numeric()->minValue(0)->helperText(__('e.g. 3 = 3× revenue')),
                Select::make('call_result')->label(__('Count calls as'))->options(fn () => CallResults::options())
                    ->default('placed')->selectablePlaceholder(false)->columnSpan(2)
                    ->helperText(__('Meta call ads report calls placed, confirmed and 20 seconds or longer. The one you pick is the result and the cost per result of call campaigns. Other networks report one call count.')),
            ]),
            Section::make(__('Accounts and access'))->columns(2)->schema([
                Select::make('ad_account_ids')->label(__('Ad accounts'))->multiple()->searchable()
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
                    ->helperText(__('An ad account belongs to one client. Its history moves with it.')),
                Select::make('users')->label(__('Team access'))->multiple()->relationship('users', 'name',
                    fn (Builder $q) => $q->whereIn('users.id', $agency?->members()->pluck('users.id') ?? []))
                    ->visible(fn () => TeamRoles::canManageTeam(auth()->user(), $agency))
                    ->helperText(__('Leave empty so everyone in the workspace sees this client. Owners and admins always do.')),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->emptyStateHeading(__('No clients yet'))
            ->emptyStateDescription(__('Add a client, then link its ad accounts to see goal-based results.'))
            ->recordUrl(fn (Client $r) => static::getUrl('view', ['record' => $r]))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->weight('bold'),
                TextColumn::make('vertical')->formatStateUsing(fn ($state) => config('adsranger.verticals')[$state] ?? $state)->toggleable(),
                TextColumn::make('market')->toggleable(),
                TextColumn::make('currency'),
                TextColumn::make('health_score')->label(__('Health'))->badge()->sortable()
                    ->formatStateUsing(fn ($state) => $state === null ? '–' : $state.' · '.__(HealthScore::band((int) $state)[0]))
                    ->color(fn ($state) => HealthScore::band($state === null ? null : (int) $state)[1])
                    ->tooltip(fn ($record) => collect((array) $record->health_parts)->map(fn ($x, $k) => ucfirst($k).': '.$x['points'].'/'.$x['max'].' · '.$x['note'])->implode("\n") ?: __('Scores run nightly.')),
                TextColumn::make('ad_accounts_count')->counts('adAccounts')->label(__('Accounts')),
                // EH-30 (D-297): setup score with the missing steps on hover.
                TextColumn::make('setup_score')->label(__('Setup'))->badge()->toggleable()
                    ->state(fn (Client $record) => ClientSetupScore::for($record)['score'].'%')
                    ->color(fn (Client $record) => ClientSetupScore::for($record)['score'] >= 80 ? 'success' : (ClientSetupScore::for($record)['score'] >= 40 ? 'warning' : 'gray'))
                    ->tooltip(fn (Client $record) => ($m = ClientSetupScore::for($record)['missing']) ? __('Missing: :list', ['list' => implode(app()->getLocale() === 'ar' ? '، ' : ', ', $m)]) : null),
                TextColumn::make('monthly_budget')->label(__('Budget / month'))->numeric(2)->toggleable(),
                TextColumn::make('target_cpr')->label(__('Target CPR'))->numeric(2)->toggleable(),
                TextColumn::make('status')->label(__('Status'))->badge()->formatStateUsing(fn ($state) => __($state))->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([SelectFilter::make('status')->options(['active' => __('Active'), 'paused' => __('Paused'), 'archived' => __('Archived')])->default('active')])
            ->recordActions([ViewAction::make()->label(__('Results')), EditAction::make()]);
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
