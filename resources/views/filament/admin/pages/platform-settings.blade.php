@php
    $modules = (array) config('modules.enabled', []);
    $previewAgencies = (array) config('modules.preview_agencies', []);
    $currentModels = $aiProviders[$aiProvider]['models'] ?? [];
@endphp
<x-filament-panels::page>
    @if ($pendingUpgrades->isNotEmpty() || $pendingEarlyAccess->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">{{ __('Pending Workspace Requests') }}</x-slot>
            <x-slot name="description">{{ __('Upgrade and Beta Early Access requests submitted from Settings → Plan & usage.') }}</x-slot>
            <div style="display:grid;gap:8px;font-size:13.5px">
                @foreach ($pendingUpgrades as $uReq)
                    <div style="padding:8px 12px;border-radius:8px;background:rgba(20,184,166,.12)">
                        <strong>{{ $uReq->agency?->name }}</strong>:
                        <bdi dir="ltr">{{ $uReq->current_plan }} → {{ $uReq->target_plan }}</bdi>
                        @if ($uReq->note) · {{ $uReq->note }} @endif
                    </div>
                @endforeach
                @foreach ($pendingEarlyAccess as $mReq)
                    <div style="padding:8px 12px;border-radius:8px;background:rgba(148,163,184,.15)">
                        <strong>{{ $mReq->agency?->name }}</strong> · {{ __('Early access requested') }}:
                        <bdi dir="ltr">{{ $mReq->module_key }}</bdi>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    <form wire:submit="save" style="display:grid;gap:16px">
        <x-filament::section>
            <x-slot name="heading">{{ __('Self-signup') }}</x-slot>
            <x-slot name="description">{{ __('Off: /app/register shows the invite-only message and creates nothing. On: anyone can sign up for a 14-day Pro trial after confirming their email.') }}</x-slot>
            <label style="display:flex;gap:10px;align-items:center;min-height:44px;font-weight:600">
                <input type="checkbox" wire:model="signupOpen" style="width:20px;height:20px">
                {{ __('Open self-signup (signup.open)') }}
            </label>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('Trial domain allowlist') }}</x-slot>
            <x-slot name="description">{{ __('One trial per business email domain. Domains listed here may start more than one trial. One domain per line.') }}</x-slot>
            <textarea wire:model="allowlist" rows="5" dir="ltr" style="width:100%;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:14px;font-family:monospace"></textarea>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ __('AI Provider & Encrypted Credentials') }}</x-slot>
            <x-slot name="description">{{ __('Select the active AI provider and model. API keys are encrypted at rest; when unconfigured, workspaces use honest deterministic fallback.') }}</x-slot>
            <div style="display:grid;gap:12px;max-width:560px">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">{{ __('AI provider') }}</label>
                    <select wire:model.live="aiProvider" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                        @foreach ($aiProviders as $pKey => $pMeta)
                            <option value="{{ $pKey }}">{{ $pMeta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">{{ __('Model') }}</label>
                    <select wire:model="aiModel" dir="ltr" style="width:100%;min-height:44px;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent">
                        @foreach ($currentModels as $mKey => $mLabel)
                            <option value="{{ $mKey }}">{{ $mLabel }} ({{ $mKey }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;margin-bottom:6px">
                        {{ __('API key') }}
                        @if ($maskedAiKey)
                            · <span style="font-family:monospace;color:#14b8a6"><bdi dir="ltr">{{ $maskedAiKey }}</bdi></span>
                        @endif
                    </label>
                    <input type="password" wire:model="aiApiKeyInput" dir="ltr" autocomplete="off" placeholder="{{ $maskedAiKey ?: 'sk-...' }}" style="width:100%;min-height:44px;padding:8px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-family:monospace">
                </div>
                <div>
                    <button type="button" wire:click="testAiConnection" style="min-height:44px;padding:0 16px;border-radius:8px;border:1px solid #0f766e;background:transparent;font-weight:600;cursor:pointer">
                        {{ __('Test AI Connection') }}
                    </button>
                    @if ($aiTestMessage)
                        <p style="margin:8px 0 0;font-size:13px;color:{{ $aiTestOk ? '#10b981' : '#94a3b8' }}">{{ $aiTestMessage }}</p>
                    @endif
                </div>
            </div>
        </x-filament::section>

        <div><x-filament::button type="submit">{{ __('Save settings') }}</x-filament::button></div>
    </form>

    <x-filament::section style="margin-top:16px">
        <x-slot name="heading">{{ __('Held-back modules (config/modules.php)') }}</x-slot>
        <x-slot name="description">{{ __('Read-only status of feature switches governed by D-149 and D-229. Standard workspaces only see modules that are turned on.') }}</x-slot>

        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <thead>
                    <tr style="text-align:start;border-bottom:1px solid rgba(148,163,184,.25)">
                        <th style="padding:8px">{{ __('Module key') }}</th>
                        <th style="padding:8px">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($modules as $key => $on)
                        <tr style="border-top:1px solid rgba(148,163,184,.18)">
                            <td style="padding:8px;font-family:monospace;font-weight:600"><bdi dir="ltr">{{ $key }}</bdi></td>
                            <td style="padding:8px">
                                @if ($on)
                                    <span style="display:inline-block;padding:2px 10px;border-radius:99px;background:rgba(16,185,129,.15);color:#10b981;font-size:12.5px;font-weight:600">{{ __('On') }}</span>
                                @else
                                    <span style="display:inline-block;padding:2px 10px;border-radius:99px;background:rgba(148,163,184,.2);font-size:12.5px">{{ __('Off (held back)') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if (! empty($previewAgencies))
            <p style="margin:12px 0 0;font-size:13px;opacity:.8">
                {{ __('Preview workspaces') }}: <bdi dir="ltr">{{ implode(', ', $previewAgencies) }}</bdi>
            </p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
