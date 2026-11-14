{{-- E2-068: PX dense command-center tokens for /admin. Slate #0f172a/#1e293b chrome + crisp #f8fafc/#ffffff canvas + Teal #0d9488/#14b8a6 accents. --}}
<style id="ar-admin-signature" data-admin-shell-tokens data-admin-density="{{ \App\Support\AdminShell::density() }}">
:root{
  --adm-chrome:#0f172a;--adm-chrome-2:#1e293b;--adm-canvas:#f8fafc;--adm-surface:#ffffff;
  --adm-border:#e2e8f0;--adm-ink:#0f172a;--adm-muted:#475569;
  --adm-teal:#0d9488;--adm-teal-bright:#14b8a6;--adm-teal-strong:#0f766e;--adm-teal-soft:#ccfbf1;--adm-teal-ice:#5eead4;
  --adm-touch:44px;--adm-radius:10px;--adm-radius-card:14px;
}
.fi-body{background-color:#f8fafc !important;color:#0f172a}
.dark .fi-body{background-color:#0f172a !important;color:#f8fafc}
.fi-sidebar,.fi-main-sidebar{background:#0f172a !important;border-inline-end:1px solid #1e293b !important}
.fi-sidebar-header{background:#0f172a !important;border-bottom:1px solid #1e293b !important}
.fi-sidebar-header *{color:#ffffff !important}
.fi-sidebar-group-label{color:#94a3b8 !important;font-weight:700 !important;text-transform:uppercase;letter-spacing:.05em;font-size:11px !important}
.fi-sidebar-item-btn{min-height:var(--adm-touch);border-radius:var(--adm-radius)}
.fi-sidebar-item-label{color:#cbd5e1 !important;font-weight:500 !important}
.fi-sidebar-item-icon{color:#94a3b8 !important}
.fi-sidebar-item-active > a,.fi-sidebar-item.fi-active > .fi-sidebar-item-btn,.fi-sidebar-item > a:hover,.fi-sidebar-item > .fi-sidebar-item-btn:hover{background:#1e293b !important;box-shadow:inset 3px 0 0 #14b8a6}
html[dir=rtl] .fi-sidebar-item-active > a,html[dir=rtl] .fi-sidebar-item.fi-active > .fi-sidebar-item-btn{box-shadow:inset -3px 0 0 #14b8a6}
.fi-sidebar-item-active .fi-sidebar-item-label,.fi-sidebar-item.fi-active .fi-sidebar-item-label{color:#ffffff !important;font-weight:700 !important}
.fi-sidebar-item-active .fi-sidebar-item-icon,.fi-sidebar-item.fi-active .fi-sidebar-item-icon{color:#14b8a6 !important}
.fi-topbar{background:#0f172a !important;border-bottom:1px solid #1e293b !important;color:#f8fafc !important}
.fi-topbar .ar-admin-label{color:#cbd5e1 !important;font-size:12px;font-weight:600;white-space:nowrap}
.fi-topbar > nav > div > button,.fi-topbar .fi-icon-btn{color:#f8fafc !important;min-width:var(--adm-touch);min-height:var(--adm-touch)}
.fi-section{background:#ffffff;border-radius:var(--adm-radius-card) !important;box-shadow:0 2px 10px -2px rgba(15,23,42,.06) !important;border:1px solid #e2e8f0 !important}
.dark .fi-section{background:#1e293b !important;border-color:#334155 !important}
.fi-ta-table,.adm-dense-table{font-variant-numeric:tabular-nums}
:focus-visible{outline:2px solid #14b8a6;outline-offset:2px}

/* PX Area Switcher Rail inside /admin topbar */
.ar-adm-areas{display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-inline-start:12px}
.ar-adm-areas a{min-height:var(--adm-touch);display:inline-flex;align-items:center;gap:6px;padding:0 12px;border-radius:var(--adm-radius);color:#cbd5e1 !important;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;border:1px solid transparent;transition:background .12s ease,color .12s ease}
.ar-adm-areas a:hover{background:#1e293b;color:#ffffff !important}
.ar-adm-areas a[aria-current="page"]{background:#1e293b;border-color:#0d9488;color:#5eead4 !important;box-shadow:inset 0 -2px 0 #14b8a6}
.ar-adm-count{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:18px;padding:0 5px;border-radius:999px;background:rgba(148,163,184,.18);color:#e2e8f0 !important;font-size:11px;font-weight:700;font-variant-numeric:tabular-nums}
.ar-adm-areas a[aria-current="page"] .ar-adm-count{background:rgba(20,184,166,.22);color:#5eead4 !important}

/* PX Top Live Telemetry Ribbon */
.ar-adm-ribbon{background:#0f172a;border-bottom:1px solid #1e293b;color:#e2e8f0;display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;padding:6px 20px;font-size:12.5px}
.ar-adm-pill{min-height:var(--adm-touch);display:inline-flex;align-items:center;gap:6px;padding:0 11px;border-radius:var(--adm-radius);background:#1e293b;border:1px solid rgba(148,163,184,.2);color:#f8fafc;text-decoration:none;white-space:nowrap;font-variant-numeric:tabular-nums}
.ar-adm-pill:hover{border-color:#14b8a6;color:#ffffff}
.ar-adm-pill strong{color:#ffffff;font-weight:700}
.ar-adm-dot{width:8px;height:8px;border-radius:999px;display:inline-block;flex:none;background:#14b8a6}
.ar-adm-dot--pending{background:#94a3b8}
.ar-adm-dot--warn{background:#ef4444}
.ar-adm-jump{margin-inline-start:auto;min-height:var(--adm-touch);display:inline-flex;align-items:center;gap:6px;padding:0 14px;border-radius:var(--adm-radius);background:#0d9488;border:1px solid #14b8a6;color:#ffffff !important;font-weight:700;text-decoration:none;white-space:nowrap}
.ar-adm-jump:hover{background:#0f766e}

/* Mobile 360px layout + phone bottom area rail (>=44px touch targets) */
.ar-adm-bottom{display:none}
.fi-simple-layout .ar-adm-bottom{display:none !important}
@media (max-width:900px){
  .ar-adm-areas{display:none}
  .ar-adm-ribbon{padding:6px 12px;gap:6px 8px;font-size:12px}
  .ar-adm-pill{white-space:normal;line-height:1.3;padding:6px 10px}
  .ar-adm-jump{margin-inline-start:0}
  .ar-adm-bottom{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));position:fixed;inset-inline:0;bottom:0;z-index:30;background:#0f172a;border-top:1px solid #1e293b;padding-bottom:env(safe-area-inset-bottom)}
  .ar-adm-bottom a,.ar-adm-bottom button{min-height:52px;min-width:44px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:4px 2px;font:inherit;font-size:11.5px;font-weight:600;color:#cbd5e1;text-decoration:none;background:none;border:0;cursor:pointer}
  .ar-adm-bottom [aria-current="page"]{color:#5eead4;font-weight:700;box-shadow:inset 0 3px 0 #14b8a6}
  .fi-body:not(.fi-simple-layout){padding-bottom:64px}
}
@if (\App\Support\AdminShell::density() === 'compact')
/* PX-04 for /admin: high-density rows on desktop (>=901px); phones keep >=44px touch targets. */
@media (min-width:901px){
  .fi-sidebar-item-btn{min-height:34px}
  .fi-ta-cell,.fi-ta-header-cell{padding-top:4px !important;padding-bottom:4px !important}
  .fi-section-content{padding:12px !important}
  .adm-dense-table td,.adm-dense-table th,.ov-card td{padding-top:4px !important;padding-bottom:4px !important}
  .ov-kpi{padding:10px 12px !important}
  .ov-top{padding:14px 18px !important}
}
@endif
</style>
