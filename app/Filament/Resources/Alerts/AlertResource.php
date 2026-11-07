<?php

namespace App\Filament\Resources\Alerts;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Filament\Resources\Alerts\Pages\ListAlerts;
use App\Mail\AlertAssignedMail;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use App\Support\CriticalPush;
use App\Support\CurrentClient;
use App\Support\PlanGate;
use App\Support\Severity;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use UnitEnum;

/** Alerts inbox: assign, snooze, resolve. */
class AlertResource extends Resource
{
    use TranslatesResourceLabels;

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

        $q = parent::getEloquentQuery()->where('agency_id', $a->id)
            ->where(fn ($q) => $q->whereNull('client_id')->orWhereIn('client_id', $ids));

        // UX-81: with a current client chosen in the top bar, only that client's alerts.
        return CurrentClient::apply($q, 'client_id', $a);
    }

    protected static function act(Alert $a, array $set, string $what): void
    {
        $a->update($set);
        AuditLog::record('alert.'.$what, $a->agency, ['alert_id' => $a->id, 'rule' => $a->rule_key]);
    }

    /**
     * Assign / snooze / resolve / reopen, shared by the inbox rows and the phone alert page (#24, I-X-4).
     *
     * @return array<int, Action>
     */
    public static function alertActions(): array
    {
        $agency = Filament::getTenant();
        $can = fn () => TeamRoles::canManageData(auth()->user(), $agency);
        $members = fn () => $agency->members()->pluck('name', 'users.id')->all();

        return [
            Action::make('assign')->label(__('Assign'))->icon('heroicon-o-user-plus')->visible($can)
                ->schema([Select::make('user')->label(__('Team member'))->options($members)->default(fn (Alert $r) => $r->assigned_to)])
                ->action(function (Alert $r, array $data) {
                    $to = $data['user'] ?: null;
                    $before = $r->assigned_to;
                    static::act($r, ['assigned_to' => $to], 'assigned');
                    static::notifyAssignee($r, $to, $before);
                }),
            Action::make('snooze')->label(__('Snooze'))->icon('heroicon-o-clock')->color('warning')->visible(fn (Alert $r) => $can() && $r->status === 'open')
                ->schema([Select::make('days')->options(['1' => trans_choice(':count day|:count days', 1), '3' => trans_choice(':count day|:count days', 3), '7' => trans_choice(':count day|:count days', 7)])->default('1')->required()])
                ->action(fn (Alert $r, array $data) => static::act($r, ['status' => 'snoozed', 'snoozed_until' => now()->addDays((int) $data['days'])], 'snoozed')),
            Action::make('resolve')->label(__('Resolve'))->icon('heroicon-o-check')->color('success')->visible(fn (Alert $r) => $can() && $r->status !== 'resolved')
                ->action(fn (Alert $r) => static::act($r, ['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => auth()->id()], 'resolved')),
            Action::make('reopen')->label(__('Reopen'))->icon('heroicon-o-arrow-uturn-left')->color('gray')->visible(fn (Alert $r) => $can() && $r->status !== 'open')
                ->action(fn (Alert $r) => static::act($r, ['status' => 'open', 'snoozed_until' => null, 'resolved_at' => null, 'resolved_by' => null], 'reopened')),
        ];
    }

    /** #24: tell the new assignee (email if they opted in to alert emails; phone push on Pro+ if subscribed). */
    public static function notifyAssignee(Alert $r, ?int $to, ?int $before): void
    {
        if (! $to || $to === $before || $to === auth()->id()) {
            return;
        }
        $u = User::find($to);
        if (! $u) {
            return;
        }
        $url = CriticalPush::alertUrl($r->agency, $r);
        if ($u->email_alerts) {
            Mail::to($u->email)->queue(new AlertAssignedMail($r, $u, (string) auth()->user()?->name, $url));
        }
        if (PlanGate::allows($r->agency, 'phone_push') && $u->pushSubscriptions()->exists()) {
            $l = $r->agency->language === 'en' ? 'en' : 'ar';
            CriticalPush::push($u, [
                'title' => Str::limit(__('Alert assigned to you · :client', ['client' => $r->client?->name ?? $r->agency->name], $l), 60, '…'),
                'body' => Str::limit(__(config("alerts.rules.{$r->rule_key}.label") ?? $r->rule_key, [], $l).($r->entity_name ? ' · '.$r->entity_name : ''), 110, '…'),
            ], $url, 'adsr-assigned-'.$r->id);
        }
    }

    public static function table(Table $table): Table
    {
        $agency = Filament::getTenant();
        $can = fn () => TeamRoles::canManageData(auth()->user(), $agency);

        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('No alerts'))
            ->emptyStateDescription(__('Alert rules run every morning after the overnight sync.'))
            ->columns([
                TextColumn::make('severity')->label(__('Level'))->badge()->color(fn ($state) => Severity::color($state))->icon(fn ($state) => Severity::icon($state))
                    ->formatStateUsing(fn ($state) => Severity::label($state)),
                TextColumn::make('title')->wrap()->weight('bold')->formatStateUsing(fn (Alert $r) => $r->localTitle())->description(fn (Alert $r) => $r->localDetail())->searchable(),
                TextColumn::make('client.name')->label(__('Client'))->toggleable(),
                TextColumn::make('rule_key')->label(__('Rule'))->formatStateUsing(fn ($state) => __(config("alerts.rules.$state.label") ?? $state))->toggleable(),
                TextColumn::make('assignee.name')->label(__('Assigned to'))->placeholder('—'),
                TextColumn::make('status')->label(__('Status'))->badge()->formatStateUsing(fn ($state) => __($state))->color(fn ($state) => ['open' => 'danger', 'snoozed' => 'warning', 'resolved' => 'success'][$state] ?? 'gray')
                    ->description(fn (Alert $r) => $r->status === 'snoozed' && $r->snoozed_until ? __('until :date', ['date' => $r->snoozed_until->translatedFormat('j M')]) : null),
                TextColumn::make('created_at')->label(__('Raised'))->since(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['open' => __('Open'), 'snoozed' => __('Snoozed'), 'resolved' => __('Resolved')])->default('open'),
                SelectFilter::make('severity')->label(__('Level'))->options(fn () => Severity::options()),
                SelectFilter::make('client_id')->label(__('Client'))->options(fn () => Client::visibleTo(auth()->user(), $agency)->orderBy('name')->pluck('name', 'id')->all()),
                TernaryFilter::make('mine')->label(__('Assigned to me'))->queries(true: fn ($q) => $q->where('assigned_to', auth()->id()), false: fn ($q) => $q, blank: fn ($q) => $q),
            ])
            ->recordUrl(fn (Alert $r) => static::getUrl('view', ['record' => $r]))
            ->recordActions(static::alertActions())
            ->toolbarActions([
                BulkAction::make('resolveAll')->label(__('Resolve selected'))->icon('heroicon-o-check')->visible($can)
                    ->action(fn (Collection $records) => $records->each(fn (Alert $r) => static::act($r, ['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => auth()->id()], 'resolved'))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAlerts::route('/'), 'view' => Pages\ViewAlert::route('/{record}')];
    }
}
