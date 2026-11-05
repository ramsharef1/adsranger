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
use App\Support\QuotaNotice;
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

    /** Health issue count (UX-56): Arabic has its own plural forms in lang/ar.json. */
    public const ISSUES = '{0} No issues found|{1} :count issue found|[2,*] :count issues found';

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
            ->emptyStateHeading(__('No ad accounts yet'))
            ->emptyStateDescription(__('Accounts appear here after you connect a network.'))
            ->columns([
                TextColumn::make('network.name')->label(__('Network')),
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('external_id')->label(__('Account ID'))->searchable()->copyable(),
                TextColumn::make('currency'),
                TextColumn::make('client.name')->label(__('Client'))->placeholder(__('Not linked'))->sortable(),
                TextColumn::make('connection.external_user_name')->label(__('Connected as'))->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')->label(__('Tracked'))->boolean(),
                TextColumn::make('sync_status')->label(__('Sync'))->badge()
                    ->color(fn ($state) => ['ok' => 'success', 'error' => 'danger', 'running' => 'warning'][$state] ?? 'gray')
                    ->formatStateUsing(fn ($state) => __(['ok' => 'OK', 'error' => 'Error', 'running' => 'Running'][$state] ?? 'Not yet'))
                    ->tooltip(fn (AdAccount $r) => $r->sync_error),
                TextColumn::make('last_synced_at')->label(__('Data updated'))->since()->placeholder('—'),
                TextColumn::make('health')->label(__('Health'))->badge()
                    ->state(fn (AdAccount $r) => $r->health_checked_at ? count((array) $r->health) : null)
                    ->formatStateUsing(fn ($state) => (int) $state === 0 ? __('OK') : trans_choice(static::ISSUES, (int) $state))
                    ->color(fn ($state) => (int) $state === 0 ? 'success' : 'danger')->placeholder(__('Not checked'))
                    ->tooltip(fn (AdAccount $r) => collect((array) $r->health)->map(fn ($i) => $i[1] ?? '')->implode(' · ') ?: null),
            ])
            ->filters([
                SelectFilter::make('ad_network_id')->label(__('Network'))->relationship('network', 'name'),
                SelectFilter::make('client_id')->label(__('Client'))->options(fn () => Client::visibleTo(auth()->user(), Filament::getTenant())->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('track')->label(__('Track'))->icon('heroicon-o-play')
                    ->visible(fn (AdAccount $r) => $can() && ! $r->is_active)
                    ->action(function (AdAccount $r) {
                        if (! PlanLimits::canActivateAdAccount($r->agency)) {
                            QuotaNotice::make($r->agency, auth()->user(), 'ad_accounts')->send();

                            return;
                        }
                        $r->update(['is_active' => true]);
                        if (! $r->backfilled_at && $r->network && ConnectorRegistry::for($r->network)->supportsSync()) {
                            SyncService::queueBackfill($r);
                            Notification::make()->title(__('Tracking started'))->body(__('The last 13 months are being pulled in the background.'))->success()->send();
                        }
                    }),
                Action::make('client')->label(__('Client'))->icon('heroicon-o-briefcase')->color('gray')
                    ->visible($can)
                    ->schema([Select::make('client_id')->label(__('Client'))->placeholder(__('Not linked'))
                        ->options(fn () => Client::where('agency_id', Filament::getTenant()->id)->orderBy('name')->pluck('name', 'id'))
                        ->default(fn (AdAccount $r) => $r->client_id)])
                    ->action(fn (AdAccount $r, array $data) => $r->update(['client_id' => $data['client_id'] ?: null])),
                Action::make('sync')->label(__('Sync now'))->icon('heroicon-o-arrow-path')->color('gray')
                    ->visible(fn (AdAccount $r) => $can() && $r->is_active && $r->network && ConnectorRegistry::for($r->network)->supportsSync())
                    ->action(function (AdAccount $r) {
                        $tz = $r->timezone ?: (Filament::getTenant()->timezone ?: 'UTC');
                        SyncAdAccount::dispatch($r->id, now($tz)->subDays(7)->toDateString(), now($tz)->toDateString(), 'manual');
                        Notification::make()->title(__('Sync queued'))->body(__('The last 7 days are being refreshed.'))->success()->send();
                    }),
                Action::make('health')->label(__('Check health'))->icon('heroicon-o-heart')->color('gray')
                    ->visible(fn (AdAccount $r) => $can() && $r->is_active && $r->network && ConnectorRegistry::for($r->network)->supportsSync())
                    ->action(function (AdAccount $r) {
                        (new CheckAccountHealth($r->id))->handle();
                        $r->refresh();
                        Notification::make()->title(empty($r->health) ? __('No problems found') : trans_choice(static::ISSUES, count($r->health)))
                            ->body(collect((array) $r->health)->map(fn ($i) => $i[1])->implode("\n") ?: null)->{empty($r->health) ? 'success' : 'warning'}()->send();
                    }),
                Action::make('untrack')->label(__('Stop tracking'))->icon('heroicon-o-pause')->color('gray')
                    ->visible(fn (AdAccount $r) => $can() && $r->is_active)
                    ->action(fn (AdAccount $r) => $r->update(['is_active' => false])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAdAccounts::route('/')];
    }
}
