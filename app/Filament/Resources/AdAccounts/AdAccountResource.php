<?php

namespace App\Filament\Resources\AdAccounts;

use App\Filament\Resources\AdAccounts\Pages\ListAdAccounts;
use App\Models\AdAccount;
use App\Support\PlanLimits;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AdAccountResource extends Resource
{
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
                TextColumn::make('connection.external_user_name')->label('Connected as'),
                IconColumn::make('is_active')->label('Tracked')->boolean(),
            ])
            ->filters([
                SelectFilter::make('ad_network_id')->label('Network')->relationship('network', 'name'),
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
