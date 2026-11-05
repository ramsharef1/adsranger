<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">{{ __('These choices are yours only and apply in every workspace you belong to. Emails come from noreply@adsranger.com.') }}</x-slot>
        <label style="display:flex;gap:10px;align-items:flex-start;padding:8px 0">
            <input type="checkbox" wire:model="alerts" style="margin-top:4px">
            <span><strong>{{ __('Email me new high alerts') }}</strong><br><span style="opacity:.65;font-size:13px">{{ __('Tracking breaks, spend with no results, tracking issues and connection problems, for the clients you can see.') }}</span></span>
        </label>
        <label style="display:flex;gap:10px;align-items:flex-start;padding:8px 0">
            <input type="checkbox" wire:model="digest" style="margin-top:4px">
            <span><strong>{{ __('Morning digest at 08:00') }}</strong><br><span style="opacity:.65;font-size:13px">{{ __('Yesterday\'s results per client and the open alerts, in the workspace time zone.') }}</span></span>
        </label>
        <x-filament::button wire:click="save" style="margin-top:8px">{{ __('Save') }}</x-filament::button>
    </x-filament::section>

    {{-- #175 phone alerts (Pro+, D-104): this device on / off, quiet hours, weekend, spend override, test. --}}
    <x-filament::section :heading="__('Phone alerts')" data-phone-alerts>
        @if (! $this->phoneAllowed())
            <x-slot name="description">{{ __('Instant phone alerts are available on Pro.') }}</x-slot>
            <x-locked-state-body feature="phone_push" :tier="\App\Support\PlanGate::requiredTier('phone_push')" :can-upgrade="\App\Support\PlanGate::canUpgrade(auth()->user(), \Filament\Facades\Filament::getTenant())" />
        @else
            <x-slot name="description">{{ __('Only Critical alerts, for the clients you can see. Warning and Info stay in the inbox and emails.') }}</x-slot>
            <div x-data="{ st: 'checking', busy: false, msg: '',
                    async init() { this.st = window.adsrPush ? await window.adsrPush.state() : 'unsupported' },
                    async on() { this.busy = true; this.st = await window.adsrPush.enable(); this.busy = false; $wire.$refresh() },
                    async off() { this.busy = true; this.st = await window.adsrPush.disable(); this.busy = false; $wire.$refresh() },
                    async test() { this.busy = true; this.msg = (await window.adsrPush.test()) ? @js(__('Test sent. It should arrive within a minute.')) : @js(__('No device received it. Turn phone alerts on here first.')); this.busy = false } }"
                 style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:4px 0 12px">
                <span x-show="st === 'on'">{{ __('On for this device') }}</span>
                <span x-show="st === 'off'">{{ __('Off on this device') }}</span>
                <span x-show="st === 'denied'">{{ __('Notifications are blocked for this site in the browser settings.') }}</span>
                <span x-show="st === 'unsupported'">{{ __('This browser can\'t receive phone alerts. On iPhone, add AdsRanger to the home screen first.') }}</span>
                <x-filament::button x-show="st === 'off'" x-on:click="on()" x-bind:disabled="busy" style="min-height:44px">{{ __('Turn on') }}</x-filament::button>
                <x-filament::button x-show="st === 'on'" x-on:click="off()" x-bind:disabled="busy" color="gray" style="min-height:44px">{{ __('Turn off') }}</x-filament::button>
                <x-filament::button x-show="st === 'on'" x-on:click="test()" x-bind:disabled="busy" color="gray" style="min-height:44px">{{ __('Send a test') }}</x-filament::button>
                <span x-text="msg" style="font-size:13px;opacity:.8"></span>
            </div>
            <p style="font-size:13px;opacity:.7;margin:0 0 12px">{{ trans_choice('{0} No device has phone alerts on yet.|{1} :count device has phone alerts on.|[2,*] :count devices have phone alerts on.', $this->devices()) }}</p>
            <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">
                <label style="display:flex;flex-direction:column;gap:4px">{{ __('Quiet hours from') }}
                    <select wire:model="quietStart" style="min-height:44px;border-radius:8px">@foreach ($this->hours() as $h) <option value="{{ $h }}"><bdi dir="ltr">{{ $h }}</bdi></option> @endforeach</select></label>
                <label style="display:flex;flex-direction:column;gap:4px">{{ __('Quiet hours to') }}
                    <select wire:model="quietEnd" style="min-height:44px;border-radius:8px">@foreach ($this->hours() as $h) <option value="{{ $h }}"><bdi dir="ltr">{{ $h }}</bdi></option> @endforeach</select></label>
            </div>
            <p style="font-size:13px;opacity:.65;margin:6px 0 0">{{ __('Workspace time. Alerts held during quiet hours arrive as one summary when they end.') }}</p>
            <label style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;min-height:44px">
                <input type="checkbox" wire:model="weekendOff" style="margin-top:4px">
                <span><strong>{{ __('Quiet on the workspace weekend') }}</strong></span>
            </label>
            <label style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;min-height:44px">
                <input type="checkbox" wire:model="overrideSpend" style="margin-top:4px">
                <span><strong>{{ __('Send anyway when spend is 2× the budget or more') }}</strong></span>
            </label>
            <x-filament::button wire:click="save" style="margin-top:8px">{{ __('Save') }}</x-filament::button>
        @endif
    </x-filament::section>
</x-filament-panels::page>
