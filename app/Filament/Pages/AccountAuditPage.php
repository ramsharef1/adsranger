<?php

namespace App\Filament\Pages;

use App\Audit\AccountAudit;
use App\Filament\Concerns\FollowsCurrentClient;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Models\AuditFinding;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\CurrentClient;
use App\Support\PlanGate;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\RateLimiter;
use UnitEnum;

/**
 * #173 Account audit (FR-016, D-135). Shows a client's latest audit: up to 10 findings ranked by money at risk, each with
 * its fix and a link to the network's own ad manager. Starter sees the top 3 (EX-50 locked state for the rest).
 * Owners, admins and managers run it again or dismiss findings; analysts view. Read-only toward the networks.
 */
class AccountAuditPage extends Page
{
    use FollowsCurrentClient;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|UnitEnum|null $navigationGroup = 'Results';

    protected static ?string $navigationLabel = 'Account audit';

    protected static ?string $title = 'Account audit';

    protected static ?string $slug = 'account-audit';

    protected static ?int $navigationSort = 13;

    protected string $view = 'filament.pages.account-audit';

    public $clientId = null;

    public bool $showDismissed = false;

    public function mount(): void
    {
        $this->clientId = CurrentClient::id() ?: $this->clients()->first()?->id;
    }

    protected function clients()
    {
        return Client::visibleTo(auth()->user(), Filament::getTenant())->orderBy('name')->get();
    }

    protected function client(): ?Client
    {
        $id = (int) $this->clientId;

        return $id ? $this->clients()->firstWhere('id', $id) : null;
    }

    protected function canManage(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    public function runAudit(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        $key = 'audit-run:'.$client->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            Notification::make()->title(__('You can run the audit again in a few minutes.'))->warning()->send();

            return;
        }
        RateLimiter::hit($key, 600);
        $audit = AccountAudit::run($client, 'manual', auth()->user());
        AuditLog::record('account_audit.run', Filament::getTenant(), ['client_id' => $client->id, 'audit_id' => $audit->id, 'findings' => $audit->findings_count]);
        Notification::make()->title(__('Audit finished'))->success()->send();
    }

    public function dismiss(int $findingId): void
    {
        $this->setDismissed($findingId, true);
    }

    public function restore(int $findingId): void
    {
        $this->setDismissed($findingId, false);
    }

    protected function setDismissed(int $findingId, bool $dismiss): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManage()) {
            abort(403);
        }
        $f = AuditFinding::where('client_id', $client->id)->where('agency_id', Filament::getTenant()->id)->findOrFail($findingId);
        $visible = PlanGate::allows(Filament::getTenant(), 'account_audit_full') ? PHP_INT_MAX : (int) config('audit.starter_visible');
        if ($f->rank > $visible) {
            abort(403);
        }
        $f->update(['dismissed_at' => $dismiss ? now() : null, 'dismissed_by' => $dismiss ? auth()->id() : null]);
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $client = $this->client();
        $audit = $client ? AccountAudit::latest($client) : null;
        $full = PlanGate::allows($agency, 'account_audit_full');
        $visible = $full ? PHP_INT_MAX : (int) config('audit.starter_visible');
        $findings = $audit ? $audit->findings()->get() : collect();

        return [
            'clients' => $this->clients(), 'client' => $client, 'audit' => $audit,
            'shown' => $findings->filter(fn ($f) => $f->rank <= $visible)->values(),
            'hiddenCount' => $findings->filter(fn ($f) => $f->rank > $visible)->count(),
            'canManage' => $this->canManage(), 'canUpgrade' => PlanGate::canUpgrade(auth()->user(), $agency),
            'hasAccounts' => $client ? AccountAudit::allowedAccounts($client)->isNotEmpty() : false,
        ];
    }
}
