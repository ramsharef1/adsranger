<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\AgencyWebhook;
use App\Models\AuditLog;
use App\Models\Client;
use App\Support\CurrentClient;
use App\Support\TeamRoles;
use App\Support\Webhooks;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * #77 / #115 Outbound webhooks + read-only Looker Studio / Sheets feed. Moved out of the hidden Agency ops hub (D-229)
 * so it can be switched on by itself (module `webhooks`) once it passes the gate.
 */
class WebhooksPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'webhooks';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $slug = 'webhooks';

    protected static ?int $navigationSort = 39;

    protected string $view = 'filament.pages.webhooks';

    public ?int $clientId = null;

    public string $webhookName = '';

    public string $webhookTargetUrl = '';

    public function mount(): void
    {
        $this->clientId = CurrentClient::id(Filament::getTenant());
    }

    public static function getNavigationLabel(): string
    {
        return __('Webhooks & data feed');
    }

    public function getTitle(): string
    {
        return __('Webhooks & Looker Studio / Sheets Feed');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Create & plan');
    }

    /** The client this webhook is limited to, when one is chosen and visible to the user; null = all clients. */
    protected function selectedClient(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user || ! $this->clientId) {
            return null;
        }

        return Client::visibleTo($user, $agency)->whereKey($this->clientId)->first();
    }

    /**
     * Create an outbound webhook + read-only JSON feed. Owners and admins only. The target must be a public https
     * address (Webhooks::targetProblem). The feed URL and signing secret are shown once, in the notification.
     */
    public function createWebhook(): void
    {
        $agency = Filament::getTenant();
        abort_unless($agency && TeamRoles::canManageTeam(auth()->user(), $agency), 403);
        $name = trim($this->webhookName);
        $url = trim($this->webhookTargetUrl);
        if ($name === '' || mb_strlen($name) > 140 || $url === '') {
            Notification::make()->title(__('Enter a name and a target address.'))->danger()->send();

            return;
        }
        if ($problem = Webhooks::targetProblem($url)) {
            Notification::make()->title(__($problem))->danger()->send();

            return;
        }

        [$hook, $token, $secret] = AgencyWebhook::issue([
            'agency_id' => $agency->id,
            'client_id' => $this->selectedClient()?->id,
            'name' => $name,
            'target_url' => $url,
            'events' => Webhooks::EVENTS,
        ]);
        AuditLog::record('webhook.created', $agency, ['webhook_id' => $hook->id, 'host' => parse_url($url, PHP_URL_HOST)]);

        $this->webhookName = '';
        $this->webhookTargetUrl = '';
        Notification::make()->title(__('Webhook & Looker/Sheets feed created'))
            ->body(__('Copy these now; the signing secret is not shown again.').' '.__('Feed URL').': '.$hook->feedUrl().' · '.__('Signing secret').': '.$secret)
            ->success()->persistent()->send();
    }

    /** Switch a webhook and its feed off (owners and admins). */
    public function deactivateWebhook(int $id): void
    {
        $agency = Filament::getTenant();
        abort_unless($agency && TeamRoles::canManageTeam(auth()->user(), $agency), 403);
        $hook = AgencyWebhook::where('agency_id', $agency->id)->findOrFail($id);
        $hook->update(['is_active' => false]);
        AuditLog::record('webhook.deactivated', $agency, ['webhook_id' => $hook->id]);
    }

    public function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $clients = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->get() : collect();

        return [
            'clients' => $clients,
            'webhooks' => AgencyWebhook::where('agency_id', $agency?->id ?? 0)->latest()->limit(20)->get(),
            'canManageWebhooks' => $agency && TeamRoles::canManageTeam($user, $agency),
        ];
    }
}
