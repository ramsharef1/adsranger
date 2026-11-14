<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\Client;
use App\Models\OfflineConversionDispatch;
use App\Support\Attribution\OfflineConversionDispatcher;
use App\Support\CurrentClient;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OfflineConversionsPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'lead_desk';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $slug = 'offline-conversions';

    protected static ?int $navigationSort = 42;

    protected string $view = 'filament.pages.offline-conversions';

    public ?int $clientId = null;

    public string $leadIdentifier = '';

    public string $dealStage = 'won';

    public float $dealValue = 0.0;

    public string $ctwaClid = '';

    public string $gclid = '';

    public string $phone = '';

    public string $email = '';

    public string $targetNetwork = 'meta_capi';

    public function mount(): void
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $first = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->first() : null;
        $this->clientId = CurrentClient::id($agency) ?: $first?->id;
    }

    public static function getNavigationLabel(): string
    {
        return __('Offline conversion loop');
    }

    public function getTitle(): string
    {
        return __('WhatsApp & CRM offline conversion loop');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Create & plan');
    }

    protected function resolveSelectedClient(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user) {
            return null;
        }
        $q = Client::visibleTo($user, $agency);

        return $this->clientId ? $q->whereKey($this->clientId)->first() : $q->orderBy('name')->first();
    }

    protected function canManage(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    public function recordOfflineDeal(): void
    {
        $client = $this->resolveSelectedClient();
        if (! $client || ! $this->canManage() || trim($this->leadIdentifier) === '') {
            return;
        }

        $row = OfflineConversionDispatcher::queueOrSend($client, [
            'lead_identifier' => $this->leadIdentifier,
            'deal_stage' => $this->dealStage,
            'deal_value' => $this->dealValue,
            'currency' => $client->currency ?: 'SAR',
            'ctwa_clid' => $this->ctwaClid,
            'gclid' => $this->gclid,
            'phone' => $this->phone,
            'email' => $this->email,
            'target_network' => $this->targetNetwork,
        ]);

        $this->reset(['leadIdentifier', 'ctwaClid', 'gclid', 'phone', 'email']);
        Notification::make()->title(
            $row->status === 'sent'
                ? __('Offline conversion sent to network.')
                : __('Queued until live API credentials are connected.')
        )->success()->send();
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $clients = $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->get() : collect();
        $client = $this->resolveSelectedClient();

        return [
            'clients' => $clients,
            'client' => $client,
            'canManage' => $this->canManage(),
            'dispatches' => $client
                ? OfflineConversionDispatch::where('agency_id', $client->agency_id)
                    ->where('client_id', $client->id)
                    ->latest('id')
                    ->limit(25)
                    ->get()
                : collect(),
        ];
    }
}
