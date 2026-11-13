<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Pages\ExportData;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientLicence;
use App\Models\ClientLink;
use App\Models\CodWeek;
use App\Models\ReportSchedule;
use App\Support\ClientFreeze;
use App\Support\ClientSetupCopy;
use App\Support\ClientSummaryText;
use App\Support\CodOutcomes;
use App\Support\DataGaps;
use App\Support\HealthScore;
use App\Support\MetricsQuery;
use App\Support\Modules;
use App\Support\MonthlyReport;
use App\Support\MonthNotes;
use App\Support\Pacing;
use App\Support\Periods;
use App\Support\PlanGate;
use App\Support\TeamRoles;
use App\Support\WhatsAppReport;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Storage;
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
            // XS-24 / NB-36 (D-305): licence type, number and expiry; printed on briefs, Desk reminder 30 days before.
            Action::make('addLicence')->label(__('Add licence'))->icon('heroicon-o-identification')->color('gray')
                ->visible(fn () => TeamRoles::canManageData(auth()->user(), $this->record->agency))
                ->modalDescription(__('For ads that must show a licence or permit number. AdsRanger keeps the number and reminds you before it expires; it does not judge whether an ad is compliant.'))
                ->schema([
                    Select::make('kind')->label(__('Type'))->options(ClientLicence::kindLabels())->required()->default('real_estate'),
                    TextInput::make('number')->label(__('Licence number'))->required()->maxLength(80),
                    DatePicker::make('expires_on')->label(__('Expires on (optional)'))->native(false),
                    TextInput::make('note')->label(__('Note'))->maxLength(200),
                ])
                ->action(function (array $data): void {
                    ClientLicence::create(['agency_id' => $this->record->agency_id, 'client_id' => $this->record->id, 'kind' => $data['kind'],
                        'number' => trim(strip_tags($data['number'])), 'expires_on' => $data['expires_on'] ?? null, 'note' => trim(strip_tags((string) ($data['note'] ?? ''))) ?: null]);
                    Notification::make()->title(__('Licence saved.'))->success()->send();
                }),
            // XS-03 / FS-20 (R4): "what we did" and "next month" for the monthly report; drafted from the app, edited by the agency.
            Action::make('monthNotes')->label(__('Month notes for the report'))->icon('heroicon-o-pencil-square')->color('gray')
                ->visible(fn () => TeamRoles::canManageData(auth()->user(), $this->record->agency))
                ->fillForm(function (): array {
                    $month = now($this->record->agency?->timezone ?: 'UTC')->format('Y-m');
                    $lang = $this->record->reportSchedule?->lang === 'en' ? 'en' : 'ar';
                    $saved = MonthNotes::get($this->record, $month);
                    $draft = MonthNotes::draft($this->record, $month, $lang);

                    return ['month' => $month, 'done' => $saved?->done ?? $draft['done'], 'next' => $saved?->next ?? $draft['next']];
                })
                ->modalDescription(__('Drafted from what happened in the app. Edit freely: it goes into the monthly report as written.'))
                ->schema([
                    Select::make('month')->label(__('Month'))->required()
                        ->options(fn () => [now($this->record->agency?->timezone ?: 'UTC')->format('Y-m') => __('This month')] + MonthlyReport::months(6, app()->getLocale(), $this->record->agency?->timezone ?: 'UTC')),
                    Textarea::make('done')->label(__('What we did this month'))->rows(6)->maxLength(3000),
                    Textarea::make('next')->label(__('Next month'))->rows(4)->maxLength(3000),
                ])
                ->action(function (array $data): void {
                    MonthNotes::save($this->record, $data['month'], $data['done'] ?? null, $data['next'] ?? null, auth()->id());
                    Notification::make()->title(__('Saved. It will appear in that month\'s report.'))->success()->send();
                }),
            // XS-08 (R4): the period's summary as text ready for WhatsApp, Arabic and English (no AI, no AdsRanger name).
            Action::make('copyWhatsApp')->label(__('Copy as WhatsApp message'))->icon('heroicon-o-clipboard-document')->color('gray')
                ->modalSubmitAction(false)->modalCancelActionLabel(__('Close'))
                ->modalContent(function () {
                    [$from, $to] = Periods::range($this->period, $this->record->agency?->timezone ?? 'UTC');

                    return view('filament.pages.client-whatsapp-text', ['texts' => ['ar' => ClientSummaryText::make($this->record, $from, $to, 'ar'), 'en' => ClientSummaryText::make($this->record, $from, $to, 'en')]]);
                }),
            // XS-02 (R4, D-303): log a call, WhatsApp chat or meeting, with an optional next contact date.
            Action::make('logContact')->label(__('Log contact'))->icon('heroicon-o-chat-bubble-left-right')->color('gray')
                ->visible(fn () => TeamRoles::canManageData(auth()->user(), $this->record->agency))
                ->schema([
                    Select::make('kind')->label(__('Type'))->options(ClientContact::kindLabels())->default('call')->required(),
                    DatePicker::make('happened_on')->label(__('Date'))->default(now($this->record->agency?->timezone ?: 'UTC')->toDateString())->required()->native(false),
                    Textarea::make('note')->label(__('Note'))->rows(3)->maxLength(2000),
                    DatePicker::make('next_on')->label(__('Next contact (optional)'))->native(false)->afterOrEqual('happened_on'),
                ])
                ->action(function (array $data): void {
                    ClientContact::create(['agency_id' => $this->record->agency_id, 'client_id' => $this->record->id, 'user_id' => auth()->id(),
                        'kind' => $data['kind'], 'happened_on' => $data['happened_on'], 'note' => trim(strip_tags((string) ($data['note'] ?? ''))) ?: null, 'next_on' => $data['next_on'] ?? null]);
                    // A new contact closes any follow-up that was due before it.
                    ClientContact::where('client_id', $this->record->id)->whereNull('next_done_at')->whereNotNull('next_on')->where('next_on', '<=', $data['happened_on'])
                        ->update(['next_done_at' => now()]);
                    Notification::make()->title(__('Contact logged.'))->success()->send();
                }),
            // FS-16 / XS-14 (R4, D-304): COD outcomes as weekly counts, typed or from a sheet; no order IDs, no customer data.
            Action::make('codWeek')->label(__('COD week'))->icon('heroicon-o-truck')->color('gray')
                ->visible(fn () => TeamRoles::canManageData(auth()->user(), $this->record->agency))
                ->modalDescription(__('Counts only: how many orders were delivered, returned and cancelled that week. No order numbers or customer details.'))
                ->schema([
                    DatePicker::make('week_start')->label(__('Week starting'))->required()->native(false)->default(now($this->record->agency?->timezone ?: 'UTC')->startOfWeek(CarbonInterface::SUNDAY)->toDateString()),
                    Select::make('campaign_id')->label(__('Campaign (optional)'))->options(fn () => CodOutcomes::campaigns($this->record))->searchable()->placeholder(__('All campaigns')),
                    TextInput::make('delivered')->label(__('Delivered'))->numeric()->integer()->minValue(0)->required()->default(0),
                    TextInput::make('returned')->label(__('Returned'))->numeric()->integer()->minValue(0)->default(0),
                    TextInput::make('cancelled')->label(__('Cancelled'))->numeric()->integer()->minValue(0)->default(0),
                ])
                ->action(function (array $data): void {
                    CodOutcomes::save($this->record, $data['week_start'], ($data['campaign_id'] ?? null) ? (int) $data['campaign_id'] : null,
                        (int) $data['delivered'], (int) ($data['returned'] ?? 0), (int) ($data['cancelled'] ?? 0), 'manual', auth()->user());
                    Notification::make()->title(__('COD week saved.'))->success()->send();
                }),
            Action::make('codSheet')->label(__('Import COD sheet'))->icon('heroicon-o-table-cells')->color('gray')
                ->visible(fn () => TeamRoles::canManageData(auth()->user(), $this->record->agency))
                ->modalDescription(__('One row per week (and campaign, if you track it): Week start, Campaign (optional), Delivered, Returned, Cancelled. Arabic or English headers. No order numbers or customer details.'))
                ->schema([
                    FileUpload::make('file')->label(__('CSV or Excel file'))->disk('local')->directory('imports')->storeFileNamesIn('file_name')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(1024)->required(),
                ])
                ->action(function (array $data): void {
                    $path = Storage::disk('local')->path($data['file']);
                    try {
                        $r = CodOutcomes::importFile($this->record, $path, (string) ($data['file_name'] ?? $data['file']), auth()->user());
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }
                    if ($r['missing_columns']) {
                        Notification::make()->title(__('The sheet needs a week start column and a delivered column.'))->danger()->send();

                        return;
                    }
                    Notification::make()->title(__('COD weeks saved: :n', ['n' => $r['saved']]))
                        ->body(collect([
                            $r['skipped'] ? __('Rows without a valid date, skipped: :n', ['n' => $r['skipped']]) : null,
                            $r['unknown_campaigns'] ? __('Campaign names not found, kept for the whole client: :names', ['names' => implode(', ', array_slice($r['unknown_campaigns'], 0, 5))]) : null,
                        ])->filter()->implode(' · ') ?: null)->success()->send();
                }),
            // XS-10 (R4, D-303): pause a client (alerts, scheduled reports, night shift stop; data kept), one click to resume.
            Action::make('pauseClient')->label(__('Pause client'))->icon('heroicon-o-pause-circle')->color('gray')
                ->visible(fn () => $this->record->status === 'active' && TeamRoles::canManageData(auth()->user(), $this->record->agency))
                ->modalDescription(__('Alerts, scheduled reports and the night shift stop for this client. Data keeps syncing and nothing is deleted.'))
                ->schema([
                    Select::make('reason')->label(__('Reason'))->options(ClientFreeze::reasonLabels())->default('holiday')->required(),
                    DatePicker::make('until')->label(__('Resume on (optional)'))->native(false)->minDate(now()->addDay()->toDateString()),
                ])
                ->action(function (array $data): void {
                    ClientFreeze::pause($this->record, $data['reason'], $data['until'] ?? null, auth()->user());
                    Notification::make()->title(__('Client paused.'))->success()->send();
                }),
            Action::make('resumeClient')->label(__('Resume client'))->icon('heroicon-o-play-circle')
                ->visible(fn () => $this->record->status === 'paused' && TeamRoles::canManageData(auth()->user(), $this->record->agency))
                ->action(function (): void {
                    ClientFreeze::resume($this->record, auth()->user());
                    Notification::make()->title(__('Client resumed.'))->success()->send();
                }),
            // PS-13: copy this client's targets and report schedule to other clients (recipients stay per client).
            Action::make('copySetup')->label(__('Copy setup to other clients'))->icon('heroicon-o-document-duplicate')->color('gray')
                ->visible(fn () => TeamRoles::canManageData(auth()->user(), $this->record->agency))
                ->schema([
                    Select::make('to')->label(__('Clients'))->multiple()->required()
                        ->options(fn () => Client::where('agency_id', $this->record->agency_id)->whereKeyNot($this->record->id)->where('status', '!=', 'archived')->orderBy('name')->pluck('name', 'id')),
                    CheckboxList::make('parts')->label(__('What to copy'))->required()->default(['targets'])
                        ->options(['targets' => __('Targets (cost per result, results, ROAS)'), 'schedule' => __('Report schedule (recipients are not copied)')]),
                ])
                ->action(function (array $data) {
                    $n = ClientSetupCopy::copy($this->record, array_map('intval', $data['to']), $data['parts']);
                    Notification::make()->title(__('Setup copied to :n clients', ['n' => $n]))->success()->send();
                }),
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
                ->visible(fn () => PlanGate::allows($this->record->agency, 'monthly_pdf'))
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
            'lang' => $s->lang, 'recipients' => $s->recipients ?? [], 'whatsapp_recipients' => $s->whatsapp_recipients ?? [],
            'attach_pdf' => $s->attach_pdf, 'include_link' => $s->include_link, 'active' => $s->active,
        ] : ['monthly' => true, 'monthly_day' => 1, 'weekly' => false, 'weekly_day' => 7, 'lang' => $lang, 'recipients' => [],
            'whatsapp_recipients' => [], 'attach_pdf' => true, 'include_link' => true, 'active' => true];
    }

    protected function scheduleSchema(): array
    {
        $max = (int) config('reports.max_recipients', 10);

        return [
            TagsInput::make('recipients')->label(__('Recipients'))->placeholder(__('name@example.com'))
                ->helperText(__('Up to :n email addresses. Press Enter after each one.', ['n' => $max]))
                ->splitKeys([',', ' ', ';'])->rules(['array', 'max:'.$max])->nestedRecursiveRules(['email:filter', 'max:190']),
            TagsInput::make('whatsapp_recipients')->label(__('WhatsApp numbers'))->placeholder('+962790000000')
                ->helperText(__('Optional: up to :n WhatsApp numbers with country code (e.g. +962790000000).', ['n' => $max]))
                ->splitKeys([',', ' ', ';'])->rules(['array', 'max:'.$max])
                ->visible(fn () => Modules::on('whatsapp_reports')), // ADSR-110 0.2
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
        $recipients = array_values(array_unique(array_filter(array_map(fn ($e) => strtolower(trim((string) $e)), (array) ($data['recipients'] ?? [])))));
        $rawWa = Modules::on('whatsapp_reports') ? array_values(array_unique(array_filter(array_map(fn ($p) => trim((string) $p), (array) ($data['whatsapp_recipients'] ?? []))))) : [];
        $waRecipients = [];
        $badWa = false;
        foreach ($rawWa as $p) {
            if ($norm = WhatsAppReport::normalizePhone($p)) {
                $waRecipients[$norm] = $norm;
            } else {
                $badWa = true;
            }
        }
        $waRecipients = array_values($waRecipients);
        $max = (int) config('reports.max_recipients', 10);
        $bad = array_filter($recipients, fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL));
        if ((! $recipients && ! $waRecipients) || count($recipients) > $max || $bad) {
            throw ValidationException::withMessages(['mountedActions.0.data.recipients' => __('Add 1 to :n valid email addresses.', ['n' => $max])]);
        }
        if ($badWa || count($waRecipients) > $max) {
            throw ValidationException::withMessages(['mountedActions.0.data.whatsapp_recipients' => __('Enter valid WhatsApp numbers with country code (e.g. +962790000000).')]);
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
            'recipients' => $recipients, 'whatsapp_recipients' => $waRecipients, 'attach_pdf' => ! empty($data['attach_pdf']), 'include_link' => ! empty($data['include_link']),
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

    public function removeLicence(int $id): void
    {
        abort_unless(TeamRoles::canManageData(auth()->user(), $this->record->agency), 403);
        ClientLicence::where('client_id', $this->record->id)->whereKey($id)->delete();
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
            'forecastLocked' => PlanGate::lockedShown($c->agency, 'forecast'),
            'funnelLocked' => PlanGate::lockedShown($c->agency, 'funnel'),
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
            'series' => DataGaps::series($c, $from, $to),
            'cod' => CodOutcomes::summary($c, now($c->agency?->timezone ?: 'UTC')->subWeeks(8)->toDateString(), now($c->agency?->timezone ?: 'UTC')->toDateString()),
            'codWeeks' => CodWeek::with('campaign')->where('client_id', $c->id)->orderByDesc('week_start')->limit(8)->get(),
            'licences' => ClientLicence::where('client_id', $c->id)->orderBy('expires_on')->get(),
            'contacts' => ClientContact::with('user')->where('client_id', $c->id)->orderByDesc('happened_on')->orderByDesc('id')->limit(10)->get(),
            'synced' => MetricsQuery::lastSynced($c),
            'accounts' => $c->adAccounts()->with('network')->get(),
        ];
    }
}
