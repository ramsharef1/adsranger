<?php

namespace App\Filament\Admin\Pages;

use App\Models\Client;
use App\Models\ReachAdvertiserBilling;
use App\Models\ReachCouponCode;
use App\Models\ReachLedgerEntry;
use App\Models\ReachOffer;
use App\Models\ReachPostbackEndpoint;
use App\Models\ReachPublisher;
use App\Models\ReachWallet;
use App\Support\Reach\OfferImporter;
use App\Support\Reach\Reach;
use App\Support\Reach\TextCleanCode;
use App\Support\Reach\Wallet;
use App\Support\Seo\ToolCopy;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use UnitEnum;

/**
 * FR-REACH R-1: /admin Reach control board. Publishers (verify / reject / pause), offer import (CSV, AR or EN headers)
 * and manual entry, owned coupon codes, S2S postback endpoints, advertiser billing modes, ledger totals.
 * Super admins only, and only while the module is on for them (Modules::on). Every amount is an estimate (D-262).
 */
class ReachControlBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $slug = 'reach';

    protected static ?int $navigationSort = 94;

    protected string $view = 'filament.admin.pages.reach-control-board';

    public string $csv = '';

    /** @var array<string, mixed> */
    public array $offer = ['title' => '', 'network' => 'direct_client', 'external_id' => '', 'payout_amount' => '', 'publisher_payout_amount' => '', 'currency' => 'USD', 'offer_type' => 'cps', 'allow_incent' => false, 'coupon_code' => '', 'landing_url' => ''];

    /** @var array<string, mixed> */
    public array $code = ['offer_id' => '', 'code' => '', 'tier' => 'vip', 'publisher_ref' => ''];

    /** @var array<string, mixed> */
    public array $endpoint = ['network' => 'arabclicks', 'label' => '', 'ips' => ''];

    /** @var array<string, mixed> */
    public array $billing = ['advertiser_id' => '', 'billing_mode' => 'escrow_prepaid', 'credit_cap' => '0'];

    /** @var array{url: string, secret: string}|null shown once after an endpoint is created */
    public ?array $newEndpoint = null;

    /** @var array<string, mixed>|null */
    public ?array $importResult = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isSuperAdmin() === true && Reach::on($user);
    }

    public static function getNavigationLabel(): string
    {
        return __('reach_ui.admin.nav');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('Settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('reach_ui.admin.title');
    }

    /** On-page heading: the Latin product name sits in <bdi> inside Arabic. */
    public function getHeading(): string|Htmlable
    {
        return ToolCopy::html(__('reach_ui.admin.title'), app()->getLocale() === 'ar');
    }

    public function importCsv(): void
    {
        $this->importResult = OfferImporter::importCsv($this->csv);
        if ($this->importResult['created'] + $this->importResult['updated'] > 0) {
            $this->csv = '';
        }
        Notification::make()->title(__('reach_ui.admin.imported', ['created' => $this->importResult['created'], 'updated' => $this->importResult['updated']]))->success()->send();
    }

    public function saveOffer(): void
    {
        $saved = OfferImporter::save($this->offer);
        if (is_string($saved)) {
            Notification::make()->title(__('reach_ui.error.'.$saved))->danger()->send();

            return;
        }
        $this->offer = array_merge($this->offer, ['title' => '', 'external_id' => '', 'payout_amount' => '', 'publisher_payout_amount' => '', 'coupon_code' => '', 'landing_url' => '']);
        Notification::make()->title(__('reach_ui.admin.offer_saved'))->success()->send();
    }

    public function addCode(): void
    {
        $offer = ReachOffer::find((int) $this->code['offer_id']);
        $code = TextCleanCode::code((string) $this->code['code']);
        $tier = in_array($this->code['tier'], ReachCouponCode::TIERS, true) ? $this->code['tier'] : null;
        $publisher = trim((string) $this->code['publisher_ref']) !== '' ? ReachPublisher::where('ref', trim((string) $this->code['publisher_ref']))->first() : null;
        if (! $offer || $code === '' || ! $tier || ($tier !== 'pool' && ! $publisher)) {
            Notification::make()->title(__('reach_ui.error.bad_code'))->danger()->send();

            return;
        }
        if ($tier === 'pool' && $offer->codes()->where('tier', 'pool')->count() >= (int) config('reach.attribution.pool_max')) {
            Notification::make()->title(__('reach_ui.error.pool_full'))->danger()->send();

            return;
        }
        ReachCouponCode::updateOrCreate(['offer_id' => $offer->id, 'code' => $code], ['tier' => $tier, 'publisher_id' => $tier === 'pool' ? null : $publisher->id]);
        $this->code['code'] = '';
        Notification::make()->title(__('reach_ui.admin.code_saved'))->success()->send();
    }

    public function createEndpoint(): void
    {
        $network = in_array($this->endpoint['network'], config('reach.networks'), true) ? $this->endpoint['network'] : null;
        $label = trim((string) $this->endpoint['label']);
        $ips = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $this->endpoint['ips']) ?: []), fn ($ip) => filter_var(explode('/', $ip)[0], FILTER_VALIDATE_IP)));
        if (! $network || $label === '') {
            Notification::make()->title(__('reach_ui.error.bad_endpoint'))->danger()->send();

            return;
        }
        [, $token, $secret] = ReachPostbackEndpoint::issue($network, mb_substr($label, 0, 120), $ips, auth()->user());
        $this->newEndpoint = ['url' => url('/reach/v1/postback/'.$token), 'secret' => $secret];
        $this->endpoint = ['network' => $network, 'label' => '', 'ips' => ''];
    }

    /** @var array<int, array{subid: string, conversion_id: string, payout: string, status: string, offer_id: string}> R-4 per-endpoint overrides */
    public array $overrides = [];

    /** R-4: overrides the network preset for one endpoint (empty field = keep the preset). */
    public function saveOverride(int $id): void
    {
        $e = ReachPostbackEndpoint::findOrFail($id);
        $row = (array) ($this->overrides[$id] ?? []);
        $clean = fn ($v) => preg_match('/^[A-Za-z0-9_\-]{1,32}$/', trim((string) $v)) ? trim((string) $v) : null;
        $map = [];
        foreach (['conversion_id', 'payout', 'status', 'offer_id'] as $field) {
            if ($v = $clean($row[$field] ?? '')) {
                $map[$field] = $v;
            }
        }
        $e->update(['subid_param' => $clean($row['subid'] ?? ''), 'param_map' => $map ?: null]);
        Notification::make()->title(__('reach_ui.admin.override_saved'))->success()->send();
    }

    public function toggleEndpoint(int $id): void
    {
        $e = ReachPostbackEndpoint::findOrFail($id);
        $e->update(['active' => ! $e->active]);
    }

    public function setPublisherStatus(int $id, string $status): void
    {
        abort_unless(in_array($status, ReachPublisher::STATUSES, true), 422);
        ReachPublisher::findOrFail($id)->update(['status' => $status]);
        Notification::make()->title(__('reach_ui.admin.status_saved'))->success()->send();
    }

    public function saveBilling(): void
    {
        $client = Client::find((int) $this->billing['advertiser_id']);
        $mode = in_array($this->billing['billing_mode'], config('reach.billing_modes'), true) ? $this->billing['billing_mode'] : null;
        $cap = OfferImporter::number($this->billing['credit_cap']) ?? 0.0;
        if (! $client || ! $mode || $cap < 0) {
            Notification::make()->title(__('reach_ui.error.bad_billing'))->danger()->send();

            return;
        }
        ReachAdvertiserBilling::updateOrCreate(['advertiser_id' => $client->id], [
            'billing_mode' => $mode, 'credit_cap' => $mode === 'escrow_prepaid' ? 0 : Reach::amount($cap),
            'currency' => $client->currency ?: 'USD', 'updated_by' => auth()->id(),
        ]);
        Notification::make()->title(__('reach_ui.admin.billing_saved'))->success()->send();
    }

    public function clearDue(): void
    {
        $n = Wallet::clearDue();
        Notification::make()->title(trans_choice('reach_ui.admin.cleared', $n, ['count' => $n]))->success()->send();
    }

    protected function getViewData(): array
    {
        return [
            'statusCounts' => ReachPublisher::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all(),
            'publishers' => ReachPublisher::latest('id')->limit(50)->get(),
            'offers' => ReachOffer::latest('id')->limit(50)->withCount('codes')->get(),
            'endpoints' => ReachPostbackEndpoint::latest('id')->get(),
            'billings' => ReachAdvertiserBilling::with('advertiser:id,name,currency')->latest('id')->limit(50)->get(),
            'clients' => Client::orderBy('name')->limit(300)->get(['id', 'name']),
            'totals' => [
                'pending' => (float) ReachWallet::sum('pending_balance'),
                'cleared' => (float) ReachWallet::sum('cleared_balance'),
                'reserve' => (float) ReachWallet::sum('reserve_balance'),
                'conversions' => ReachLedgerEntry::where('conversion_type', 'conversion')->count(),
                'unattributed' => (int) Cache::get('reach:unattributed:'.now()->toDateString(), 0),
            ],
        ];
    }
}
