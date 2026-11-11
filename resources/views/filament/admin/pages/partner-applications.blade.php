@php
    $arCount = $entries->where('lang', 'ar')->count();
    $enCount = $entries->where('lang', 'en')->count();
    $pubCount = $entries->where('is_published', true)->count();
@endphp
<x-filament-panels::page>
    @if ($issuedMagicUrl)
        <x-filament::section>
            <x-slot name="heading">{{ __('7-day magic setup link ready') }}</x-slot>
            <x-slot name="description"><bdi dir="ltr">{{ $issuedForEmail }}</bdi></x-slot>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
                <input id="pa-magic-url" type="text" readonly dir="ltr" value="{{ $issuedMagicUrl }}" style="flex:1;min-width:260px;min-height:44px;padding:8px 12px;border-radius:8px;border:1px solid #14b8a6;background:transparent;font-family:monospace;font-size:13px">
                <button type="button" onclick="navigator.clipboard?.writeText(document.getElementById('pa-magic-url').value)" style="min-height:44px;padding:0 16px;border-radius:8px;border:0;background:#0f766e;color:#fff;font-weight:600;cursor:pointer">{{ __('Copy 7-day login link') }}</button>
            </div>
        </x-filament::section>
    @endif

    @if ($activeEntryId && $modalType === 'onboarding')
        <x-filament::section>
            <x-slot name="heading">{{ __('Invite / Provision Partner Workspace') }}</x-slot>
            <x-slot name="description">{{ __('Choose onboarding mode, plan tier, and trial length.') }}</x-slot>
            <form wire:submit="submitOnboardingModal" style="display:grid;gap:14px;max-width:520px">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">{{ __('Delivery method') }}</label>
                    <select wire:model="onboardingMode" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                        <option value="invite">{{ __('Send Email Invite') }}</option>
                        <option value="provision">{{ __('Provision Workspace & Copy Login Link') }}</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">{{ __('Plan tier') }}</label>
                    <select wire:model="selectedPlan" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                        @foreach ($planOptions as $k => $lbl)
                            <option value="{{ $k }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">{{ __('Trial length') }}</label>
                    <select wire:model="selectedTrial" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                        @foreach ($trialOptions as $k => $lbl)
                            <option value="{{ $k }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button type="submit" style="min-height:44px;padding:0 18px;border-radius:8px;border:0;background:#0f766e;color:#fff;font-weight:600;cursor:pointer">{{ __('Confirm') }}</button>
                    <button type="button" wire:click="closeModal" style="min-height:44px;padding:0 16px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;cursor:pointer">{{ __('Cancel') }}</button>
                </div>
            </form>
        </x-filament::section>
    @endif

    @if ($activeEntryId && $modalType === 'directory')
        <x-filament::section>
            <x-slot name="heading">{{ __('Publish to /partners Directory') }}</x-slot>
            <x-slot name="description">{{ __('Only admin-approved partner records are displayed publicly on /partners and /en/partners.') }}</x-slot>
            <form wire:submit="submitDirectoryModal" style="display:grid;gap:14px;max-width:520px">
                <label style="display:flex;gap:10px;align-items:center;min-height:44px;font-weight:600">
                    <input type="checkbox" wire:model="publishToDirectory" style="width:20px;height:20px">
                    {{ __('Show this agency in the public /partners directory') }}
                </label>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">{{ __('Partner badge') }}</label>
                    <select wire:model="selectedBadge" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                        @foreach ($badgeOptions as $k => $lbl)
                            <option value="{{ $k }}">{{ __($lbl) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <button type="submit" style="min-height:44px;padding:0 18px;border-radius:8px;border:0;background:#0f766e;color:#fff;font-weight:600;cursor:pointer">{{ __('Save directory visibility') }}</button>
                    <button type="button" wire:click="closeModal" style="min-height:44px;padding:0 16px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;cursor:pointer">{{ __('Cancel') }}</button>
                </div>
            </form>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">{{ __('Partner applications') }} ({{ $total }})</x-slot>
        <x-slot name="description">{{ __('Agencies that applied via /partners or /en/partners.') }}</x-slot>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;font-size:13px">
            <span style="padding:4px 10px;border-radius:99px;border:1px solid rgba(148,163,184,.25);font-weight:600">{{ __('Total') }}: {{ $total }}</span>
            <span style="padding:4px 10px;border-radius:99px;border:1px solid rgba(148,163,184,.25)">{{ __('Published') }}: {{ $pubCount }}</span>
            <span style="padding:4px 10px;border-radius:99px;border:1px solid rgba(148,163,184,.25)">AR: {{ $arCount }} · EN: {{ $enCount }}</span>
        </div>

        @if ($entries->isEmpty())
            <p style="opacity:.7;font-size:14px">{{ __('No partner applications yet.') }}</p>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <thead>
                        <tr style="text-align:start;border-bottom:1px solid rgba(148,163,184,.25)">
                            <th style="padding:10px 8px">{{ __('Agency') }}</th>
                            <th style="padding:10px 8px">{{ __('Email') }}</th>
                            <th style="padding:10px 8px">{{ __('Country') }}</th>
                            <th style="padding:10px 8px">{{ __('Clients') }}</th>
                            <th style="padding:10px 8px">{{ __('Directory') }}</th>
                            <th style="padding:10px 8px">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $e)
                            <tr style="border-top:1px solid rgba(148,163,184,.18)">
                                <td style="padding:10px 8px;font-weight:600">{{ $e->agency_name }}</td>
                                <td style="padding:10px 8px"><bdi dir="ltr">{{ $e->email }}</bdi></td>
                                <td style="padding:10px 8px"><bdi dir="ltr">{{ $e->country }}</bdi></td>
                                <td style="padding:10px 8px"><bdi dir="ltr">{{ $e->clients_range }}</bdi></td>
                                <td style="padding:10px 8px">
                                    @if ($e->is_published)
                                        <span style="display:inline-block;padding:2px 8px;border-radius:6px;background:rgba(16,185,129,.15);font-size:12.5px;font-weight:600">
                                            {{ __(\App\Models\PartnerApplication::BADGES[$e->partner_badge] ?? 'Certified Partner') }}
                                        </span>
                                    @else
                                        <span style="opacity:.65;font-size:12.5px">{{ __('Hidden') }}</span>
                                    @endif
                                </td>
                                <td style="padding:10px 8px">
                                    <div style="display:flex;flex-wrap:wrap;gap:6px">
                                        <button type="button" wire:click="openOnboardingModal({{ $e->id }}, 'provision')" style="min-height:44px;padding:0 12px;border-radius:8px;border:0;background:#0f766e;color:#fff;font-size:12.5px;font-weight:600;cursor:pointer">{{ __('Invite / Provision Workspace') }}</button>
                                        <button type="button" wire:click="openDirectoryModal({{ $e->id }})" style="min-height:44px;padding:0 12px;border-radius:8px;border:1px solid #0f766e;background:transparent;font-size:12.5px;font-weight:600;cursor:pointer">{{ __('Publish to /partners Directory') }}</button>
                                        @if ($e->provisioned_agency_id)
                                            <button type="button" wire:click="regenerateMagicLink({{ $e->id }})" style="min-height:44px;padding:0 12px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent;font-size:12.5px;cursor:pointer">{{ __('Regenerate 7-Day Link') }}</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
