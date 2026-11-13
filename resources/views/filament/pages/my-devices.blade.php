<x-filament-panels::page>
    @php($rows = $this->sessions())
    <x-filament::section>
        <x-slot name="description">{{ __('Where your account is signed in now. If you see a device you don\'t know, sign out the other devices and change your password.') }}</x-slot>
        <div style="display:flex;flex-direction:column;gap:2px">
            @forelse ($rows as $s)
                <div style="display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(127,127,127,.2)" data-session>
                    <div>
                        <strong><bdi>{{ $s['label'] }}</bdi></strong>
                        @if ($s['current'])<x-filament::badge color="success" style="display:inline-flex;margin-inline-start:6px">{{ __('This device') }}</x-filament::badge>@endif
                        <div style="font-size:13px;opacity:.7">
                            {{ __('Last active :time', ['time' => $s['last_active']->locale(app()->getLocale())->diffForHumans()]) }}
                            @if ($s['ip']) · <bdi dir="ltr">{{ $s['ip'] }}</bdi>@endif
                        </div>
                    </div>
                    @unless ($s['current'])
                        <x-filament::button color="gray" size="sm" wire:click="signOut(@js($s['id']))" style="min-height:44px">{{ __('Sign out') }}</x-filament::button>
                    @endunless
                </div>
            @empty
                <p style="opacity:.7">{{ __('No signed-in devices found.') }}</p>
            @endforelse
        </div>
        @if (count($rows) > 1)
            <x-filament::button color="danger" wire:click="signOutOthers" wire:confirm="{{ __('Sign out every device except this one? They will need your password to sign in again.') }}" style="margin-top:12px;min-height:44px">{{ __('Sign out all other devices') }}</x-filament::button>
        @endif
    </x-filament::section>

    @php($fresh = $this->newDevices())
    @if ($fresh->isNotEmpty())
        <x-filament::section :heading="__('New devices in the last 30 days')">
            <x-slot name="description">{{ __('We send a phone alert (if you turned phone alerts on) when your account signs in from a browser and system it hasn\'t used in 6 months.') }}</x-slot>
            @foreach ($fresh as $r)
                <div style="padding:6px 0;font-size:14px"><bdi>{{ $r->meta['label'] ?? __('Unknown device') }}</bdi> · <bdi dir="ltr">{{ $r->created_at?->timezone(\Filament\Facades\Filament::getTenant()?->timezone ?: 'Asia/Amman')->format('Y-m-d H:i') }}</bdi></div>
            @endforeach
        </x-filament::section>
    @endif
</x-filament-panels::page>
