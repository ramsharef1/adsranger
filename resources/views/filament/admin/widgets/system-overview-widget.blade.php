<x-filament-widgets::widget>
    <div style="display:grid;gap:18px">
        {{-- Semrush-Style Top Project Control Strip --}}
        <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:18px 22px;box-shadow:0 2px 10px -2px rgba(17,19,34,.06);color:#0f172a">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding-bottom:14px;border-bottom:1px solid #e2e8f0">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <span style="display:inline-flex;align-items:center;justify-content:center;padding:4px 12px;border-radius:6px;background:#111322;color:#ffffff;font-size:12px;font-weight:700;letter-spacing:.03em">ADSRANGER ADMIN</span>
                    <strong style="font-size:18px;color:#0f172a">{{ __('Platform overview') }}</strong>
                    <span style="font-size:13px;color:#64748b">{{ __('Live summary of workspaces, public waitlist signups, partner applications, and platform safety switches.') }}</span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:8px">
                    <a href="{{ url('/admin/agencies') }}" style="display:inline-flex;align-items:center;min-height:36px;padding:6px 12px;border-radius:8px;background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;text-decoration:none;font-size:13px;font-weight:600">{{ __('Agencies') }}</a>
                    <a href="{{ url('/admin/plans') }}" style="display:inline-flex;align-items:center;min-height:36px;padding:6px 12px;border-radius:8px;background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;text-decoration:none;font-size:13px;font-weight:600">{{ __('Plans') }}</a>
                    <a href="{{ url('/admin/ad-networks') }}" style="display:inline-flex;align-items:center;min-height:36px;padding:6px 12px;border-radius:8px;background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;text-decoration:none;font-size:13px;font-weight:600">{{ __('Ad networks') }}</a>
                    <a href="{{ url('/admin/audit-log') }}" style="display:inline-flex;align-items:center;min-height:36px;padding:6px 12px;border-radius:8px;background:#f8fafc;border:1px solid #cbd5e1;color:#0f172a;text-decoration:none;font-size:13px;font-weight:600">{{ __('Audit log') }}</a>
                    <a href="{{ url('/admin/platform-settings') }}" style="display:inline-flex;align-items:center;min-height:36px;padding:6px 14px;border-radius:8px;background:#ff642d;color:#ffffff;text-decoration:none;font-size:13px;font-weight:700">{{ __('Platform settings') }}</a>
                </div>
            </div>

            {{-- Semrush 5-Column Domain Overview Style Metric Strip with SVG Gauges --}}
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;padding-top:16px">
                <a href="{{ url('/admin/agencies') }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;text-decoration:none;color:#0f172a">
                    <div>
                        <div style="font-size:11.5px;color:#64748b;font-weight:700;text-transform:uppercase">{{ __('Agencies') }}</div>
                        <div style="font-size:28px;font-weight:700;margin-top:2px;font-variant-numeric:tabular-nums;color:#0f172a">{{ $agenciesTotal }}</div>
                        <div style="font-size:12px;color:#0f766e;margin-top:4px;font-weight:700">{{ __('Active') }}: {{ $agenciesActive }}</div>
                    </div>
                    <svg width="48" height="48" viewBox="0 0 36 36" aria-hidden="true">
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3.8"/>
                        <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#0f766e" stroke-width="3.8" stroke-dasharray="80, 100" stroke-linecap="round"/>
                    </svg>
                </a>

                <a href="{{ url('/admin/waitlist') }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;text-decoration:none;color:#0f172a">
                    <div style="flex:1">
                        <div style="font-size:11.5px;color:#64748b;font-weight:700;text-transform:uppercase">{{ __('Agency waitlist') }}</div>
                        <div style="font-size:28px;font-weight:700;margin-top:2px;font-variant-numeric:tabular-nums;color:#0f172a">{{ $waitlistTotal }}</div>
                        <div style="font-size:12px;color:#059669;margin-top:4px;font-weight:700">{{ __('Consent') }}: {{ $waitlistConsented }} ({{ $waitlistConsentPct }}%)</div>
                        <div style="height:5px;border-radius:99px;background:#e2e8f0;margin-top:6px;overflow:hidden"><div style="height:100%;width:{{ max(10, $waitlistConsentPct) }}%;background:#10b981"></div></div>
                    </div>
                </a>

                <a href="{{ url('/admin/partner-applications') }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;text-decoration:none;color:#0f172a">
                    <div>
                        <div style="font-size:11.5px;color:#64748b;font-weight:700;text-transform:uppercase">{{ __('Partner applications') }}</div>
                        <div style="font-size:28px;font-weight:700;margin-top:2px;font-variant-numeric:tabular-nums;color:#0f172a">{{ $partnersTotal }}</div>
                        <div style="font-size:12px;color:#ea580c;margin-top:4px;font-weight:700"><bdi dir="ltr">/partners</bdi></div>
                    </div>
                    <span style="width:40px;height:40px;border-radius:10px;background:rgba(255,100,45,.12);color:#ff642d;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:16px">★</span>
                </a>

                <a href="{{ url('/admin/network-requests') }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;text-decoration:none;color:#0f172a">
                    <div>
                        <div style="font-size:11.5px;color:#64748b;font-weight:700;text-transform:uppercase">{{ __('Network requests') }}</div>
                        <div style="font-size:28px;font-weight:700;margin-top:2px;font-variant-numeric:tabular-nums;color:#0f172a">{{ $networkRequestsTotal }}</div>
                        <div style="font-size:12px;color:#0284c7;margin-top:4px;font-weight:700"><bdi dir="ltr">#127</bdi></div>
                    </div>
                    <span style="width:40px;height:40px;border-radius:10px;background:rgba(2,132,199,.12);color:#0284c7;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:15px">⇄</span>
                </a>

                <a href="{{ url('/admin/platform-settings') }}" style="display:flex;flex-direction:column;justify-content:space-between;padding:14px 16px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0;text-decoration:none;color:#0f172a">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <span style="font-size:11.5px;color:#64748b;font-weight:700;text-transform:uppercase">{{ __('Self-signup') }}</span>
                        @if ($signupOpen)
                            <span style="padding:2px 8px;border-radius:6px;background:#dcfce7;color:#15803d;font-size:11.5px;font-weight:700">{{ __('Open') }}</span>
                        @else
                            <span style="padding:2px 8px;border-radius:6px;background:#ffedd5;color:#c2410c;font-size:11.5px;font-weight:700">{{ __('Invite only') }}</span>
                        @endif
                    </div>
                    <div style="font-size:13px;font-weight:700;color:#0f172a;margin-top:8px">{{ __('Held-back modules off') }}: {{ $offModulesCount }}/{{ $totalModulesCount }}</div>
                    <div style="height:5px;border-radius:99px;background:#e2e8f0;margin-top:6px;overflow:hidden"><div style="height:100%;width:100%;background:#111322"></div></div>
                </a>
            </div>
        </div>

        {{-- Semrush 3-Column White Data Cards --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(310px,1fr));gap:16px">
            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px -2px rgba(17,19,34,.05);color:#0f172a">
                <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
                    <strong style="font-size:14px">{{ __('Agency waitlist') }}</strong>
                    <a href="{{ url('/admin/waitlist') }}" style="font-size:12px;color:#ff642d;font-weight:700;text-decoration:none">→</a>
                </div>
                <div style="padding:12px 16px">
                    @if ($recentWaitlist->isEmpty())
                        <p style="color:#64748b;font-size:13px;margin:0">{{ __('No waitlist entries yet.') }}</p>
                    @else
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <tbody>
                                @foreach ($recentWaitlist as $w)
                                    <tr style="border-bottom:1px solid #f1f5f9">
                                        <td style="padding:8px 4px;font-weight:700;color:#0f172a">{{ $w->agency_name ?: ($w->name ?: '—') }}</td>
                                        <td style="padding:8px 4px;color:#475569"><bdi dir="ltr">{{ $w->email }}</bdi></td>
                                        <td style="padding:8px 4px;text-align:end;color:#64748b;font-variant-numeric:tabular-nums"><bdi dir="ltr">{{ $w->created_at?->format('m-d H:i') }}</bdi></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px -2px rgba(17,19,34,.05);color:#0f172a">
                <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
                    <strong style="font-size:14px">{{ __('Partner applications') }}</strong>
                    <a href="{{ url('/admin/partner-applications') }}" style="font-size:12px;color:#ff642d;font-weight:700;text-decoration:none">→</a>
                </div>
                <div style="padding:12px 16px">
                    @if ($recentPartners->isEmpty())
                        <p style="color:#64748b;font-size:13px;margin:0">{{ __('No partner applications yet.') }}</p>
                    @else
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <tbody>
                                @foreach ($recentPartners as $p)
                                    <tr style="border-bottom:1px solid #f1f5f9">
                                        <td style="padding:8px 4px;font-weight:700;color:#0f172a">{{ $p->agency_name }}</td>
                                        <td style="padding:8px 4px;color:#475569"><bdi dir="ltr">{{ $p->country }}</bdi></td>
                                        <td style="padding:8px 4px;text-align:end;font-variant-numeric:tabular-nums;color:#0f766e;font-weight:700"><bdi dir="ltr">{{ $p->clients_range }}</bdi></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;box-shadow:0 2px 10px -2px rgba(17,19,34,.05);color:#0f172a">
                <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between">
                    <strong style="font-size:14px">{{ __('Audit log') }}</strong>
                    <a href="{{ url('/admin/audit-log') }}" style="font-size:12px;color:#ff642d;font-weight:700;text-decoration:none">→</a>
                </div>
                <div style="padding:12px 16px">
                    @if ($recentAudits->isEmpty())
                        <p style="color:#64748b;font-size:13px;margin:0">—</p>
                    @else
                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                            <tbody>
                                @foreach ($recentAudits as $a)
                                    <tr style="border-bottom:1px solid #f1f5f9">
                                        <td style="padding:8px 4px;font-family:monospace;font-size:12px;color:#0f172a;font-weight:600"><bdi dir="ltr">{{ $a->action }}</bdi></td>
                                        <td style="padding:8px 4px;text-align:end;color:#64748b;font-variant-numeric:tabular-nums"><bdi dir="ltr">{{ $a->created_at?->format('m-d H:i') }}</bdi></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
