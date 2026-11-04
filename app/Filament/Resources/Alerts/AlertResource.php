<?php

namespace App\Filament\Resources\Alerts;

use App\Filament\Resources\Alerts\Pages\ListAlerts;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use UnitEnum;

/** Alerts inbox: assign, snooze, resolve. */
class AlertResource extends Resource
{
    protected static ?string $model = Alert::class;

    protected static ?string $tenantRelationshipName = 'alerts';

    protected static ?string $tenantOwnershipRelationshipName = 'agency';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static string|UnitEnum|null $navigationGroup = 'Results';

    protected static ?string $navigationLabel = 'Alerts';

    protected static ?string $slug = 'alerts';

    protected static ?int $navigationSort = 11;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $a = Filament::getTenant();
        $n = $a ? static::getEloquentQuery()->where('status', 'open')->count() : 0;

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        $a = Filament::getTenant();
        $ids = Client::visibleTo(auth()->user(), $a)->pluck('id');

        return parent::getEloquentQuery()->where('agency_id', $a->id)
            ->where(fn ($q) => $q->whereNull('client_id')->orWhereIn('client_id', $ids));
    }

    protected static function act(Alert $a, array $set, string $what): void
    {
        $a->update($set);
        AuditLog::record('alert.'.$what, $a->agency, ['alert_id' => $a->id, 'rule' => $a->rule_key]);
    }

    public static function table(Table $table): Table
    {
        $agency = Filament::getTenant();
        $can = fn () => TeamRoles::canManageData(auth()->user(), $agency);
        $members = fn () => $agency->members()->pluck('name', 'users.id')->all();

        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No alerts')
            ->emptyStateDescription('Alert rules run every morning after the overnight sync.')
            ->columns([
                TextColumn::make('severity')->badge()->color(fn ($state) => ['high' => 'danger', 'medium' => 'warning', 'low' => 'gray'][$state] ?? 'gray')
                    ->formatStateUsing(fn ($state) => ucfirst($state)),
                TextColumn::make('title')->wrap()->weight('bold')->description(fn (Alert $r) => $r->detail)->searchable(),
                TextColumn::make('client.name')->label('Client')->toggleable(),
                TextColumn::make('rule_key')->label('Rule')->formatStateUsing(fn ($state) => config("alerts.rules.$state.label") ?? $state)->toggleable(),
                TextColumn::make('assignee.name')->label('Assigned to')->placeholder('—'),
                TextColumn::make('status')->badge()->color(fn ($state) => ['open' => 'danger', 'snoozed' => 'warning', 'resolved' => 'success'][$state] ?? 'gray')
                    ->description(fn (Alert $r) => $r->status === 'snoozed' && $r->snoozed_until ? 'until '.$r->snoozed_until->format('M j') : null),
                TextColumn::make('created_at')->label('Raised')->since(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => 'Open', 'snoozed' => 'Snoozed', 'resolved' => 'Resolved'])->default('open'),
                SelectFilter::make('severity')->options(config('alerts.severities')),
                SelectFilter::make('client_id')->label('Client')->options(fn () => Client::visibleTo(auth()->user(), $agency)->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('mine')->label('Assigned to me')->queries(true: fn ($q) => $q->where('assigned_to', auth()->id()), false: fn ($q) => $q, blank: fn ($q) => $q),
            ])
            ->recordActions([
                Action::make('assign')->label('Assign')->icon('heroicon-o-user-plus')->visible($can)
                    ->schema([Select::make('user')->label('Team member')->options($members)->default(fn (Alert $r) => $r->assigned_to)])
                    ->action(fn (Alert $r, array $data) => static::act($r, ['assigned_to' => $data['user'] ?: null], 'assigned')),
                Action::make('snooze')->label('Snooze')->icon('heroicon-o-clock')->color('warning')->visible(fn (Alert $r) => $can() && $r->status === 'open')
                    ->schema([Select::make('days')->options(['1' => '1 day', '3' => '3 days', '7' => '7 days'])->default('1')->required()])
                    ->action(fn (Alert $r, array $data) => static::act($r, ['status' => 'snoozed', 'snoozed_until' => now()->addDays((int) $data['days'])], 'snoozed')),
                Action::make('resolve')->label('Resolve')->icon('heroicon-o-check')->color('success')->visible(fn (Alert $r) => $can() && $r->status !== 'resolved')
                    ->action(fn (Alert $r) => static::act($r, ['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => auth()->id()], 'resolved')),
                Action::make('reopen')->label('Reopen')->icon('heroicon-o-arrow-uturn-left')->color('gray')->visible(fn (Alert $r) => $can() && $r->status !== 'open')
                    ->action(fn (Alert $r) => static::act($r, ['status' => 'open', 'snoozed_until' => null, 'resolved_at' => null, 'resolved_by' => null], 'reopened')),
            ])
            ->toolbarActions([
                BulkAction::make('resolveAll')->label('Resolve selected')->icon('heroicon-o-check')->visible($can)
                    ->action(fn (Collection $records) => $records->each(fn (Alert $r) => static::act($r, ['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => auth()->id()], 'resolved'))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAlerts::route('/')];
    }
}
