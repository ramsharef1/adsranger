@if (\App\Support\MissionShell::onAdmin())
{{-- Mission control look for /admin (D-314): the same dark tokens as the agency app, on top of the admin shell's own rules. --}}
<script data-mission-theme>
    (() => {
        const h = document.documentElement;
        const keep = () => { if (! h.classList.contains('dark')) h.classList.add('dark'); if (! h.classList.contains('ar-mission')) h.classList.add('ar-mission'); };
        keep();
        new MutationObserver(keep).observe(h, { attributes: true, attributeFilter: ['class'] });
    })();
</script>
<style data-mission-admin-tokens>
html.ar-mission{--mc-bg:#04070D;--mc-p1:#0A111D;--mc-p2:#0E1727;--mc-ln:#1B2638;--mc-tx:#E5EDF7;--mc-mu:#8494AB;--mc-ac:#2DD4BF;color-scheme:dark;
  --ov-card:#0E1727;--ov-soft:#0B1422;--ov-line:#1B2638;--ov-ink:#E5EDF7;--ov-muted:#8494AB;--ov-track:#17202F}
html.ar-mission .fi-body{background-color:var(--mc-bg) !important;color:var(--mc-tx)}
html.ar-mission .fi-topbar,html.ar-mission .fi-topbar nav{background:#070C15 !important;box-shadow:none}
html.ar-mission .fi-topbar{border-bottom:1px solid var(--mc-ln) !important}
html.ar-mission .fi-sidebar,html.ar-mission .fi-main-sidebar,html.ar-mission .fi-sidebar-header{background:var(--mc-p1) !important;border-color:var(--mc-ln) !important}
html.ar-mission .fi-sidebar-item-active > a,html.ar-mission .fi-sidebar-item.fi-active > .fi-sidebar-item-btn,html.ar-mission .fi-sidebar-item > a:hover,html.ar-mission .fi-sidebar-item > .fi-sidebar-item-btn:hover{background:rgba(45,212,191,.14) !important}
html.ar-mission .fi-sidebar-item.fi-active .fi-sidebar-item-label,html.ar-mission .fi-sidebar-item.fi-active .fi-sidebar-item-icon{color:var(--mc-ac) !important}
html.ar-mission .fi-section,html.ar-mission .fi-ta-ctn,html.ar-mission .fi-wi-stats-overview-stat,html.ar-mission .fi-dropdown-panel,html.ar-mission .fi-modal-window{background:linear-gradient(180deg,var(--mc-p2),var(--mc-p1)) !important;border:1px solid var(--mc-ln) !important;box-shadow:none !important;--tw-ring-color:transparent}
html.ar-mission .fi-input-wrp{background:#0B1422;--tw-ring-color:var(--mc-ln)}
html.ar-mission .fi-header-heading{color:var(--mc-tx)}
html.ar-mission .ar-adm-ribbon{background:#070C15;border-bottom:1px solid var(--mc-ln)}
html.ar-mission .ar-adm-pill{background:#0E1727;border-color:var(--mc-ln)}
html.ar-mission .ar-adm-areas a[aria-current="page"]{background:rgba(45,212,191,.16);border-color:rgba(45,212,191,.45);color:var(--mc-ac) !important;box-shadow:none}
html.ar-mission .ar-adm-areas a:hover{background:#111C2E}
html.ar-mission .ar-adm-bottom{background:#070C15;border-color:var(--mc-ln)}
html.ar-mission .ov-box{box-shadow:none}
html.ar-mission :focus-visible{outline:2px solid var(--mc-ac);outline-offset:2px}
</style>
@endif
