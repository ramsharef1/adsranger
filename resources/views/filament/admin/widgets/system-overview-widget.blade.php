<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('Platform overview') }}</x-slot>
        <x-slot name="description">{{ __('Live summary of workspaces, public waitlist signups, partner applications, and platform safety switches.') }}</x-slot>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px">
            <a href="{{ url('/admin/agencies') }}" style="display:block;padding:14px 16px;border-radius:10px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit">
                <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Agencies') }}</div>
                <div style="font-size:24px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums">{{ $agenciesTotal }}</div>
                <div style="font-size:12px;opacity:.75;margin-top:4px">{{ __('Active') }}: {{ $agenciesActive }}</div>
            </a>

            <a href="{{ url('/admin/waitlist') }}" style="display:block;padding:14px 16px;border-radius:10px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit">
                <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Agency waitlist') }}</div>
                <div style="font-size:24px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums">{{ $waitlistTotal }}</div>
                <div style="font-size:12px;opacity:.75;margin-top:4px">{{ __('Consent') }}: {{ $waitlistConsented }}</div>
            </a>

            <a href="{{ url('/admin/partner-applications') }}" style="display:block;padding:14px 16px;border-radius:10px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit">
                <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Partner applications') }}</div>
                <div style="font-size:24px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums">{{ $partnersTotal }}</div>
                <div style="font-size:12px;opacity:.75;margin-top:4px">/partners</div>
            </a>

            <a href="{{ url('/admin/network-requests') }}" style="display:block;padding:14px 16px;border-radius:10px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit">
                <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Network requests') }}</div>
                <div style="font-size:24px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums">{{ $networkRequestsTotal }}</div>
                <div style="font-size:12px;opacity:.75;margin-top:4px">#127</div>
            </a>

            <a href="{{ url('/admin/platform-settings') }}" style="display:block;padding:14px 16px;border-radius:10px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit">
                <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Self-signup') }}</div>
                <div style="font-size:18px;font-weight:700;margin-top:6px">
                    @if ($signupOpen)
                        <span style="display:inline-block;padding:2px 10px;border-radius:99px;background:rgba(16,185,129,.15);color:#10b981;font-size:13px">{{ __('Open') }}</span>
                    @else
                        <span style="display:inline-block;padding:2px 10px;border-radius:99px;background:rgba(148,163,184,.2);font-size:13px">{{ __('Invite only') }}</span>
                    @endif
                </div>
                <div style="font-size:12px;opacity:.75;margin-top:6px">{{ __('Held-back modules off') }}: {{ $offModulesCount }}/{{ $totalModulesCount }}</div>
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
