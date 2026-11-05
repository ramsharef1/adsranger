<?php

namespace App\Filament\Admin\Resources\Agencies;

use App\Alerts\AlertEngine;
use App\Filament\Admin\Resources\Agencies\Pages\EditAgency;
use App\Filament\Admin\Resources\Agencies\Pages\ListAgencies;
use App\Filament\Concerns\TranslatesResourceLabels;
use App\Models\Agency;
use App\Models\Plan;
use App\Support\DemoData;
use App\Support\Trial;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class AgencyResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Agency::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Agencies';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'super_admin';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Archived (soft-deleted by the D-61 lifecycle) agencies stay visible here, behind the "Archived" filter. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make(__('Agency'))->columns(2)->schema([
                TextInput::make('name')->label(__('Name'))->required()->maxLength(120),
                TextInput::make('slug')->disabled()->helperText(__('Used in the agency\'s /app URL.')),
                Select::make('country')->label(__('Country'))->options(config('adsranger.countries'))->searchable(),
                Select::make('currency')->options(collect(config('adsranger.currencies'))->mapWithKeys(fn ($c) => [$c => $c])),
            ])->columnSpan(2),
            Section::make(__('Plan and status'))->columns(2)->schema([
                Select::make('plan')->options(fn () => Plan::orderBy('sort')->pluck('name', 'key'))->required()
                    ->helperText(__('Controls which features and networks the agency gets.')),
                Select::make('status')->options(config('adsranger.agency_statuses'))->required()
                    ->helperText(__('Suspended agencies cannot sign in to their workspace.')),
                DateTimePicker::make('trial_ends_at')->label(__('Trial ends'))->seconds(false),
                Select::make('signup_source')->label(__('Signup source'))->options(static::sources())->disabled()->dehydrated(false),
            ])->columnSpan(2),
            // D-61 lifecycle state + dates (self-signup only; invited/admin agencies are never locked or deleted).
            Section::make(__('Trial lifecycle'))->columns(3)
                ->description(__('Self-signup agencies only. After the trial: read-only for 14 days, then closed for 30 days, then the data is deleted. "Extend trial" lifts any of these.'))
                ->visible(fn (?Agency $record) => $record && Trial::lifecycleApplies($record))
                ->schema([
                    TextEntry::make('lifecycle_stage')->label(__('Stage'))->state(fn (Agency $record) => Trial::stageLabel(Trial::stage($record)))->badge(),
                    TextEntry::make('read_only_until')->label(__('Read-only until'))->state(fn (Agency $record) => Trial::readOnlyUntil($record)?->format('Y-m-d') ?? '—'),
                    TextEntry::make('delete_on')->label(__('Data deleted on'))->state(fn (Agency $record) => Trial::deleteOn($record)?->format('Y-m-d') ?? '—'),
                    TextEntry::make('trial_ending_notified_at')->label(__('"Ends in 3 days" mail'))->state(fn (Agency $record) => $record->trial_ending_notified_at?->format('Y-m-d H:i') ?? '—'),
                    TextEntry::make('trial_ended_notified_at')->label(__('"Trial ended" mail'))->state(fn (Agency $record) => $record->trial_ended_notified_at?->format('Y-m-d H:i') ?? '—'),
                    TextEntry::make('deletion_notified_at')->label(__('"Deletion in 7 days" mail'))->state(fn (Agency $record) => $record->deletion_notified_at?->format('Y-m-d H:i') ?? '—'),
                    TextEntry::make('deleted_at')->label(__('Archived'))->state(fn (Agency $record) => $record->deleted_at?->format('Y-m-d H:i') ?? '—'),
                ])->columnSpan(2),
            Section::make(__('Network overrides'))->description(__('Layer 3 of the access rules. "Always allow" turns a network on for this agency even when it is off globally or not in its plan (good for beta tests). "Block" hides it, and wins over everything.'))->columns(2)->schema([
                CheckboxList::make('allowedNetworks')->label(__('Always allow'))->relationship('allowedNetworks', 'name'),
                CheckboxList::make('blockedNetworks')->label(__('Block'))->relationship('blockedNetworks', 'name'),
            ])->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('Name'))->searchable()->weight('bold'),
                TextColumn::make('owner.email')->label(__('Owner'))->searchable(),
                TextColumn::make('members_count')->counts('members')->label(__('Members')),
                TextColumn::make('plan')->badge()->formatStateUsing(fn (?string $state) => config('adsranger.plans')[$state] ?? $state),
                TextColumn::make('status')->label(__('Status'))->badge()
                    ->formatStateUsing(fn (?string $state) => __(config('adsranger.agency_statuses')[$state] ?? (string) $state))->color(fn (?string $state) => match ($state) {
                        'active' => 'success',
                        'trial' => 'warning',
                        'read_only' => 'warning',
                        'expired', 'suspended' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('signup_source')->label(__('Signup source'))->badge()
                    ->formatStateUsing(fn (?string $state) => static::sources()[$state] ?? $state)
                    ->color(fn (?string $state) => $state === 'self_signup' ? 'info' : 'gray'),
                TextColumn::make('trial_ends_at')->label(__('Trial ends'))->date('Y-m-d')->placeholder('—'),
                // D-61 lifecycle (self-signup only), with its dates in the tooltip.
                TextColumn::make('lifecycle')->label(__('Trial stage'))->badge()
                    ->state(fn (Agency $r) => Trial::stage($r) === Trial::NONE ? null : Trial::stageLabel(Trial::stage($r)))
                    ->color(fn (Agency $r) => match (Trial::stage($r)) {
                        Trial::READ_ONLY => 'warning', Trial::EXPIRED, Trial::ARCHIVED => 'danger', default => 'info',
                    })
                    ->tooltip(fn (Agency $r) => Trial::stage($r) === Trial::NONE ? null : __('Read-only until').' '.(Trial::readOnlyUntil($r)?->format('Y-m-d') ?? '—').' · '.__('Data deleted on').' '.(Trial::deleteOn($r)?->format('Y-m-d') ?? '—'))
                    ->placeholder('—'),
                TextColumn::make('country'),
                TextColumn::make('created_at')->label(__('Created'))->since(),
            ])
            ->filters([
                SelectFilter::make('plan')->options(config('adsranger.plans')),
                SelectFilter::make('status')->options(config('adsranger.agency_statuses')),
                SelectFilter::make('signup_source')->label(__('Signup source'))->options(static::sources()),
                TrashedFilter::make()->label(__('Archived')),
            ])
            ->recordActions([EditAction::make(), ActionGroup::make([
                static::extendTrialAction(),
                Action::make('demo')->label(__('Load demo data'))->icon('heroicon-o-beaker')->requiresConfirmation()
                    ->modalDescription(__('Adds 3 sample clients with 90 days of Meta-style results and runs the alert rules. Remove it any time.'))
                    ->action(function (Agency $r) {
                        $rows = array_sum(DemoData::seed($r));
                        $s = AlertEngine::run($r, now($r->timezone ?: 'UTC')->subDay()->toDateString());
                        Notification::make()->title(__('Demo data loaded: :rows rows, :alerts alerts', ['rows' => $rows, 'alerts' => $s['opened']]))->success()->send();
                    }),
                Action::make('undemo')->label(__('Remove demo data'))->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->action(function (Agency $r) {
                        DemoData::remove($r);
                        Notification::make()->title(__('Demo data removed'))->success()->send();
                    }),
            ])]);
    }

    /** @return array<string, string> */
    public static function sources(): array
    {
        return ['invite' => __('Invite'), 'self_signup' => __('Self-signup'), 'admin' => __('Created by admin')];
    }

    /** #158 / 911 part 3 point 10: extend a trial (days input, confirm with the new end date), audit `agency.trial_extended`. */
    public static function extendTrialAction(): Action
    {
        return Action::make('extendTrial')->label(__('Extend trial'))->icon('heroicon-o-clock')
            // D-61: also lifts a read-only, closed or archived self-signup workspace back to a running trial.
            ->visible(fn (Agency $r) => in_array($r->status, Trial::LIFECYCLE_STATUSES, true) || $r->trashed())
            ->modalHeading(__('Extend trial'))
            ->modalDescription(fn (Agency $r) => __('Counted from the current end date (:date), or from today if it has passed. You can change it again later.', ['date' => $r->trial_ends_at?->format('Y-m-d') ?? '—'])
                .(Trial::isReadOnly($r) ? ' '.__('This also lifts the read-only / closed state: the workspace opens again as a running trial.') : ''))
            ->schema([TextInput::make('days')->label(__('Days to add'))->numeric()->integer()->minValue(1)->maxValue(90)->default(7)->required()])
            ->modalSubmitActionLabel(__('Extend trial'))
            ->action(function (Agency $r, array $data) {
                $to = Trial::extend($r, (int) $data['days']);
                Notification::make()->title(__('Trial extended for :agency until :date', ['agency' => $r->name, 'date' => $to->format('Y-m-d')]))->success()->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgencies::route('/'),
            'edit' => EditAgency::route('/{record}/edit'),
        ];
    }
}
