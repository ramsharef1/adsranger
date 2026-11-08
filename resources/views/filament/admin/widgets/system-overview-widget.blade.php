@php
    $activePct = $agenciesTotal > 0 ? (int) round(($agenciesActive / $agenciesTotal) * 100) : 0;
    $offPct = $totalModulesCount > 0 ? (int) round(($offModulesCount / $totalModulesCount) * 100) : 0;
    $tables = [
        [__('Agency waitlist'), url('/admin/waitlist'), $recentWaitlist, __('No waitlist entries yet.'), fn ($w) => [$w->agency_name ?: ($w->name ?: '—'), $w->email, $w->created_at?->format('m-d H:i')]],
        [__('Partner applications'), url('/admin/partner-applications'), $recentPartners, __('No partner applications yet.'), fn ($p) => [$p->agency_name, $p->country, $p->clients_range]],
        [__('Audit log'), url('/admin/audit-log'), $recentAudits, '—', fn ($a) => [$a->action, null, $a->created_at?->format('m-d H:i')]],
    ];
@endphp
<x-filament-widgets::widget>
    {{-- /admin overview (E2-025…031, reviewed by E1): real counts + dynamic SVG donut gauges; light + dark theme aware. --}}
    <style>
        .ov{display:grid;gap:18px;--ov-card:#fff;--ov-soft:#f8fafc;--ov-line:#e2e8f0;--ov-ink:#0f172a;--ov-muted:#475569;--ov-acc:#0f766e;--ov-cta:#0f766e;--ov-cta-ink:#fff;--ov-track:#e2e8f0}
        .dark .ov{--ov-card:#111827;--ov-soft:#0b1120;--ov-line:#1e293b;--ov-ink:#f8fafc;--ov-muted:#94a3b8;--ov-acc:#5eead4;--ov-cta:#5eead4;--ov-cta-ink:#0b1120;--ov-track:#1e293b}
        .ov-box{background:var(--ov-card);border:1px solid var(--ov-line);border-radius:14px;color:var(--ov-ink);box-shadow:0 2px 10px -2px rgba(15,23,42,.06)}
        .ov-top{padding:18px 22px}
        .ov-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--ov-line)}
        .ov-tag{display:inline-flex;padding:4px 12px;border-radius:6px;background:#0f172a;color:#fff;font-size:12px;font-weight:700;letter-spacing:.03em}
        .dark .ov-tag{background:#1e293b}
        .ov-sub{font-size:13px;color:var(--ov-muted)}
        .ov-links{display:flex;flex-wrap:wrap;gap:8px}
        .ov-links a{display:inline-flex;align-items:center;min-height:44px;padding:6px 12px;border-radius:8px;background:var(--ov-soft);border:1px solid var(--ov-line);color:var(--ov-ink);text-decoration:none;font-size:13px;font-weight:600}
        .ov-links a:hover{border-color:var(--ov-acc)}
        .ov-links a.ov-main{background:var(--ov-cta);border-color:var(--ov-cta);color:var(--ov-cta-ink);font-weight:700}
        .ov-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr));gap:16px;padding-top:16px}
        .ov-kpi{display:flex;flex-direction:column;justify-content:space-between;padding:14px 16px;border-radius:12px;background:var(--ov-soft);border:1px solid var(--ov-line);border-top:3px solid var(--ov-acc);text-decoration:none;color:var(--ov-ink);transition:transform .12s ease}
        .ov-kpi:hover{transform:translateY(-2px)}
        .ov-kpi-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
        .ov-lbl{font-size:11.5px;color:var(--ov-muted);font-weight:700;text-transform:uppercase;letter-spacing:.02em}
        .ov-num{font-size:28px;font-weight:700;margin-top:2px;font-variant-numeric:tabular-nums}
        .ov-note{font-size:12.5px;color:var(--ov-acc);margin-top:4px;font-weight:700}
        .ov-bar{height:6px;border-radius:99px;background:var(--ov-track);margin-top:8px;overflow:hidden}
        .ov-bar i{display:block;height:100%;background:var(--ov-acc)}
        .ov-pill{padding:2px 8px;border-radius:6px;font-size:12px;font-weight:700}
        .ov-open{background:#dcfce7;color:#166534}.ov-closed{background:#ffedd5;color:#9a3412}
        .ov-ico{width:38px;height:38px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:15px;flex-shrink:0}
        .ov-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(310px,100%),1fr));gap:16px}
        .ov-card{overflow:hidden}
        .ov-card-h{padding:10px 16px;background:var(--ov-soft);border-bottom:1px solid var(--ov-line);display:flex;align-items:center;justify-content:space-between;gap:8px}
        .ov-card-h a{display:inline-flex;align-items:center;min-height:44px;font-size:13px;color:var(--ov-acc);font-weight:700}
        .ov-card table{width:100%;border-collapse:collapse;font-size:13px}
        .ov-card td{padding:8px 4px;border-bottom:1px solid var(--ov-line);color:var(--ov-muted)}
        .ov-card td:first-child{font-weight:700;color:var(--ov-ink)}
        .ov-card td:last-child{text-align:end;font-variant-numeric:tabular-nums}
    </style>
    <div class="ov">
        <div class="ov-box ov-top">
            <div class="ov-head">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <span class="ov-tag">ADSRANGER ADMIN</span>
                    <strong style="font-size:18px">{{ __('Platform overview') }}</strong>
                    <span class="ov-sub">{{ __('Live summary of workspaces, public waitlist signups, partner applications, and platform safety switches.') }}</span>
                </div>
                <nav class="ov-links" aria-label="{{ __('Platform overview') }}">
                    <a href="{{ url('/admin/agencies') }}">{{ __('Agencies') }}</a>
                    <a href="{{ url('/admin/plans') }}">{{ __('Plans') }}</a>
                    <a href="{{ url('/admin/ad-networks') }}">{{ __('Ad networks') }}</a>
                    <a href="{{ url('/admin/audit-log') }}">{{ __('Audit log') }}</a>
                    <a class="ov-main" href="{{ url('/admin/platform-settings') }}">{{ __('Platform settings') }}</a>
                </nav>
            </div>

            <div class="ov-kpis">
                <a class="ov-kpi" href="{{ url('/admin/agencies') }}">
                    <div class="ov-kpi-row">
                        <div>
                            <div class="ov-lbl">{{ __('Agencies') }}</div>
                            <div class="ov-num">{{ $agenciesTotal }}</div>
                            <div class="ov-note">{{ __('Active') }}: {{ $agenciesActive }}</div>
                        </div>
                        <svg width="46" height="46" viewBox="0 0 36 36" role="img" aria-label="{{ $activePct }}%">
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--ov-track)" stroke-width="3.8"/>
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--ov-acc)" stroke-width="3.8" stroke-dasharray="{{ $activePct }}, 100" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="ov-bar" aria-hidden="true"><i style="width:{{ $activePct }}%"></i></div>
                </a>

                <a class="ov-kpi" href="{{ url('/admin/waitlist') }}" style="border-top-color:#10b981">
                    <div>
                        <div class="ov-lbl">{{ __('Agency waitlist') }}</div>
                        <div class="ov-num">{{ $waitlistTotal }}</div>
                        <div class="ov-note" style="color:#059669">{{ __('Consent') }}: {{ $waitlistConsented }} ({{ $waitlistConsentPct }}%)</div>
                    </div>
                    <div class="ov-bar" role="img" aria-label="{{ $waitlistConsentPct }}%"><i style="width:{{ $waitlistConsentPct }}%;background:#10b981"></i></div>
                </a>

                <a class="ov-kpi" href="{{ url('/admin/partner-applications') }}" style="border-top-color:var(--ov-acc)">
                    <div class="ov-kpi-row">
                        <div>
                            <div class="ov-lbl">{{ __('Partner applications') }}</div>
                            <div class="ov-num">{{ $partnersTotal }}</div>
                            <div class="ov-note" style="color:var(--ov-acc)"><bdi dir="ltr">/partners</bdi></div>
                        </div>
                        <span class="ov-ico" style="background:rgba(20,184,166,.14);color:var(--ov-acc)" aria-hidden="true">★</span>
                    </div>
                </a>

                <a class="ov-kpi" href="{{ url('/admin/network-requests') }}" style="border-top-color:#0284c7">
                    <div class="ov-kpi-row">
                        <div>
                            <div class="ov-lbl">{{ __('Network requests') }}</div>
                            <div class="ov-num">{{ $networkRequestsTotal }}</div>
                            <div class="ov-note" style="color:#0284c7"><bdi dir="ltr">#127</bdi></div>
                        </div>
                        <span class="ov-ico" style="background:rgba(2,132,199,.14);color:#0284c7" aria-hidden="true">⇄</span>
                    </div>
                </a>

                <a class="ov-kpi" href="{{ url('/admin/platform-settings') }}" style="border-top-color:#0f172a">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                        <span class="ov-lbl">{{ __('Self-signup') }}</span>
                        <span class="ov-pill {{ $signupOpen ? 'ov-open' : 'ov-closed' }}">{{ $signupOpen ? __('Open') : __('Invite only') }}</span>
                    </div>
                    <div class="ov-num" style="font-size:15px;margin-top:10px">{{ __('Held-back modules off') }}: {{ $offModulesCount }}/{{ $totalModulesCount }}</div>
                    <div class="ov-bar" role="img" aria-label="{{ $offPct }}%"><i style="width:{{ $offPct }}%;background:#0f172a"></i></div>
                </a>
            </div>
        </div>

        <div class="ov-cards">
            @foreach ($tables as [$heading, $href, $rows, $empty, $cells])
                <section class="ov-box ov-card">
                    <div class="ov-card-h">
                        <strong style="font-size:14px">{{ $heading }}</strong>
                        <a href="{{ $href }}">{{ __('View all') }}</a>
                    </div>
                    <div style="padding:4px 12px 8px">
                        @if ($rows->isEmpty())
                            <p class="ov-sub" style="margin:10px 4px">{{ $empty }}</p>
                        @else
                            <table>
                                <tbody>
                                    @foreach ($rows as $row)
                                        @php([$a, $b, $c] = $cells($row))
                                        <tr>
                                            <td><bdi>{{ $a }}</bdi></td>
                                            @if ($b !== null)<td><bdi dir="ltr">{{ $b }}</bdi></td>@endif
                                            <td><bdi dir="ltr">{{ $c }}</bdi></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
