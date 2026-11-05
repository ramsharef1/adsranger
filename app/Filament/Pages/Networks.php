<?php

namespace App\Filament\Pages;

use App\Connectors\ConnectorRegistry;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Http\Controllers\ConnectController;
use App\Models\AdNetwork;
use App\Models\AuditLog;
use App\Models\Connection;
use App\Support\Bidi;
use App\Support\NetworkAccess;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use UnitEnum;

/** The ad networks this user may use in the current workspace, with Connect buttons. */
class Networks extends Page implements HasTable
{
    use InteractsWithTable;
    use TranslatesPageLabels;

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

    /** Connect a network with the agency's own API credentials. */
    protected function connectWithKeys(AdNetwork $net, array $data): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        abort_unless(TeamRoles::canManageData($user, $agency) && NetworkAccess::allows($user, $agency, $net), 403);
        $connector = ConnectorRegistry::for($net);
        abort_unless($connector->connectMode() === 'keys', 404);

        $key = 'connect-keys:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 6)) {
            Notification::make()->title(__('Too many attempts. Try again in a few minutes.'))->danger()->send();

            return;
        }
        RateLimiter::hit($key, 600);

        $input = [];
        foreach ($data as $k => $v) {
            if (str_starts_with($k, 'k_')) {
                $input[substr($k, 2)] = $v;
            }
        }
        try {
            $t = $connector->connectWithKeys($input);
        } catch (\Throwable $e) {
            Notification::make()->title(__('Could not connect :network.', ['network' => Bidi::isolate($net->name)]))->body(Str::limit($e->getMessage(), 220))->danger()->persistent()->send();

            return;
        }
        $connection = Connection::create([
            'agency_id' => $agency->id, 'ad_network_id' => $net->id, 'created_by' => $user->id,
            'status' => 'active', 'environment' => $net->environment ?? 'live',
            'external_user_id' => $t['user_id'], 'external_user_name' => $t['user_name'],
            'access_token' => $t['access_token'], 'credentials' => $t['credentials'],
            'token_expires_at' => $t['expires_in'] ? now()->addSeconds(max(60, (int) $t['expires_in'] - 60)) : null,
            'scopes' => 'api-keys',
        ]);
        AuditLog::record('connection.created', $agency, ['network' => $net->key, 'connection_id' => $connection->id, 'method' => 'api_keys']);
        $found = ConnectController::importAccounts($connector, $connection, $agency);
        RateLimiter::clear($key);
        $n = Notification::make()->title(__(':network connected', ['network' => Bidi::isolate($net->name)]))
            ->body(trans_choice('{0} No accounts found.|{1} :count account found.|[2,*] :count accounts found.', $found).($connection->fresh()->status === 'error' ? ' '.__('Listing accounts failed: :error', ['error' => Str::limit((string) $connection->fresh()->last_error, 160)]) : ''));
        ($found ? $n->success() : $n->warning())->send();
    }

    public function table(Table $table): Table
    {
        $agency = Filament::getTenant();
        $ids = NetworkAccess::forUser(auth()->user(), $agency)->pluck('id');

        return $table
            ->query(AdNetwork::query()->whereIn('id', $ids)->orderBy('stage')->orderBy('name'))
            ->paginated(false)
            ->emptyStateHeading(__('No networks available yet'))
            ->emptyStateDescription(__('Your plan or your workspace admin decides which networks you can connect.'))
            ->columns([
                TextColumn::make('name')->label(__('Name'))->weight('bold'),
                TextColumn::make('category')->label(__('Category'))->badge()->color('gray'),
                TextColumn::make('availability')->label(__('Status'))->badge()
                    ->state(fn (AdNetwork $r) => ConnectorRegistry::for($r)->supportsConnect() ? (ConnectorRegistry::for($r)->connectMode() === 'keys' ? 'Connect with your API keys' : 'Ready to connect') : ($r->hasKeys() ? 'Connector coming soon' : 'Waiting for setup'))
                    ->formatStateUsing(fn (string $state) => __($state))
                    ->color(fn (string $state) => str_starts_with($state, 'Ready') || str_starts_with($state, 'Connect with') ? 'success' : 'gray'),
                TextColumn::make('connected')->label(__('Connected'))
                    ->state(fn (AdNetwork $r) => $agency->connections()->where('ad_network_id', $r->id)->where('status', '!=', 'disconnected')->count()),
                TextColumn::make('who')->label(__('Who can use'))
                    ->state(fn (AdNetwork $r) => count(NetworkAccess::limitedTo($agency, $r)) ? trans_choice('{1} :count chosen member|[2,*] :count chosen members', count(NetworkAccess::limitedTo($agency, $r))) : __('Everyone')),
            ])
            ->recordActions([
                Action::make('connect')->label(__('Connect'))->icon('heroicon-o-link')->button()
                    ->visible(fn (AdNetwork $r) => TeamRoles::canManageData(auth()->user(), $agency) && ConnectorRegistry::for($r)->connectMode() === 'oauth' && ConnectorRegistry::for($r)->supportsConnect())
                    ->url(fn (AdNetwork $r) => url('/app/connect/'.$r->key.'/start?agency='.$agency->slug)),
                Action::make('connectKeys')->label(__('Connect'))->icon('heroicon-o-key')->button()
                    ->visible(fn (AdNetwork $r) => TeamRoles::canManageData(auth()->user(), $agency) && ConnectorRegistry::for($r)->connectMode() === 'keys')
                    ->modalHeading(fn (AdNetwork $r) => __('Connect :network', ['network' => Bidi::isolate($r->name)]))
                    ->modalDescription(fn (AdNetwork $r) => ConnectorRegistry::for($r)->keyHelp().' '.__('Your credentials are stored encrypted and used only to read your ad data.'))
                    ->modalSubmitActionLabel(__('Connect'))
                    ->schema(fn (AdNetwork $r) => collect(ConnectorRegistry::for($r)->keyFields())->map(fn (array $f) => ($f[4] ?? 'text') === 'textarea'
                        ? Textarea::make('k_'.$f[0])->label($f[1])->helperText($f[3])->rows(5)->maxLength(8000)->required(empty($f[5]))->extraInputAttributes(['dir' => 'ltr', 'autocomplete' => 'off', 'spellcheck' => 'false'])
                        : TextInput::make('k_'.$f[0])->label($f[1])->helperText($f[3])->maxLength(500)->required(empty($f[5]))->password($f[2])->revealable($f[2])
                            ->extraInputAttributes(['dir' => 'ltr', 'autocomplete' => $f[2] ? 'new-password' : 'off']))->all())
                    ->action(fn (AdNetwork $r, array $data) => $this->connectWithKeys($r, $data)),
                Action::make('who')->label(__('Who can use'))->icon('heroicon-o-user-group')
                    ->visible(fn () => TeamRoles::canManageTeam(auth()->user(), $agency))
                    ->schema([
                        Select::make('users')->label(__('Limit to these members'))->multiple()
                            ->options(fn () => $agency->members()->pluck('name', 'users.id'))
                            ->default(fn (AdNetwork $r) => NetworkAccess::limitedTo($agency, $r))
                            ->helperText(__('Leave empty so everyone in the workspace can use it. Owners and admins always can.')),
                    ])
                    ->action(function (AdNetwork $r, array $data) use ($agency) {
                        NetworkAccess::setLimitedTo($agency, $r, $data['users'] ?? []);
                        Notification::make()->title(__('Saved'))->success()->send();
                    }),
            ]);
    }
}
