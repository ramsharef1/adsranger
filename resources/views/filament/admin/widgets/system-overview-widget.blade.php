<x-filament-widgets::widget>
    <div style="display:grid;gap:16px">
        <x-filament::section>
            <x-slot name="heading">{{ __('Platform overview') }}</x-slot>
            <x-slot name="description">{{ __('Live summary of workspaces, public waitlist signups, partner applications, and platform safety switches.') }}</x-slot>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px">
                <a href="{{ url('/admin/agencies') }}" style="display:block;padding:14px 16px;border-radius:12px;border:1px solid rgba(148,163,184,.25);border-inline-start:4px solid #0f766e;text-decoration:none;color:inherit">
                    <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Agencies') }}</div>
                    <div style="font-size:26px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums">{{ $agenciesTotal }}</div>
                    <div style="font-size:12px;opacity:.8;margin-top:6px">{{ __('Active') }}: {{ $agenciesActive }}</div>
                </a>

                <a href="{{ url('/admin/waitlist') }}" style="display:block;padding:14px 16px;border-radius:12px;border:1px solid rgba(148,163,184,.25);border-inline-start:4px solid #10b981;text-decoration:none;color:inherit">
                    <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Agency waitlist') }}</div>
                    <div style="font-size:26px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums">{{ $waitlistTotal }}</div>
                    <div style="font-size:12px;opacity:.8;margin-top:6px">{{ __('Consent') }}: {{ $waitlistConsented }} ({{ $waitlistConsentPct }}%)</div>
                </a>

                <a href="{{ url('/admin/partner-applications') }}" style="display:block;padding:14px 16px;border-radius:12px;border:1px solid rgba(148,163,184,.25);border-inline-start:4px solid #f59e0b;text-decoration:none;color:inherit">
                    <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Partner applications') }}</div>
                    <div style="font-size:26px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums">{{ $partnersTotal }}</div>
                    <div style="font-size:12px;opacity:.8;margin-top:6px"><bdi dir="ltr">/partners</bdi></div>
                </a>

                <a href="{{ url('/admin/network-requests') }}" style="display:block;padding:14px 16px;border-radius:12px;border:1px solid rgba(148,163,184,.25);border-inline-start:4px solid #0284c7;text-decoration:none;color:inherit">
                    <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Network requests') }}</div>
                    <div style="font-size:26px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums">{{ $networkRequestsTotal }}</div>
                    <div style="font-size:12px;opacity:.8;margin-top:6px"><bdi dir="ltr">#127</bdi></div>
                </a>

                <a href="{{ url('/admin/platform-settings') }}" style="display:block;padding:14px 16px;border-radius:12px;border:1px solid rgba(148,163,184,.25);border-inline-start:4px solid #6366f1;text-decoration:none;color:inherit">
                    <div style="font-size:12px;opacity:.7;font-weight:600">{{ __('Self-signup') }}</div>
                    <div style="font-size:18px;font-weight:700;margin-top:6px">
                        @if ($signupOpen)
                            <span style="display:inline-block;padding:2px 10px;border-radius:99px;background:rgba(16,185,129,.15);color:#10b981;font-size:13px">{{ __('Open') }}</span>
                        @else
                            <span style="display:inline-block;padding:2px 10px;border-radius:99px;background:rgba(148,163,184,.2);font-size:13px">{{ __('Invite only') }}</span>
                        @endif
                    </div>
                    <div style="font-size:12px;opacity:.8;margin-top:6px">{{ __('Held-back modules off') }}: {{ $offModulesCount }}/{{ $totalModulesCount }}</div>
                </a>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;padding-top:12px;border-top:1px solid rgba(148,163,184,.2)">
                <a href="{{ url('/admin/agencies') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:4px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit;font-size:13px;font-weight:600">{{ __('Agencies') }}</a>
                <a href="{{ url('/admin/plans') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:4px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit;font-size:13px;font-weight:600">{{ __('Plans') }}</a>
                <a href="{{ url('/admin/ad-networks') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:4px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit;font-size:13px;font-weight:600">{{ __('Ad networks') }}</a>
                <a href="{{ url('/admin/audit-log') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:4px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit;font-size:13px;font-weight:600">{{ __('Audit log') }}</a>
                <a href="{{ url('/admin/platform-settings') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:4px 12px;border-radius:8px;border:1px solid rgba(148,163,184,.25);text-decoration:none;color:inherit;font-size:13px;font-weight:600">{{ __('Platform settings') }}</a>
            </div>
        </x-filament::section>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px">
            <x-filament::section>
                <x-slot name="heading">{{ __('Agency waitlist') }}</x-slot>
                @if ($recentWaitlist->isEmpty())
                    <p style="opacity:.7;font-size:13px;margin:0">{{ __('No waitlist entries yet.') }}</p>
                @else
                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:8px;font-size:13px">
                        @foreach ($recentWaitlist as $w)
                            <li style="display:flex;justify-content:space-between;gap:8px;padding-bottom:6px;border-bottom:1px solid rgba(148,163,184,.15)">
                                <span><strong>{{ $w->agency_name ?: ($w->name ?: '—') }}</strong> · <bdi dir="ltr">{{ $w->email }}</bdi></span>
                                <span style="opacity:.7;font-variant-numeric:tabular-nums"><bdi dir="ltr">{{ $w->created_at?->format('m-d H:i') }}</bdi></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Partner applications') }}</x-slot>
                @if ($recentPartners->isEmpty())
                    <p style="opacity:.7;font-size:13px;margin:0">{{ __('No partner applications yet.') }}</p>
                @else
                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:8px;font-size:13px">
                        @foreach ($recentPartners as $p)
                            <li style="display:flex;justify-content:space-between;gap:8px;padding-bottom:6px;border-bottom:1px solid rgba(148,163,184,.15)">
                                <span><strong>{{ $p->agency_name }}</strong> · <bdi dir="ltr">{{ $p->country }}</bdi></span>
                                <span style="opacity:.7;font-variant-numeric:tabular-nums"><bdi dir="ltr">{{ $p->clients_range }}</bdi></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Audit log') }}</x-slot>
                @if ($recentAudits->isEmpty())
                    <p style="opacity:.7;font-size:13px;margin:0">—</p>
                @else
                    <ul style="list-style:none;padding:0;margin:0;display:grid;gap:8px;font-size:13px">
                        @foreach ($recentAudits as $a)
                            <li style="display:flex;justify-content:space-between;gap:8px;padding-bottom:6px;border-bottom:1px solid rgba(148,163,184,.15)">
                                <span><bdi dir="ltr">{{ $a->action }}</bdi></span>
                                <span style="opacity:.7;font-variant-numeric:tabular-nums"><bdi dir="ltr">{{ $a->created_at?->format('m-d H:i') }}</bdi></span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament-widgets::widget>
