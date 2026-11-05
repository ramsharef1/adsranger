<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Pages\ExportData;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\ClientLink;
use App\Models\ReportSchedule;
use App\Support\HealthScore;
use App\Support\MetricsQuery;
use App\Support\MonthlyReport;
use App\Support\Pacing;
use App\Support\Periods;
use App\Support\PlanGate;
use App\Support\TeamRoles;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

/** Client results: KPIs by goal, 6-month trend, campaigns → ad sets → ads drill-down, top ads. */
class ViewClient extends ViewRecord
{
    protected static string $resource = ClientResource::class;

    protected string $view = 'filament.pages.client-results';

    public string $period = '30d';

    public ?int $campaign = null;

    public ?int $adSet = null;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            // #56 signed client links: owners, admins and managers create and revoke; everyone in the workspace can see them.
            Action::make('clientLinks')
                ->label(__('Client links'))
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading(__('Client links'))
                ->modalDescription(__('Anyone with a link can see this client\'s results without an account. Revoke a link to stop it working.'))
                ->modalContent(fn () => view('filament.pages.client-links', [
                    'links' => $this->record->links()->latest()->get(),
                    'canManage' => $this->canManageLinks(),
                ]))
                ->schema(fn () => $this->canManageLinks() ? [
                    TextInput::make('label')->label(__('Name (optional)'))->maxLength(80)->placeholder(__('e.g. Monthly review')),
                    Select::make('days')->label(__('Expires'))->options(['' => __('Never'), '30' => __('In 30 days'), '90' => __('In 90 days')])->default(''),
                ] : [])
                ->modalSubmitActionLabel(__('Create link'))
                ->modalSubmitAction(fn ($action) => $this->canManageLinks() ? $action : false)
                ->action(function (array $data): void {
                    abort_unless($this->canManageLinks(), 403);
                    $link = ClientLink::issue($this->record, auth()->user(), $data['label'] ?? null, ($data['days'] ?? '') !== '' ? (int) $data['days'] : null);
                    AuditLog::record('client_link.created', $this->record, ['link_id' => $link->id, 'expires_at' => $link->expires_at?->toDateString()]);
                    Notification::make()->title(__('Client link created'))->body($link->url())->success()->persistent()->send();
                }),
            // #57 monthly PDF report (AR / EN), any role that can see the client.
            Action::make('monthlyPdf')
                ->label(__('Monthly PDF'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->modalHeading(__('Monthly PDF report'))
                ->schema([
                    Select::make('month')->label(__('Month'))->options(fn () => MonthlyReport::months(12, app()->getLocale() === 'ar' ? 'ar' : 'en', $this->record->agency->timezone ?: 'UTC'))
                        ->default(fn () => array_key_first(MonthlyReport::months(1)))->required(),
                    Select::make('lang')->label(__('Language'))->options(['ar' => 'العربية', 'en' => 'English'])
                        ->default(fn () => $this->record->agency->language === 'en' ? 'en' : 'ar')->required(),
                ])
                ->modalSubmitActionLabel(__('Download PDF'))
                ->action(function (array $data) {
                    abort_unless(MonthlyReport::validMonth($data['month'] ?? null) && in_array($data['lang'] ?? null, ['ar', 'en'], true), 422);
                    AuditLog::record('report.monthly_pdf', $this->record, ['month' => $data['month'], 'lang' => $data['lang']]);
                    $pdf = MonthlyReport::pdf($this->record, $data['month'], $data['lang']);

                    return response()->streamDownload(fn () => print ($pdf), MonthlyReport::filename($this->record, $data['month'], $data['lang']), ['Content-Type' => 'application/pdf']);
                }),
            // #79 this client's daily metrics as CSV (any role that can see the client).
            Action::make('exportCsv')
                ->label(__('Export CSV'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->modalHeading(__('Daily metrics CSV'))
                ->modalDescription(__('One row per day, ad account and campaign, in each account\'s own currency.'))
                ->schema(fn () => [
                    ...ExportData::periodFields(),
                    Toggle::make('for_client')->label(__('A copy for the client'))
                        ->helperText($this->record->hide_spend ? __('Spend is hidden from this client, so spend and cost per result are left out.') : __('This client sees spend, so the file is the same.'))
                        ->default((bool) $this->record->hide_spend),
                ])
                ->modalSubmitActionLabel(__('Download CSV'))
                ->action(function (array $data) {
                    $this->redirect(ExportData::exportUrl('metrics', ['client' => $this->record->id, 'from' => $data['from'], 'to' => $data['to'], 'for_client' => ! empty($data['for_client']) ? 1 : 0]));
                }),
            // #106 scheduled email reports: owners, admins and account managers (EX-37 hides it from analysts).
            Action::make('emailReports')
                ->label(__('Email reports'))
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn () => $this->canManageLinks())
                ->modalHeading(__('Scheduled email reports'))
                ->modalDescription(fn () => $this->reportsLocked() ? null : __('Email this client\'s results on a schedule. Until our mail provider is live, these emails can land in spam: ask recipients to check spam and mark them as safe.'))
                ->modalContent(fn () => $this->reportsLocked()
                    ? view('components.locked-state-body', ['feature' => 'scheduled_reports', 'tier' => PlanGate::requiredTier('scheduled_reports'), 'canUpgrade' => PlanGate::canUpgrade(auth()->user(), $this->record->agency)])
                    : view('filament.pages.report-schedule-status', ['s' => $this->record->reportSchedule()->with('agency', 'link')->first(), 'client' => $this->record]))
                ->fillForm(fn () => $this->scheduleFormData())
                ->schema(fn () => $this->reportsLocked() ? [] : $this->scheduleSchema())
                ->modalSubmitActionLabel(__('Save schedule'))
                ->modalSubmitAction(fn ($action) => $this->reportsLocked() ? false : $action)
                ->action(fn (array $data) => $this->saveSchedule($data)),
            EditAction::make()->label(__('Settings & targets')),
        ];
    }

    public function reportsLocked(): bool
    {
        return PlanGate::locked($this->record->agency, 'scheduled_reports');
    }

    public static function weekdays(): array
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $monday = CarbonImmutable::now()->startOfWeek(CarbonInterface::MONDAY);
        $out = [];
        for ($i = 1; $i <= 7; $i++) {
            $out[$i] = $monday->addDays($i - 1)->locale($locale)->dayName;
        }

        return $out;
    }

    protected function scheduleFormData(): array
    {
        $s = $this->record->reportSchedule;
        $lang = $this->record->agency->language === 'en' ? 'en' : 'ar';

        return $s ? [
            'monthly' => (bool) $s->monthly_day, 'monthly_day' => $s->monthly_day ?: 1,
            'weekly' => (bool) $s->weekly_day, 'weekly_day' => $s->weekly_day ?: 7,
            'lang' => $s->lang, 'recipients' => $s->recipients, 'attach_pdf' => $s->attach_pdf, 'include_link' => $s->include_link, 'active' => $s->active,
        ] : ['monthly' => true, 'monthly_day' => 1, 'weekly' => false, 'weekly_day' => 7, 'lang' => $lang, 'recipients' => [],
            'attach_pdf' => true, 'include_link' => true, 'active' => true];
    }

    protected function scheduleSchema(): array
    {
        $max = (int) config('reports.max_recipients', 10);

        return [
            TagsInput::make('recipients')->label(__('Recipients'))->placeholder(__('name@example.com'))
                ->helperText(__('Up to :n email addresses. Press Enter after each one.', ['n' => $max]))
                ->splitKeys([',', ' ', ';'])->required()->rules(['array', 'max:'.$max])->nestedRecursiveRules(['email:filter', 'max:190']),
            Toggle::make('monthly')->label(__('Monthly report'))->helperText(__('Last month\'s results, with the monthly PDF if you attach it.'))->live(),
            Select::make('monthly_day')->label(__('Day of the month'))->options(array_combine(range(1, 28), range(1, 28)))
                ->visible(fn (Get $get) => (bool) $get('monthly'))->required(fn (Get $get) => (bool) $get('monthly')),
            Toggle::make('weekly')->label(__('Weekly summary'))->helperText(__('The last 7 days compared with the 7 days before.'))->live(),
            Select::make('weekly_day')->label(__('Day of the week'))->options(static::weekdays())
                ->visible(fn (Get $get) => (bool) $get('weekly'))->required(fn (Get $get) => (bool) $get('weekly')),
            Select::make('lang')->label(__('Language'))->options(['ar' => 'العربية', 'en' => 'English'])->required(),
            Toggle::make('attach_pdf')->label(__('Attach the monthly PDF'))->helperText(__('Sent with the monthly email.')),
            Toggle::make('include_link')->label(__('Include a client link'))->helperText(__('A read-only link to the live report. You can revoke it under Client links.')),
            Toggle::make('active')->label(__('Send these emails'))->helperText(__('Turn off to pause without losing the settings.')),
        ];
    }

    public function saveSchedule(array $data): void
    {
        abort_unless($this->canManageLinks() && ! $this->reportsLocked(), 403);
        $recipients = array_values(array_unique(array_map(fn ($e) => strtolower(trim((string) $e)), (array) ($data['recipients'] ?? []))));
        $max = (int) config('reports.max_recipients', 10);
        $bad = array_filter($recipients, fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL));
        if (! $recipients || count($recipients) > $max || $bad) {
            throw ValidationException::withMessages(['mountedActions.0.data.recipients' => __('Add 1 to :n valid email addresses.', ['n' => $max])]);
        }
        $monthly = ! empty($data['monthly']) ? max(1, min(28, (int) ($data['monthly_day'] ?? 1))) : null;
        $weekly = ! empty($data['weekly']) ? max(1, min(7, (int) ($data['weekly_day'] ?? 7))) : null;
        if (! $monthly && ! $weekly) {
            throw ValidationException::withMessages(['mountedActions.0.data.monthly' => __('Choose a monthly report, a weekly summary or both.')]);
        }

        $c = $this->record;
        $s = $c->reportSchedule ?? new ReportSchedule(['agency_id' => $c->agency_id, 'client_id' => $c->id, 'created_by' => auth()->id()]);
        $s->fill([
            'monthly_day' => $monthly, 'weekly_day' => $weekly, 'lang' => ($data['lang'] ?? 'ar') === 'en' ? 'en' : 'ar',
            'recipients' => $recipients, 'attach_pdf' => ! empty($data['attach_pdf']), 'include_link' => ! empty($data['include_link']),
            'active' => ! empty($data['active']),
        ]);
        if ($s->include_link && ! $s->activeLink()) {
            $link = ClientLink::issue($c, auth()->user(), __('Scheduled reports'));
            AuditLog::record('client_link.created', $c, ['link_id' => $link->id, 'expires_at' => null, 'for' => 'report_schedule']);
            $s->client_link_id = $link->id;
        }
        $s->save();
        AuditLog::record('report.schedule_saved', $c, ['schedule_id' => $s->id, 'monthly_day' => $monthly, 'weekly_day' => $weekly,
            'lang' => $s->lang, 'recipients' => count($recipients), 'attach_pdf' => $s->attach_pdf, 'include_link' => $s->include_link, 'active' => $s->active]);
        $c->unsetRelation('reportSchedule');
        Notification::make()->title(__('Report schedule saved'))
            ->body(($next = $s->nextSendDate()) ? __('Next email: :date', ['date' => $next->toDateString()]) : null)->success()->send();
    }

    public function canManageLinks(): bool
    {
        return TeamRoles::canManageData(auth()->user(), $this->record->agency);
    }

    public function revokeLink(int $id): void
    {
        abort_unless($this->canManageLinks(), 403);
        $link = $this->record->links()->whereKey($id)->firstOrFail();
        $link->forceFill(['revoked_at' => now()])->save();
        AuditLog::record('client_link.revoked', $this->record, ['link_id' => $link->id]);
        Notification::make()->title(__('Link revoked'))->success()->send();
    }

    public function setPeriod(string $p): void
    {
        $this->period = array_key_exists($p, Periods::options()) ? $p : '30d';
    }

    public function openCampaign(?int $id): void
    {
        $this->campaign = $id;
        $this->adSet = null;
    }

    public function openAdSet(?int $id): void
    {
        $this->adSet = $id;
    }

    protected function getViewData(): array
    {
        $c = $this->record;
        [$from, $to] = Periods::range($this->period, $c->agency->timezone ?? 'UTC');
        [$pf, $pt] = Periods::previous($from, $to);
        $level = $this->adSet ? 'ad' : ($this->campaign ? 'ad_set' : 'campaign');

        $t = MetricsQuery::totals($c, $from, $to);

        return [
            'client' => $c,
            'health' => HealthScore::compute($c),
            'pace' => Pacing::month($c),
            'forecastLocked' => PlanGate::locked($c->agency, 'forecast'),
            'funnelLocked' => PlanGate::locked($c->agency, 'funnel'),
            'canUpgrade' => PlanGate::canUpgrade(auth()->user(), $c->agency),
            'funnel' => MetricsQuery::funnel($t),
            'actions' => MetricsQuery::conversionActions($c, $from, $to),
            'from' => $from, 'to' => $to,
            'periods' => Periods::options(),
            't' => $t,
            'prev' => MetricsQuery::totals($c, $pf, $pt),
            'months' => MetricsQuery::monthly($c, 6),
            'level' => $level,
            'rows' => MetricsQuery::breakdown($c, $from, $to, $level, $this->adSet ?: $this->campaign),
            'crumbs' => [
                'campaign' => $this->campaign ? Campaign::find($this->campaign)?->name : null,
                'ad_set' => $this->adSet ? AdSet::find($this->adSet)?->name : null,
            ],
            'top' => MetricsQuery::topAds($c, $from, $to),
            'alerts' => Alert::where('client_id', $c->id)->where('status', 'open')->latest()->limit(5)->get(),
            'synced' => MetricsQuery::lastSynced($c),
            'accounts' => $c->adAccounts()->with('network')->get(),
        ];
    }
}
