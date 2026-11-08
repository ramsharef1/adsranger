<x-filament-widgets::widget>
    <div style="display:grid;gap:18px">
        {{-- Semrush Dark Executive Command Banner --}}
        <div style="background:linear-gradient(135deg,#0b1120 0%,#131f3a 60%,#0f3d3e 100%);color:#f8fafc;border-radius:16px;padding:22px 24px;border:1px solid #1e293b;box-shadow:0 14px 32px -12px rgba(11,17,32,.35)">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px">
                <div>
                    <div style="display:inline-flex;align-items:center;gap:8px;padding:3px 10px;border-radius:99px;background:rgba(45,212,191,.15);color:#5eead4;font-size:12px;font-weight:700;margin-bottom:6px">
                        <span>●</span>
                        <span>{{ __('Platform overview') }}</span>
                    </div>
                    <div style="font-size:14px;color:#cbd5e1">{{ __('Live summary of workspaces, public waitlist signups, partner applications, and platform safety switches.') }}</div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                    <a href="{{ url('/admin/agencies') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:6px 14px;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);color:#f8fafc;text-decoration:none;font-size:13px;font-weight:600">{{ __('Agencies') }}</a>
                    <a href="{{ url('/admin/plans') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:6px 14px;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);color:#f8fafc;text-decoration:none;font-size:13px;font-weight:600">{{ __('Plans') }}</a>
                    <a href="{{ url('/admin/ad-networks') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:6px 14px;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);color:#f8fafc;text-decoration:none;font-size:13px;font-weight:600">{{ __('Ad networks') }}</a>
                    <a href="{{ url('/admin/audit-log') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:6px 14px;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);color:#f8fafc;text-decoration:none;font-size:13px;font-weight:600">{{ __('Audit log') }}</a>
                    <a href="{{ url('/admin/platform-settings') }}" style="display:inline-flex;align-items:center;min-height:38px;padding:6px 14px;border-radius:8px;background:#f97316;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700">{{ __('Platform settings') }}</a>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
                <a href="{{ url('/admin/agencies') }}" style="display:block;padding:16px;border-radius:12px;background:rgba(17,24,39,.85);border:1px solid #26334d;border-top:3px solid #14b8a6;text-decoration:none;color:#f8fafc">
                    <div style="font-size:12px;color:#94a3b8;font-weight:600">{{ __('Agencies') }}</div>
                    <div style="font-size:28px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums;color:#ffffff">{{ $agenciesTotal }}</div>
                    <div style="font-size:12px;color:#5eead4;margin-top:6px;font-weight:600">{{ __('Active') }}: {{ $agenciesActive }}</div>
                </a>

                <a href="{{ url('/admin/waitlist') }}" style="display:block;padding:16px;border-radius:12px;background:rgba(17,24,39,.85);border:1px solid #26334d;border-top:3px solid #10b981;text-decoration:none;color:#f8fafc">
                    <div style="font-size:12px;color:#94a3b8;font-weight:600">{{ __('Agency waitlist') }}</div>
                    <div style="font-size:28px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums;color:#ffffff">{{ $waitlistTotal }}</div>
                    <div style="font-size:12px;color:#6ee7b7;margin-top:6px;font-weight:600">{{ __('Consent') }}: {{ $waitlistConsented }} ({{ $waitlistConsentPct }}%)</div>
                </a>

                <a href="{{ url('/admin/partner-applications') }}" style="display:block;padding:16px;border-radius:12px;background:rgba(17,24,39,.85);border:1px solid #26334d;border-top:3px solid #f97316;text-decoration:none;color:#f8fafc">
                    <div style="font-size:12px;color:#94a3b8;font-weight:600">{{ __('Partner applications') }}</div>
                    <div style="font-size:28px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums;color:#ffffff">{{ $partnersTotal }}</div>
                    <div style="font-size:12px;color:#fdba74;margin-top:6px;font-weight:600"><bdi dir="ltr">/partners</bdi></div>
                </a>

                <a href="{{ url('/admin/network-requests') }}" style="display:block;padding:16px;border-radius:12px;background:rgba(17,24,39,.85);border:1px solid #26334d;border-top:3px solid #38bdf8;text-decoration:none;color:#f8fafc">
                    <div style="font-size:12px;color:#94a3b8;font-weight:600">{{ __('Network requests') }}</div>
                    <div style="font-size:28px;font-weight:700;margin-top:4px;font-variant-numeric:tabular-nums;color:#ffffff">{{ $networkRequestsTotal }}</div>
                    <div style="font-size:12px;color:#7dd3fc;margin-top:6px;font-weight:600"><bdi dir="ltr">#127</bdi></div>
                </a>

                <a href="{{ url('/admin/platform-settings') }}" style="display:block;padding:16px;border-radius:12px;background:rgba(17,24,39,.85);border:1px solid #26334d;border-top:3px solid #a855f7;text-decoration:none;color:#f8fafc">
                    <div style="font-size:12px;color:#94a3b8;font-weight:600">{{ __('Self-signup') }}</div>
                    <div style="margin-top:8px">
                        @if ($signupOpen)
                            <span style="display:inline-block;padding:3px 10px;border-radius:99px;background:rgba(16,185,129,.2);color:#6ee7b7;font-size:13px;font-weight:700">{{ __('Open') }}</span>
                        @else
                            <span style="display:inline-block;padding:3px 10px;border-radius:99px;background:rgba(249,115,22,.2);color:#fdba74;font-size:13px;font-weight:700">{{ __('Invite only') }}</span>
                        @endif
                    </div>
                    <div style="font-size:12px;color:#cbd5e1;margin-top:8px">{{ __('Held-back modules off') }}: {{ $offModulesCount }}/{{ $totalModulesCount }}</div>
                </a>
            </div>
        </div>

        {{-- 3-Column Semrush Data Panels --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px">
            <x-filament::section>
                <x-slot name="heading">{{ __('Agency waitlist') }}</x-slot>
                @if ($recentWaitlist->isEmpty())
                    <p style="opacity:.7;font-size:13px;margin:0">{{ __('No waitlist entries yet.') }}</p>
                @else
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <tbody>
                            @foreach ($recentWaitlist as $w)
                                <tr style="border-bottom:1px solid rgba(148,163,184,.15)">
                                    <td style="padding:7px 4px;font-weight:600">{{ $w->agency_name ?: ($w->name ?: '—') }}</td>
                                    <td style="padding:7px 4px;opacity:.8"><bdi dir="ltr">{{ $w->email }}</bdi></td>
                                    <td style="padding:7px 4px;text-align:end;opacity:.7;font-variant-numeric:tabular-nums"><bdi dir="ltr">{{ $w->created_at?->format('m-d H:i') }}</bdi></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Partner applications') }}</x-slot>
                @if ($recentPartners->isEmpty())
                    <p style="opacity:.7;font-size:13px;margin:0">{{ __('No partner applications yet.') }}</p>
                @else
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <tbody>
                            @foreach ($recentPartners as $p)
                                <tr style="border-bottom:1px solid rgba(148,163,184,.15)">
                                    <td style="padding:7px 4px;font-weight:600">{{ $p->agency_name }}</td>
                                    <td style="padding:7px 4px"><bdi dir="ltr">{{ $p->country }}</bdi></td>
                                    <td style="padding:7px 4px;text-align:end;font-variant-numeric:tabular-nums"><bdi dir="ltr">{{ $p->clients_range }}</bdi></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">{{ __('Audit log') }}</x-slot>
                @if ($recentAudits->isEmpty())
                    <p style="opacity:.7;font-size:13px;margin:0">—</p>
                @else
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <tbody>
                            @foreach ($recentAudits as $a)
                                <tr style="border-bottom:1px solid rgba(148,163,184,.15)">
                                    <td style="padding:7px 4px;font-family:monospace;font-size:12px"><bdi dir="ltr">{{ $a->action }}</bdi></td>
                                    <td style="padding:7px 4px;text-align:end;opacity:.7;font-variant-numeric:tabular-nums"><bdi dir="ltr">{{ $a->created_at?->format('m-d H:i') }}</bdi></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament-widgets::widget>
