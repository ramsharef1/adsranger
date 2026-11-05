<?php

namespace App\Filament\Resources\AdAccounts;

use App\Connectors\ConnectorRegistry;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\AdAccounts\Pages\ListAdAccounts;
use App\Jobs\CheckAccountHealth;
use App\Jobs\SyncAdAccount;
use App\Models\AdAccount;
use App\Models\Client;
use App\Support\PlanLimits;
use App\Support\TeamRoles;
use App\Sync\SyncService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AdAccountResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = AdAccount::class;

    protected static ?string $tenantRelationshipName = 'adAccounts';

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Ad accounts';

    protected static ?string $slug = 'ad-accounts';

    protected static ?int $navigationSort = 22;

    public static function canCreate(): bool
    {
        return false;
    }

    /** Owners and admins see every account; others see unlinked ones and those of clients they can see. */
    public static function getEloquentQuery(): Builder
    {
        $a = Filament::getTenant();
        $q = parent::getEloquentQuery();
        if (TeamRoles::canManageTeam(auth()->user(), $a)) {
            return $q;
        }
        $ids = Client::visibleTo(auth()->user(), $a)->pluck('id');

        return $q->where(fn ($w) => $w->whereNull('client_id')->orWhereIn('client_id', $ids));
    }

    public static function table(Table $table): Table
    {
        $can = fn () => TeamRoles::canManageData(auth()->user(), Filament::getTenant());

        return $table
            ->defaultSort('name')
            ->emptyStateHeading('No ad accounts yet')
            ->emptyStateDescription('Accounts appear here after you connect a network.')
            ->columns([
                TextColumn::make('network.name')->label('Network'),
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('external_id')->label('Account ID')->searchable()->copyable(),
                TextColumn::make('currency'),
                TextColumn::make('client.name')->label('Client')->placeholder('Not linked')->sortable(),
                TextColumn::make('connection.external_user_name')->label('Connected as')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label('Tracked')->boolean(),
                TextColumn::make('sync_status')->label('Sync')->badge()
                    ->color(fn ($state) => ['ok' => 'success', 'error' => 'danger', 'running' => 'warning'][$state] ?? 'gray')
                    ->formatStateUsing(fn ($state) => ['ok' => 'OK', 'error' => 'Error', 'running' => 'Running'][$state] ?? 'Not yet')
                    ->tooltip(fn (AdAccount $r) => $r->sync_error),
                TextColumn::make('last_synced_at')->label('Data updated')->since()->placeholder('—'),
                TextColumn::make('health')->label('Health')->badge()
                    ->state(fn (AdAccount $r) => $r->health_checked_at ? (empty($r->health) ? 'OK' : count($r->health).' issue(s)') : null)
                    ->color(fn ($state) => $state === 'OK' ? 'success' : 'danger')->placeholder('Not checked')
                    ->tooltip(fn (AdAccount $r) => collect((array) $r->health)->map(fn ($i) => $i[1] ?? '')->implode(' · ') ?: null),
            ])
            ->filters([
                SelectFilter::make('ad_network_id')->label('Network')->relationship('network', 'name'),
                SelectFilter::make('client_id')->label('Client')->options(fn () => Client::visibleTo(auth()->user(), Filament::getTenant())->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('track')->label('Track')->icon('heroicon-o-play')
                    ->visible(fn (AdAccount $r) => $can() && ! $r->is_active)
                    ->action(function (AdAccount $r) {
                        if (! PlanLimits::canActivateAdAccount($r->agency)) {
                            Notification::make()->title('Plan limit reached')->body('Your plan tracks up to '.PlanLimits::adAccountsLimit($r->agency).' ad accounts. Stop tracking one, or upgrade.')->warning()->send();

                            return;
                        }
                        $r->update(['is_active' => true]);
                        if (! $r->backfilled_at && $r->network && ConnectorRegistry::for($r->network)->supportsSync()) {
                            SyncService::queueBackfill($r);
                            Notification::make()->title('Tracking started')->body('The last 13 months are being pulled in the background.')->success()->send();
                        }
                    }),
                Action::make('client')->label('Client')->icon('heroicon-o-briefcase')->color('gray')
                    ->visible($can)
                    ->schema([Select::make('client_id')->label('Client')->placeholder('Not linked')
                        ->options(fn () => Client::where('agency_id', Filament::getTenant()->id)->orderBy('name')->pluck('name', 'id'))
                        ->default(fn (AdAccount $r) => $r->client_id)])
                    ->action(fn (AdAccount $r, array $data) => $r->update(['client_id' => $data['client_id'] ?: null])),
                Action::make('sync')->label('Sync now')->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (AdAccount $r) => $can() && $r->is_active && $r->network && ConnectorRegistry::for($r->network)->supportsSync())
                    ->action(function (AdAccount $r) {
                        $tz = $r->timezone ?: (Filament::getTenant()->timezone ?: 'UTC');
                        SyncAdAccount::dispatch($r->id, now($tz)->subDays(7)->toDateString(), now($tz)->toDateString(), 'manual');
                        Notification::make()->title('Sync queued')->body('The last 7 days are being refreshed.')->success()->send();
                    }),
                Action::make('health')->label('Check health')->icon('heroicon-o-heart')->color('gray')
                    ->visible(fn (AdAccount $r) => $can() && $r->is_active && $r->network && ConnectorRegistry::for($r->network)->supportsSync())
                    ->action(function (AdAccount $r) {
                        (new CheckAccountHealth($r->id))->handle();
                        $r->refresh();
                        Notification::make()->title(empty($r->health) ? 'No problems found' : count($r->health).' issue(s) found')
                            ->body(collect((array) $r->health)->map(fn ($i) => $i[1])->implode("\n") ?: null)->{empty($r->health) ? 'success' : 'warning'}()->send();
                    }),
                Action::make('untrack')->label('Stop tracking')->icon('heroicon-o-pause')->color('gray')
                    ->visible(fn (AdAccount $r) => $can() && $r->is_active)
                    ->action(fn (AdAccount $r) => $r->update(['is_active' => false])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAdAccounts::route('/')];
    }
}
