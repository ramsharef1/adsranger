@php
    $w = fn ($v): string => (string) \App\Support\TextClean::western((string) $v);
    $activePct = $agenciesTotal > 0 ? (int) round(($agenciesActive / $agenciesTotal) * 100) : 0;
    $offPct = $totalModulesCount > 0 ? (int) round(($offModulesCount / $totalModulesCount) * 100) : 0;
    $tables = [
        [__('Agency waitlist'), url('/admin/waitlist'), $recentWaitlist, __('No waitlist entries yet.'), fn ($row) => [$row->agency_name ?: ($row->name ?: '—'), $row->email, $row->created_at?->format('m-d H:i')]],
        [__('Partner applications'), url('/admin/partner-applications'), $recentPartners, __('No partner applications yet.'), fn ($row) => [$row->agency_name, $row->country, $row->clients_range]],
        [__('Audit log'), url('/admin/audit-log'), $recentAudits, '—', fn ($row) => [$row->action, null, $row->created_at?->format('m-d H:i')]],
    ];
@endphp
<x-filament-widgets::widget>
    {{-- E2-068: PX executive command-center cockpit for /admin. Strictly Slate #0f172a/#1e293b + Teal #0d9488/#14b8a6/#0f766e. --}}
    <style>
        .ov{display:grid;gap:16px;--ov-card:#ffffff;--ov-soft:#f8fafc;--ov-line:#e2e8f0;--ov-ink:#0f172a;--ov-muted:#475569;--ov-acc:#0d9488;--ov-acc-2:#14b8a6;--ov-cta:#0f766e;--ov-cta-ink:#ffffff;--ov-track:#e2e8f0}
        .dark .ov{--ov-card:#1e293b;--ov-soft:#0f172a;--ov-line:#334155;--ov-ink:#f8fafc;--ov-muted:#94a3b8;--ov-acc:#14b8a6;--ov-acc-2:#5eead4;--ov-cta:#14b8a6;--ov-cta-ink:#0f172a;--ov-track:#334155}
        .ov-box{background:var(--ov-card);border:1px solid var(--ov-line);border-radius:14px;color:var(--ov-ink);box-shadow:0 2px 10px -2px rgba(15,23,42,.06)}
        .ov-top{padding:16px 20px}
        .ov-head{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--ov-line)}
        .ov-tag{display:inline-flex;align-items:center;gap:6px;padding:4px 11px;border-radius:8px;background:#0f172a;border:1px solid #1e293b;color:#5eead4;font-size:11.5px;font-weight:700;letter-spacing:.04em}
        .ov-sub{font-size:13px;color:var(--ov-muted)}
        .ov-links{display:flex;flex-wrap:wrap;gap:6px}
        .ov-links a{display:inline-flex;align-items:center;min-height:44px;padding:6px 12px;border-radius:8px;background:var(--ov-soft);border:1px solid var(--ov-line);color:var(--ov-ink);text-decoration:none;font-size:12.5px;font-weight:600}
        .ov-links a:hover{border-color:var(--ov-acc)}
        .ov-links a.ov-main{background:var(--ov-cta);border-color:var(--ov-cta);color:var(--ov-cta-ink);font-weight:700}
        .ov-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(196px,100%),1fr));gap:14px;padding-top:14px}
        .ov-kpi{display:flex;flex-direction:column;justify-content:space-between;min-height:44px;padding:13px 15px;border-radius:12px;background:var(--ov-soft);border:1px solid var(--ov-line);border-top:3px solid var(--ov-acc);text-decoration:none;color:var(--ov-ink);transition:border-color .12s ease,transform .12s ease}
        .ov-kpi:hover{transform:translateY(-1px);border-color:var(--ov-acc-2)}
        .ov-kpi-row{display:flex;align-items:center;justify-content:space-between;gap:10px}
        .ov-lbl{font-size:11.5px;color:var(--ov-muted);font-weight:700;text-transform:uppercase;letter-spacing:.02em}
        .ov-num{font-size:26px;font-weight:700;margin-top:2px;font-variant-numeric:tabular-nums}
        .ov-note{font-size:12px;color:var(--ov-acc);margin-top:4px;font-weight:700;font-variant-numeric:tabular-nums}
        .ov-bar{height:6px;border-radius:99px;background:var(--ov-track);margin-top:8px;overflow:hidden}
        .ov-bar i{display:block;height:100%;background:var(--ov-acc)}
        .ov-pill{padding:2px 8px;border-radius:6px;font-size:11.5px;font-weight:700}
        .ov-open{background:#ccfbf1;color:#0f766e}
        .ov-closed{background:#e2e8f0;color:#0f172a}
        .dark .ov-closed{background:#334155;color:#e2e8f0}
        .ov-ico{width:36px;height:36px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;background:rgba(20,184,166,.14);color:var(--ov-acc)}
        .ov-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr));gap:14px}
        .ov-card{overflow:hidden}
        .ov-card-h{padding:8px 14px;background:var(--ov-soft);border-bottom:1px solid var(--ov-line);display:flex;align-items:center;justify-content:space-between;gap:8px}
        .ov-card-h a{display:inline-flex;align-items:center;min-height:44px;padding:0 6px;font-size:12.5px;color:var(--ov-acc);font-weight:700;text-decoration:none}
        .ov-card table{width:100%;border-collapse:collapse;font-size:13px}
        .ov-card td{padding:7px 4px;border-bottom:1px solid var(--ov-line);color:var(--ov-muted)}
        .ov-card tr:last-child td{border-bottom:0}
        .ov-card td:first-child{font-weight:700;color:var(--ov-ink)}
        .ov-card td:last-child{text-align:end;font-variant-numeric:tabular-nums}
    </style>
    <div class="ov {{ ($density ?? 'comfortable') === 'compact' ? 'ov--compact' : '' }}" data-admin-cockpit>
        <div class="ov-box ov-top">
            <div class="ov-head">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <span class="ov-tag"><bdi dir="ltr">ADSRANGER · PX COCKPIT</bdi></span>
                    <strong style="font-size:17px">{{ __('Platform overview') }}</strong>
                    <span class="ov-sub">{{ __('Live summary of workspaces, public waitlist signups, partner applications, and platform safety switches.') }}</span>
                </div>
                <nav class="ov-links" aria-label="{{ __('Platform overview') }}">
                    <a href="{{ url('/admin/agencies') }}">{{ __('Agencies') }}</a>
                    <a href="{{ url('/admin/live-verification') }}">{{ __('Live verification') }}</a>
                    <a href="{{ url('/admin/costs-launch') }}">{{ __('Costs & launch') }}</a>
                    <a href="{{ url('/admin/tool-guard') }}">{{ __('Tool guard') }}</a>
                    <a href="{{ url('/admin/register-guard') }}">{{ __('Register-to-code guard') }}</a>
                    <a href="{{ url('/admin/audit-log') }}">{{ __('Audit log') }}</a>
                    <a class="ov-main" href="{{ url('/admin/platform-settings') }}">{{ __('Platform settings') }}</a>
                </nav>
            </div>

            <div class="ov-kpis">
                <a class="ov-kpi" href="{{ url('/admin/agencies') }}">
                    <div class="ov-kpi-row">
                        <div>
                            <div class="ov-lbl">{{ __('Agencies') }}</div>
                            <div class="ov-num"><bdi dir="ltr">{{ $w($agenciesTotal) }}</bdi></div>
                            <div class="ov-note">{{ __('Active') }}: <bdi dir="ltr">{{ $w($agenciesActive) }}</bdi></div>
                        </div>
                        <svg width="44" height="44" viewBox="0 0 36 36" role="img" aria-label="{{ $w($activePct) }}%">
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--ov-track)" stroke-width="3.8"/>
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="var(--ov-acc)" stroke-width="3.8" stroke-dasharray="{{ $w($activePct) }}, 100" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="ov-bar" aria-hidden="true"><i style="width:{{ $w($activePct) }}%"></i></div>
                </a>

                <a class="ov-kpi" href="{{ url('/admin/waitlist') }}" style="border-top-color:#14b8a6">
                    <div>
                        <div class="ov-lbl">{{ __('Agency waitlist') }}</div>
                        <div class="ov-num"><bdi dir="ltr">{{ $w($waitlistTotal) }}</bdi></div>
                        <div class="ov-note">{{ __('Consent') }}: <bdi dir="ltr">{{ $w($waitlistConsented) }} ({{ $w($waitlistConsentPct) }}%)</bdi></div>
                    </div>
                    <div class="ov-bar" role="img" aria-label="{{ $w($waitlistConsentPct) }}%"><i style="width:{{ $w($waitlistConsentPct) }}%;background:#14b8a6"></i></div>
                </a>

                <a class="ov-kpi" href="{{ url('/admin/partner-applications') }}">
                    <div class="ov-kpi-row">
                        <div>
                            <div class="ov-lbl">{{ __('Partner applications') }}</div>
                            <div class="ov-num"><bdi dir="ltr">{{ $w($partnersTotal) }}</bdi></div>
                            <div class="ov-note"><bdi dir="ltr">/partners</bdi></div>
                        </div>
                        <span class="ov-ico" aria-hidden="true">★</span>
                    </div>
                </a>

                <a class="ov-kpi" href="{{ url('/admin/network-requests') }}" style="border-top-color:#1e293b">
                    <div class="ov-kpi-row">
                        <div>
                            <div class="ov-lbl">{{ __('Network requests') }}</div>
                            <div class="ov-num"><bdi dir="ltr">{{ $w($networkRequestsTotal) }}</bdi></div>
                            <div class="ov-note" style="color:var(--ov-muted)"><bdi dir="ltr">#127</bdi></div>
                        </div>
                        <span class="ov-ico" aria-hidden="true">⇄</span>
                    </div>
                </a>

                <a class="ov-kpi" href="{{ url('/admin/platform-settings') }}" style="border-top-color:#0f172a">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
                        <span class="ov-lbl">{{ __('Self-signup') }}</span>
                        <span class="ov-pill {{ $signupOpen ? 'ov-open' : 'ov-closed' }}">{{ $signupOpen ? __('Open') : __('Invite only') }}</span>
                    </div>
                    <div class="ov-num" style="font-size:15px;margin-top:8px">{{ __('Held-back modules off') }}: <bdi dir="ltr">{{ $w($offModulesCount) }}/{{ $w($totalModulesCount) }}</bdi></div>
                    <div class="ov-note" style="color:var(--ov-muted)">{{ __('Global modules on') }}: <bdi dir="ltr">{{ $w($enabledModulesCount ?? 0) }}</bdi> · {{ __('Preview') }}: <bdi dir="ltr">{{ $w($previewCount ?? 0) }}</bdi></div>
                    <div class="ov-bar" role="img" aria-label="{{ $w($offPct) }}%"><i style="width:{{ $w($offPct) }}%;background:#0f172a"></i></div>
                </a>
            </div>
        </div>

        <div class="ov-cards">
            @foreach ($tables as [$heading, $href, $rows, $empty, $cells])
                <section class="ov-box ov-card">
                    <div class="ov-card-h">
                        <strong style="font-size:13.5px">{{ $heading }}</strong>
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
                                            <td><bdi dir="ltr">{{ $w($c) }}</bdi></td>
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
