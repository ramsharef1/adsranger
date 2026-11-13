<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Http\Controllers\ExportController;
use App\Models\Client;
use App\Support\Modules;
use App\Support\TrialLifecycle;
use App\Support\TwoPerson;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

/**
 * #79 Data › Export data: CSV downloads of what this user can see (daily metrics for a period, clients, ad
 * accounts), for the owner the whole workspace as a ZIP of CSVs ("download my data"), and self-serve
 * workspace / personal account deletion with password confirmation.
 */
class ExportData extends Page
{
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?string $navigationLabel = 'Export data';

    protected static ?string $title = 'Export data';

    protected static ?string $slug = 'export-data';

    protected static ?int $navigationSort = 24;

    protected string $view = 'filament.pages.export-data';

    public static function exportUrl(string $name, array $query = []): string
    {
        return route('filament.app.export.'.$name, ['tenant' => Filament::getTenant()->slug] + $query);
    }

    /** Period fields shared by the workspace and client exports (default: last 30 days to yesterday). */
    public static function periodFields(): array
    {
        $tz = Filament::getTenant()?->timezone ?: 'UTC';
        $yesterday = CarbonImmutable::now($tz)->subDay();

        return [
            DatePicker::make('from')->label(__('From'))->native()->required()->default($yesterday->subDays(29)->toDateString())
                ->maxDate($yesterday->addDay()->toDateString()),
            DatePicker::make('to')->label(__('To'))->native()->required()->default($yesterday->toDateString())->afterOrEqual('from')
                ->maxDate($yesterday->addDay()->toDateString()),
        ];
    }

    public function metricsAction(): Action
    {
        $agency = Filament::getTenant();

        return Action::make('metrics')
            ->label(__('Download daily metrics'))
            ->icon('heroicon-o-arrow-down-tray')
            ->modalHeading(__('Daily metrics'))
            ->modalDescription(__('One row per day, ad account and campaign, in each account\'s own currency.'))
            ->schema([
                Select::make('client')->label(__('Client'))->placeholder(__('All clients you can see'))
                    ->options(fn () => Client::visibleTo(auth()->user(), $agency)->orderBy('name')->pluck('name', 'id'))->searchable(),
                ...static::periodFields(),
                // DB-04 (D-295): Excel with right-to-left sheets in Arabic, or CSV.
                Select::make('format')->label(__('Format'))->options(['xlsx' => __('Excel (.xlsx)'), 'csv' => 'CSV'])->default('xlsx')->required(),
            ])
            ->modalSubmitActionLabel(__('Download'))
            ->action(function (array $data) {
                $q = ['from' => $data['from'], 'to' => $data['to']];
                if (! empty($data['client'])) {
                    $q['client'] = $data['client'];
                }
                $this->redirect(static::exportUrl(($data['format'] ?? 'csv') === 'xlsx' ? 'metrics.xlsx' : 'metrics', $q));
            });
    }

    public function clientsAction(): Action
    {
        return Action::make('clients')->label(__('Download clients'))->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->url(fn () => static::exportUrl('clients'));
    }

    public function accountsAction(): Action
    {
        return Action::make('accounts')->label(__('Download ad accounts'))->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->url(fn () => static::exportUrl('accounts'));
    }

    public function workspaceAction(): Action
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        // DS-35 (D-305): with 2+ owners / admins, the full download waits for a second owner or admin.
        $gate = $agency && $user && TwoPerson::needed($agency) && ! TwoPerson::approved($agency, 'export_workspace', null, $user);
        $pending = $gate && TwoPerson::pending($agency, 'export_workspace', null, $user);

        $a = Action::make('workspace')->label($gate ? ($pending ? __('Waiting for approval to download all data') : __('Ask to download all workspace data')) : __('Download all workspace data'))
            ->icon('heroicon-o-archive-box-arrow-down')->color('gray')
            ->visible(fn () => ExportController::canDownloadWorkspace(auth()->user(), Filament::getTenant()));

        return $gate
            ? $a->disabled($pending)->requiresConfirmation()->modalDescription(__('Another owner or admin must approve before all workspace data can be downloaded. The request expires in 48 hours.'))
                ->action(function () use ($agency, $user): void {
                    TwoPerson::request($agency, 'export_workspace', null, null, $user);
                    Notification::make()->title(__('Request sent. Another owner or admin can approve it on their Desk.'))->success()->send();
                })
            : $a->url(fn () => static::exportUrl('workspace'));
    }

    public function deleteWorkspaceAction(): Action
    {
        $agency = Filament::getTenant();

        return Action::make('deleteWorkspace')
            ->label(__('Delete workspace'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->visible(fn () => auth()->user()?->roleIn(Filament::getTenant()) === 'owner' && Modules::on('self_serve_deletion'))
            ->modalHeading(__('Delete workspace'))
            ->modalDescription(__('We close access now and keep the workspace data for 30 days, during which you can ask support to restore it. After that, our team deletes it for good.').' '.__('Enter your password and the workspace slug to confirm.'))
            ->schema([
                TextInput::make('password')
                    ->label(__('Current password'))
                    ->password()
                    ->required()
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        $user = auth()->user();
                        if (! $user || ! Hash::check((string) $value, $user->password)) {
                            $fail(__('The password is incorrect.'));
                        }
                    }),
                TextInput::make('confirm_slug')
                    ->label(__('Workspace slug'))
                    ->helperText($agency?->slug ?? '')
                    ->required()
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        $tenant = Filament::getTenant();
                        if (! $tenant || trim((string) $value) !== $tenant->slug) {
                            $fail(__('The workspace slug does not match.'));
                        }
                    }),
            ])
            ->modalSubmitActionLabel(__('Delete workspace'))
            ->action(function () {
                $user = auth()->user();
                $tenant = Filament::getTenant();
                if (! $user || ! $tenant || $user->roleIn($tenant) !== 'owner' || ! Modules::on('self_serve_deletion')) {
                    abort(403);
                }

                TrialLifecycle::archiveSelfServe($tenant, $user);
                Notification::make()->title(__('Workspace scheduled for deletion.'))->success()->send();
                $this->redirect('/');
            });
    }

    public function deleteAccountAction(): Action
    {
        return Action::make('deleteAccount')
            ->label(__('Delete my account'))
            ->icon('heroicon-o-user-minus')
            ->color('danger')
            ->visible(function () {
                $u = auth()->user();
                $tenant = Filament::getTenant();
                if (! $u || $u->isSuperAdmin() || ! Modules::on('self_serve_deletion')) {
                    return false;
                }

                return $tenant ? $u->roleIn($tenant) !== 'owner' : true;
            })
            ->modalHeading(__('Delete my account'))
            ->modalDescription(__('This permanently removes your user account from AdsRanger. Type DELETE and enter your password to confirm.'))
            ->schema([
                TextInput::make('password')
                    ->label(__('Current password'))
                    ->password()
                    ->required()
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        $user = auth()->user();
                        if (! $user || ! Hash::check((string) $value, $user->password)) {
                            $fail(__('The password is incorrect.'));
                        }
                    }),
                TextInput::make('confirm_word')
                    ->label(__('Type DELETE to confirm'))
                    ->required()
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (strtoupper(trim((string) $value)) !== 'DELETE') {
                            $fail(__('Please type DELETE to confirm.'));
                        }
                        if (auth()->user()?->agencies()->wherePivot('role', 'owner')->exists()) {
                            $fail(__('Delete your owned workspaces first before deleting your personal account.'));
                        }
                    }),
            ])
            ->modalSubmitActionLabel(__('Delete my account'))
            ->action(function () {
                $user = auth()->user();
                if (! $user || $user->isSuperAdmin() || ! Modules::on('self_serve_deletion')) {
                    abort(403);
                }

                Auth::logout();
                TrialLifecycle::deletePersonalAccount($user);
                if (request()?->hasSession()) {
                    request()->session()->invalidate();
                    request()->session()->regenerateToken();
                }
                $this->redirect('/');
            });
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $role = $user?->roleIn($agency);

        return [
            'restricted' => ! in_array($role, ['owner', 'admin'], true) && ! $user?->isSuperAdmin(),
            'canZip' => ExportController::canDownloadWorkspace($user, $agency),
            'canDeleteWorkspace' => $role === 'owner' && Modules::on('self_serve_deletion'),
            'canDeleteAccount' => $user && ! $user->isSuperAdmin() && $role !== 'owner' && Modules::on('self_serve_deletion'),
        ];
    }
}
