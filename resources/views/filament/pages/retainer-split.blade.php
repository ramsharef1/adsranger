<x-filament-panels::page>
@php
    $m = fn (?float $v) => \App\Support\ResultResolver::money($v, $split['currency'] ?? null);
    $models = ['fixed' => __('Fixed monthly fee'), 'percent_spend' => __('Share of ad spend'), 'hybrid' => __('Fixed fee plus a share of ad spend')];
@endphp
<style>
.rs-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
.rs-in{width:100%;min-height:44px;border-radius:8px;border:1px solid rgba(148,163,184,.5);padding:8px 12px;background:transparent;color:inherit}
.rs-btn{min-height:44px;padding:8px 16px;border-radius:8px;background:#0f766e;color:#fff;font-weight:600;border:0;cursor:pointer}
.rs-btn.ghost{background:transparent;color:inherit;border:1px solid rgba(148,163,184,.5)}
.rs-muted{font-size:13px;opacity:.75;line-height:1.6}
.rs-row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid rgba(148,163,184,.2);font-size:14px}
.rs-row strong{white-space:nowrap}
.rs-note{padding:12px 14px;border-radius:12px;border:1px solid rgba(148,163,184,.35);background:rgba(148,163,184,.08);font-size:13.5px;line-height:1.65}
</style>

<div class="rs-note" role="note">{{ $ar ? \App\Support\AgencyOps\RetainerSplit::REMINDER_AR : \App\Support\AgencyOps\RetainerSplit::REMINDER_EN }}</div>

<x-filament::section>
    <x-slot name="heading">{{ __('Your billing details') }}</x-slot>
    @if ($canManageProfile)
        <div class="rs-grid">
            <label class="rs-muted">{{ __('Legal name') }}<input type="text" wire:model="legalName" class="rs-in" maxlength="190"></label>
            <label class="rs-muted">{{ __('Tax number') }}<input type="text" wire:model="taxId" class="rs-in" dir="ltr" maxlength="40"></label>
            <label class="rs-muted">{{ __('Default currency') }}<input type="text" wire:model="defaultCurrency" class="rs-in" dir="ltr" maxlength="3"></label>
            <label class="rs-muted">{{ __('Country code') }}<input type="text" wire:model="countryCode" class="rs-in" dir="ltr" maxlength="2" placeholder="SA"></label>
        </div>
        @foreach (['legalName', 'taxId', 'defaultCurrency', 'countryCode'] as $f)@error($f)<p style="color:#b91c1c;font-size:13px">{{ $message }}</p>@enderror @endforeach
        <button type="button" class="rs-btn" style="margin-top:10px" wire:click="saveProfile">{{ __('Save') }}</button>
    @elseif ($profile)
        <p class="rs-muted">{{ $profile->legal_name }}@if ($profile->tax_identifier) · <bdi dir="ltr">{{ $profile->tax_identifier }}</bdi>@endif</p>
    @else
        <p class="rs-muted">{{ __('The workspace owner or an admin adds the billing details.') }}</p>
    @endif
</x-filament::section>

<x-filament::section>
    <x-slot name="heading">{{ __('Client fee terms') }}</x-slot>
    <div class="rs-grid">
        <label class="rs-muted">{{ __('Client') }}<select wire:model.live="clientId" class="rs-in">@foreach ($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></label>
        <label class="rs-muted">{{ __('Month') }}<input type="month" wire:model.live="month" class="rs-in" dir="ltr"></label>
    </div>
    @if ($client && $canManageTerms)
        <div class="rs-grid" style="margin-top:10px">
            <label class="rs-muted">{{ __('Fee model') }}<select wire:model="feeModel" class="rs-in">@foreach ($models as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></label>
            <label class="rs-muted">{{ __('Fixed fee') }} (<bdi dir="ltr">{{ $client->currency }}</bdi>)<input type="number" step="0.001" min="0" wire:model="fixedAmount" class="rs-in" dir="ltr"></label>
            <label class="rs-muted">{{ __('Share of ad spend %') }}<input type="number" step="0.01" min="0" max="100" wire:model="spendPercent" class="rs-in" dir="ltr"></label>
            <label class="rs-muted">{{ __('Minimum fee') }} (<bdi dir="ltr">{{ $client->currency }}</bdi>)<input type="number" step="0.001" min="0" wire:model="minFee" class="rs-in" dir="ltr"></label>
            <label class="rs-muted">{{ __('Tax rate on the fee % (leave empty if none)') }}<input type="number" step="0.01" min="0" max="100" wire:model="taxRate" class="rs-in" dir="ltr"></label>
        </div>
        <button type="button" class="rs-btn" style="margin-top:10px" wire:click="saveTerms">{{ __('Save') }}</button>
    @endif

    @if ($split)
        <div style="margin-top:14px">
            <div class="rs-row"><span>{{ __('Pass-through ad spend') }}</span><strong><bdi dir="ltr">{{ $m($split['spend']) }}</bdi></strong></div>
            <div class="rs-row"><span>{{ __('Agency management fee') }} · {{ $models[$split['fee_model']] ?? $split['fee_model'] }}@if ($split['min_applied']) · {{ __('minimum fee applied') }}@endif</span><strong><bdi dir="ltr">{{ $m($split['fee']) }}</bdi></strong></div>
            @if ($split['tax_rate'] !== null)
                <div class="rs-row"><span>{{ __('Tax on the fee (:rate%)', ['rate' => number_format($split['tax_rate'], 2)]) }}</span><strong><bdi dir="ltr">{{ $m($split['tax']) }}</bdi></strong></div>
            @endif
            <div class="rs-row"><span>{{ __('Total for the month') }}</span><strong><bdi dir="ltr">{{ $m($split['total']) }}</bdi></strong></div>
            <p class="rs-muted">{{ __('Ad spend comes from the synced and imported data for the month, in the client\'s currency. Tax is applied to the fee only, at the rate you entered.') }}</p>
        </div>
        @if ($client?->hide_spend)
            <p class="rs-muted">{{ __('Spend is hidden from this client, so no invoice draft is exported.') }}</p>
        @elseif (! $profile)
            <p class="rs-muted">{{ __('Add your billing details to export drafts.') }}</p>
        @elseif ($canExport)
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px">
                <button type="button" class="rs-btn ghost" wire:click="downloadCsv">{{ __('Download CSV') }}</button>
                <button type="button" class="rs-btn ghost" wire:click="downloadZatcaTlv">{{ __('ZATCA QR data (TLV, draft)') }}</button>
                <button type="button" class="rs-btn ghost" wire:click="downloadUbl">{{ __('JoFotara UBL XML (draft)') }}</button>
            </div>
        @endif
    @elseif ($client)
        <p class="rs-muted" style="margin-top:12px">{{ __('No fee terms for this client yet.') }}</p>
    @endif
</x-filament::section>
</x-filament-panels::page>
