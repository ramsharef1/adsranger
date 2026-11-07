<?php

namespace App\Filament\Resources\Connections;

use App\Connectors\ConnectorRegistry;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\Connections\Pages\ListConnections;
use App\Models\AdAccount;
use App\Models\Connection;
use App\Support\PlanLimits;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class ConnectionResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Connection::class;

    protected static ?string $tenantRelationshipName = 'connections';

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Connections';

    protected static ?string $slug = 'connections';

    protected static ?int $navigationSort = 21;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $can = fn () => TeamRoles::canManageData(auth()->user(), Filament::getTenant());

        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No connections yet'))
            ->emptyStateDescription(__('Go to Data → Networks and click Connect.'))
            ->columns([
                TextColumn::make('network.name')->label(__('Network'))->weight('bold'),
                TextColumn::make('external_user_name')->label(__('Connected as')),
                TextColumn::make('status')->label(__('Status'))->badge()->formatStateUsing(fn (string $state) => __($state))->color(fn (string $state) => match ($state) {
                    'active' => 'success', 'error' => 'danger', default => 'gray',
                }),
                TextColumn::make('ad_accounts_count')->counts('adAccounts')->label(__('Accounts')),
                TextColumn::make('last_synced_at')->label(__('Last sync'))->since(),
                TextColumn::make('last_error')->label(__('Last error'))->limit(60)->color('danger'),
            ])
            ->recordActions([
                Action::make('refresh')->label(__('Refresh accounts'))->requiresConfirmation()->icon('heroicon-o-arrow-path')
                    ->visible(fn (Connection $r) => $can() && $r->status !== 'disconnected')
                    ->action(function (Connection $r) {
                        try {
                            $n = 0;
                            foreach (ConnectorRegistry::for($r->network)->accounts($r) as $a) {
                                $acc = AdAccount::firstOrNew(['connection_id' => $r->id, 'external_id' => $a['id']]);
                                $acc->fill(['agency_id' => $r->agency_id, 'ad_network_id' => $r->ad_network_id, 'name' => $a['name'], 'currency' => $a['currency'], 'timezone' => $a['timezone'], 'status' => $a['status']]);
                                if (! $acc->exists) {
                                    $acc->is_active = PlanLimits::canActivateAdAccount($r->agency);
                                }
                                $acc->save();
                                $n++;
                            }
                            $r->update(['status' => 'active', 'last_synced_at' => now(), 'last_error' => null]);
                            Notification::make()->title(trans_choice('{0} No accounts found.|{1} :count account found.|[2,*] :count accounts found.', $n))->success()->send();
                        } catch (\Throwable $e) {
                            $r->update(['status' => 'error', 'last_error' => Str::limit($e->getMessage(), 500)]);
                            Notification::make()->title(__('Refresh failed'))->body(Str::limit($e->getMessage(), 200))->danger()->send();
                        }
                    }),
                Action::make('disconnect')->label(__('Disconnect'))->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (Connection $r) => $can() && $r->status !== 'disconnected')
                    ->requiresConfirmation()
                    ->modalDescription(__('AdsRanger deletes the access token now and stops syncing. You can also remove AdsRanger in the network\'s own settings.'))
                    ->action(function (Connection $r) {
                        $r->disconnect();
                        Notification::make()->title(__('Disconnected'))->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListConnections::route('/')];
    }
}
