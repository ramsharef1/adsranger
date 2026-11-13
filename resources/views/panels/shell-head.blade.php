@if (\App\Support\Shell::on())
{{-- PX-02 (D-302): design tokens from UI/UX's shell v1 / Today v2 boards. Light theme only; dark keeps Filament's own until UI/UX draws it. --}}
<style data-shell-tokens>
:root{
  --ar-bg:#F5F8F7;--ar-surface:#FFFFFF;--ar-border:#DDE5E4;--ar-line:#EEF2F1;--ar-control:#C5D1CF;
  --ar-text:#0F1F1E;--ar-text-2:#33423F;--ar-muted:#556664;
  --ar-accent:#0F766E;--ar-accent-strong:#0B5C56;--ar-accent-soft:#E6F4F2;
  --ar-ribbon:#0F2B29;--ar-ribbon-text:#E9F3F1;--ar-ribbon-link:#5EEAD4;
  --ar-critical-bg:#FEE4E2;--ar-critical:#912018;--ar-warning-bg:#FFF1D6;--ar-warning:#7A4B00;
  --ar-info-bg:#E0EDFF;--ar-info:#1849A9;--ar-ok-bg:#DCFAE6;--ar-ok:#085D3A;--ar-neutral-bg:#EEF1F1;--ar-neutral:#44524F;
  --ar-radius-control:10px;--ar-radius-card:14px;--ar-radius-pill:999px;--ar-touch:44px;--ar-sidebar:248px;
}
html:not(.dark) .fi-body{background:var(--ar-bg);color:var(--ar-text)}
html:not(.dark) .fi-topbar{background:var(--ar-surface);border-bottom:1px solid var(--ar-border);box-shadow:none}
html:not(.dark) .fi-main-sidebar{background:var(--ar-surface);border-inline-end:1px solid var(--ar-border)}
.fi-sidebar-item-btn{min-height:var(--ar-touch);border-radius:var(--ar-radius-control)}
html:not(.dark) .fi-sidebar-item-label{color:var(--ar-text-2)}
html:not(.dark) .fi-sidebar-item.fi-active>.fi-sidebar-item-btn{background:var(--ar-accent-soft);box-shadow:inset -3px 0 0 var(--ar-accent)}
html[dir=ltr]:not(.dark) .fi-sidebar-item.fi-active>.fi-sidebar-item-btn{box-shadow:inset 3px 0 0 var(--ar-accent)}
html:not(.dark) .fi-sidebar-item.fi-active .fi-sidebar-item-label{color:var(--ar-accent-strong);font-weight:600}
html:not(.dark) .fi-sidebar-group-label{color:var(--ar-muted);font-size:13px;font-weight:600}
html:not(.dark) .fi-section{border-radius:var(--ar-radius-card);border:1px solid var(--ar-border);box-shadow:none}
.fi-btn{border-radius:var(--ar-radius-control)}
:focus-visible{outline:2px solid var(--ar-accent);outline-offset:2px}
/* Area links in the header (PX-01); phones use the bottom bar instead. */
.ar-areas{display:flex;gap:4px;flex-wrap:wrap;margin-inline-start:16px}
.ar-areas a{min-height:var(--ar-touch);display:inline-flex;align-items:center;padding:0 14px;border-radius:var(--ar-radius-control);color:var(--ar-text-2);font-weight:500;text-decoration:none;white-space:nowrap}
.dark .ar-areas a{color:#CBD5E1}
.ar-areas a[aria-current]{background:var(--ar-accent-soft);color:var(--ar-accent-strong);font-weight:600}
.dark .ar-areas a[aria-current]{background:rgba(20,184,166,.18);color:#5EEAD4}
.ar-ribbon{background:var(--ar-ribbon);color:var(--ar-ribbon-text);display:flex;flex-wrap:wrap;align-items:center;gap:4px 24px;padding:4px 24px;font-size:13px}
.ar-ribbon a{color:var(--ar-ribbon-link);font-weight:600}
.ar-ribbon-status{margin-inline-start:auto;font-weight:600}
.ar-switch{position:relative}
.ar-switch-btn{min-height:var(--ar-touch);display:inline-flex;align-items:center;gap:6px;padding:0 14px;border:1px solid var(--ar-control);border-radius:var(--ar-radius-control);background:var(--ar-surface);color:var(--ar-text);font:inherit;cursor:pointer;max-width:220px}
.ar-switch-btn bdi{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dark .ar-switch-btn{background:transparent;color:#E2E8F0;border-color:rgba(148,163,184,.35)}
.ar-switch-list{position:absolute;inset-inline-end:0;top:calc(100% + 6px);z-index:40;min-width:240px;max-height:60vh;overflow:auto;margin:0;padding:6px;list-style:none;background:var(--ar-surface);border:1px solid var(--ar-border);border-radius:var(--ar-radius-card);box-shadow:0 8px 24px rgba(15,31,30,.12)}
.dark .ar-switch-list{background:#0B1020;border-color:rgba(148,163,184,.25)}
.ar-switch-list a{min-height:var(--ar-touch);display:flex;align-items:center;gap:10px;padding:0 10px;border-radius:var(--ar-radius-control);color:inherit;text-decoration:none}
.ar-switch-list a:hover,.ar-switch-list a[aria-current]{background:var(--ar-accent-soft);color:var(--ar-accent-strong)}
.ar-dot{width:8px;height:8px;border-radius:999px;display:inline-block;flex:none}
[x-cloak]{display:none!important}
.ar-bottom{display:none}
@media (max-width:900px){
  .ar-areas{display:none}
  .ar-ribbon{padding:4px 12px;font-size:12px}
  .ar-bottom{display:grid;grid-template-columns:repeat(5,1fr);position:fixed;inset-inline:0;bottom:0;z-index:30;background:var(--ar-surface);border-top:1px solid var(--ar-border);padding-bottom:env(safe-area-inset-bottom)}
  .dark .ar-bottom{background:#0B1020;border-color:rgba(148,163,184,.25)}
  .ar-bottom a,.ar-bottom button{min-height:56px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;font:inherit;font-size:12px;color:var(--ar-text-2);text-decoration:none;background:none;border:0;cursor:pointer;position:relative}
  .dark .ar-bottom a,.dark .ar-bottom button{color:#CBD5E1}
  .ar-bottom [aria-current]{color:var(--ar-accent-strong);font-weight:700;box-shadow:inset 0 3px 0 var(--ar-accent)}
  .ar-bottom .ar-count{position:absolute;top:6px;inset-inline-start:calc(50% + 6px);background:var(--ar-critical-bg);color:var(--ar-critical);border-radius:var(--ar-radius-pill);padding:0 6px;font-size:11px;font-weight:700}
  .fi-body{padding-bottom:64px}
}
@if (\App\Support\Shell::density() === 'compact')
/* PX-04: compact rows on desktop only; phones keep 44 px targets. */
@media (min-width:901px){
  .fi-sidebar-item-btn{min-height:34px}
  .fi-ta-cell,.fi-ta-header-cell{padding-top:4px!important;padding-bottom:4px!important}
  .fi-section-content{padding:12px!important}
}
@endif
</style>
@endif
