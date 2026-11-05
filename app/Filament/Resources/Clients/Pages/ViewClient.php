<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\AdSet;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\ClientLink;
use App\Support\HealthScore;
use App\Support\MetricsQuery;
use App\Support\MonthlyReport;
use App\Support\Pacing;
use App\Support\Periods;
use App\Support\PlanGate;
use App\Support\TeamRoles;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

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
            EditAction::make()->label(__('Settings & targets')),
        ];
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
