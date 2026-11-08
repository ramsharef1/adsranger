<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\Client;
use App\Models\ClientOnboardingLink;
use App\Support\CurrentClient;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

/**
 * #105 Shareable client onboarding & access request link (/onboard/{token}). Moved out of the hidden Agency ops hub
 * (D-229) so it can be switched on by itself (module `client_onboarding`). It is not the #62 prospect audit (#173b).
 */
class ClientOnboardingLinksPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'client_onboarding';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $slug = 'client-onboarding';

    protected static ?int $navigationSort = 37;

    protected string $view = 'filament.pages.client-onboarding-links';

    public ?int $clientId = null;

    public string $prospectName = '';

    public string $prospectWebsite = '';

    public function mount(): void
    {
        $this->clientId = CurrentClient::id(Filament::getTenant());
    }

    public static function getNavigationLabel(): string
    {
        return __('Client onboarding links');
    }

    public function getTitle(): string
    {
        return __('Client Onboarding & Access Link');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Create & plan');
    }

    protected function selectedClient(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user || ! $this->clientId) {
            return null;
        }

        return Client::visibleTo($user, $agency)->whereKey($this->clientId)->first();
    }

    public function createOnboardingLink(): void
    {
        $agency = Filament::getTenant();
        if (! $agency) {
            return;
        }
        $client = $this->selectedClient();
        $name = trim($this->prospectName) !== '' ? trim($this->prospectName) : ($client?->name ?? '');
        if ($name === '') {
            return;
        }

        ClientOnboardingLink::create([
            'agency_id' => $agency->id,
            'client_id' => $client?->id,
            'token' => Str::random(40),
            'prospect_name' => $name,
            'website_url' => trim($this->prospectWebsite) !== '' ? trim($this->prospectWebsite) : null,
            'requested_networks' => ['meta', 'google_ads', 'ga4', 'tiktok', 'snapchat'],
            'status' => 'pending',
        ]);

        $this->prospectName = '';
        $this->prospectWebsite = '';
        Notification::make()->title(__('Client onboarding link created'))->success()->send();
    }

    public function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();

        return [
            'clients' => $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->get() : collect(),
            'onboardingLinks' => ClientOnboardingLink::where('agency_id', $agency?->id ?? 0)->latest()->limit(20)->get(),
        ];
    }
}
