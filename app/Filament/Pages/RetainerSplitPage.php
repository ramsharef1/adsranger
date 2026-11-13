<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HeldBackModule;
use App\Models\AgencyBillingProfile;
use App\Models\Client;
use App\Models\ClientRetainerTerm;
use App\Support\AgencyOps\RetainerSplit;
use App\Support\CurrentClient;
use App\Support\TeamRoles;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * E2-067 retainer vs ad-spend split (module agency_ops_hub, off). Owners and admins set the billing profile; owners,
 * admins and managers set a client's fee terms and download the drafts; analysts view. A client with hide_spend gets
 * no export (an invoice draft would show the spend the agency hides from them).
 */
class RetainerSplitPage extends Page
{
    use HeldBackModule;

    protected static string $module = 'agency_ops_hub';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $slug = 'retainer-split';

    protected static ?int $navigationSort = 39;

    protected string $view = 'filament.pages.retainer-split';

    public ?int $clientId = null;

    public string $month = '';

    public string $legalName = '';

    public string $taxId = '';

    public string $defaultCurrency = 'SAR';

    public string $countryCode = '';

    public string $feeModel = 'hybrid';

    public ?float $fixedAmount = 0;

    public ?float $spendPercent = 0;

    public ?float $minFee = 0;

    public ?float $taxRate = null;

    public static function getNavigationLabel(): string
    {
        return __('Retainer and ad-spend split');
    }

    public function getTitle(): string
    {
        return __('Retainer and ad-spend split');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Create & plan');
    }

    public function mount(): void
    {
        $agency = Filament::getTenant();
        $this->month = now($agency?->timezone ?: 'UTC')->subMonthNoOverflow()->format('Y-m');
        $this->clientId = $this->client()?->id;
        if ($profile = $this->profile()) {
            $this->legalName = $profile->legal_name;
            $this->taxId = (string) $profile->tax_identifier;
            $this->defaultCurrency = $profile->default_currency;
            $this->countryCode = (string) $profile->country_code;
        } else {
            $this->legalName = (string) ($agency?->name ?? '');
            $this->defaultCurrency = strtoupper((string) ($agency?->currency ?: 'SAR'));
            $this->countryCode = strtoupper((string) ($agency?->country ?? ''));
        }
        $this->loadTerms();
    }

    public function updatedClientId(): void
    {
        $this->loadTerms();
    }

    protected function client(): ?Client
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        if (! $agency || ! $user) {
            return null;
        }
        $q = Client::visibleTo($user, $agency);
        if ($this->clientId) {
            return (clone $q)->whereKey($this->clientId)->first();
        }
        $current = CurrentClient::resolve();

        return ($current ? (clone $q)->whereKey($current->id)->first() : null) ?? $q->orderBy('name')->first();
    }

    protected function profile(): ?AgencyBillingProfile
    {
        $agency = Filament::getTenant();

        return $agency ? AgencyBillingProfile::where('agency_id', $agency->getKey())->first() : null;
    }

    protected function terms(): ?ClientRetainerTerm
    {
        $client = $this->client();

        return $client ? ClientRetainerTerm::where('agency_id', $client->agency_id)->where('client_id', $client->id)->first() : null;
    }

    protected function loadTerms(): void
    {
        $t = $this->terms();
        $this->feeModel = $t->fee_model ?? 'hybrid';
        $this->fixedAmount = $t->fixed_amount ?? 0;
        $this->spendPercent = $t->spend_percent ?? 0;
        $this->minFee = $t->min_fee ?? 0;
        $this->taxRate = $t?->tax_rate_percent;
    }

    protected function canManageTerms(): bool
    {
        return TeamRoles::canManageData(auth()->user(), Filament::getTenant());
    }

    protected function canManageProfile(): bool
    {
        return TeamRoles::canManageTeam(auth()->user(), Filament::getTenant());
    }

    public function saveProfile(): void
    {
        $agency = Filament::getTenant();
        if (! $agency || ! $this->canManageProfile()) {
            return;
        }
        $this->validate([
            'legalName' => ['required', 'string', 'max:190'],
            'taxId' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\-]*$/'],
            'defaultCurrency' => ['required', 'string', 'size:3', 'alpha'],
            'countryCode' => ['nullable', 'string', 'size:2', 'alpha'],
        ]);
        AgencyBillingProfile::updateOrCreate(['agency_id' => $agency->getKey()], [
            'legal_name' => trim($this->legalName), 'tax_identifier' => trim($this->taxId) ?: null,
            'default_currency' => strtoupper($this->defaultCurrency), 'country_code' => strtoupper(trim($this->countryCode)) ?: null,
        ]);
        Notification::make()->title(__('Saved'))->success()->send();
    }

    public function saveTerms(): void
    {
        $client = $this->client();
        if (! $client || ! $this->canManageTerms()) {
            return;
        }
        $this->validate([
            'feeModel' => ['required', 'in:'.implode(',', ClientRetainerTerm::FEE_MODELS)],
            'fixedAmount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'spendPercent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minFee' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'taxRate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        ClientRetainerTerm::updateOrCreate(['agency_id' => $client->agency_id, 'client_id' => $client->id], [
            'fee_model' => $this->feeModel, 'fixed_amount' => (float) $this->fixedAmount, 'spend_percent' => (float) $this->spendPercent,
            'min_fee' => (float) $this->minFee, 'tax_rate_percent' => $this->taxRate === null || $this->taxRate === '' ? null : (float) $this->taxRate,
            // The split reads spend in the client's own currency, so the terms use it too (no conversion is invented).
            'currency' => strtoupper((string) ($client->currency ?: 'USD')),
        ]);
        Notification::make()->title(__('Saved'))->success()->send();
    }

    /** @return array{0: Client, 1: ClientRetainerTerm, 2: AgencyBillingProfile, 3: array}|null */
    protected function exportable(): ?array
    {
        $client = $this->client();
        $terms = $this->terms();
        $profile = $this->profile();
        if (! $client || ! $terms || ! $profile || ! $this->canManageTerms() || $client->hide_spend || ! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            return null;
        }

        return [$client, $terms, $profile, RetainerSplit::compute($client, $terms, $this->month)];
    }

    protected function fileName(Client $client, string $ext): string
    {
        return 'retainer-split-'.(Str::slug($client->name) ?: 'client-'.$client->id).'-'.$this->month.'.'.$ext;
    }

    public function downloadCsv(): ?StreamedResponse
    {
        if (! $x = $this->exportable()) {
            return null;
        }
        [$client, , , $split] = $x;
        $body = RetainerSplit::csv($client, $split, app()->getLocale() === 'ar');

        return response()->streamDownload(fn () => print ($body), $this->fileName($client, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function downloadZatcaTlv(): ?StreamedResponse
    {
        if (! $x = $this->exportable()) {
            return null;
        }
        [$client, , $profile, $split] = $x;
        $tlv = RetainerSplit::zatcaTlv($profile, $split, now());
        $body = RetainerSplit::REMINDER_EN."\n".RetainerSplit::REMINDER_AR."\n\nTLV (Base64):\n".$tlv."\n";

        return response()->streamDownload(fn () => print ($body), $this->fileName($client, 'tlv.txt'), ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function downloadUbl(): ?StreamedResponse
    {
        if (! $x = $this->exportable()) {
            return null;
        }
        [$client, , $profile, $split] = $x;
        $xml = RetainerSplit::ublXml($profile, $client, $split, 'DRAFT-'.$client->id.'-'.str_replace('-', '', $this->month), now());

        return response()->streamDownload(fn () => print ($xml), $this->fileName($client, 'xml'), ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    protected function getViewData(): array
    {
        $agency = Filament::getTenant();
        $user = auth()->user();
        $client = $this->client();
        $terms = $this->terms();

        return [
            'ar' => app()->getLocale() === 'ar',
            'clients' => $agency && $user ? Client::visibleTo($user, $agency)->orderBy('name')->get() : collect(),
            'client' => $client,
            'profile' => $this->profile(),
            'terms' => $terms,
            'split' => $client && $terms && preg_match('/^\d{4}-\d{2}$/', $this->month) ? RetainerSplit::compute($client, $terms, $this->month) : null,
            'canManageTerms' => $this->canManageTerms(),
            'canManageProfile' => $this->canManageProfile(),
            'canExport' => $this->exportable() !== null,
        ];
    }
}
