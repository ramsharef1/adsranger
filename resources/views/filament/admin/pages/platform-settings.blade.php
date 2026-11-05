<x-filament-panels::page>
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
        <div><x-filament::button type="submit">{{ __('Save settings') }}</x-filament::button></div>
    </form>
</x-filament-panels::page>
