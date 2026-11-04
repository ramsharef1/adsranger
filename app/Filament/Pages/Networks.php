<?php

namespace App\Filament\Pages;

use App\Connectors\ConnectorRegistry;
use App\Models\AdNetwork;
use App\Support\NetworkAccess;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

/** The ad networks this user may use in the current workspace, with Connect buttons. */
class Networks extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Networks';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'networks';

    public function mount(): void
    {
        if ($m = session('connect_error')) {
            Notification::make()->title($m)->danger()->send();
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        $agency = Filament::getTenant();
        $ids = NetworkAccess::forUser(auth()->user(), $agency)->pluck('id');

        return $table
            ->query(AdNetwork::query()->whereIn('id', $ids)->orderBy('stage')->orderBy('name'))
            ->paginated(false)
            ->emptyStateHeading('No networks available yet')
            ->emptyStateDescription('Your plan or your workspace admin decides which networks you can connect.')
            ->columns([
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('category')->badge()->color('gray'),
                TextColumn::make('availability')->label('Status')->badge()
                    ->state(fn (AdNetwork $r) => ConnectorRegistry::for($r)->supportsConnect() ? 'Ready to connect' : ($r->hasKeys() ? 'Connector coming soon' : 'Waiting for setup'))
                    ->color(fn (string $state) => $state === 'Ready to connect' ? 'success' : 'gray'),
                TextColumn::make('connected')->label('Connected')
                    ->state(fn (AdNetwork $r) => $agency->connections()->where('ad_network_id', $r->id)->where('status', '!=', 'disconnected')->count()),
                TextColumn::make('who')->label('Who can use')
                    ->state(fn (AdNetwork $r) => count(NetworkAccess::limitedTo($agency, $r)) ? count(NetworkAccess::limitedTo($agency, $r)).' chosen member(s)' : 'Everyone'),
            ])
            ->recordActions([
                Action::make('connect')->label('Connect')->icon('heroicon-o-link')->button()
                    ->visible(fn (AdNetwork $r) => TeamRoles::canManageData(auth()->user(), $agency) && ConnectorRegistry::for($r)->supportsConnect())
                    ->url(fn (AdNetwork $r) => url('/app/connect/'.$r->key.'/start?agency='.$agency->slug)),
                Action::make('who')->label('Who can use')->icon('heroicon-o-user-group')
                    ->visible(fn () => TeamRoles::canManageTeam(auth()->user(), $agency))
                    ->schema([
                        Select::make('users')->label('Limit to these members')->multiple()
                            ->options(fn () => $agency->members()->pluck('name', 'users.id'))
                            ->default(fn (AdNetwork $r) => NetworkAccess::limitedTo($agency, $r))
                            ->helperText('Leave empty so everyone in the workspace can use it. Owners and admins always can.'),
                    ])
                    ->action(function (AdNetwork $r, array $data) use ($agency) {
                        NetworkAccess::setLimitedTo($agency, $r, $data['users'] ?? []);
                        Notification::make()->title('Saved')->success()->send();
                    }),
            ]);
    }
}
