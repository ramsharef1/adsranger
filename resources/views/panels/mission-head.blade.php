@if (\App\Support\MissionShell::on())
{{-- Mission control shell (D-312): one dark look for everyone who has it on, whatever the browser theme says. --}}
<script data-mission-theme>
    (() => {
        const h = document.documentElement;
        const keep = () => { if (! h.classList.contains('dark')) h.classList.add('dark'); if (! h.classList.contains('ar-mission')) h.classList.add('ar-mission'); };
        keep();
        new MutationObserver(keep).observe(h, { attributes: true, attributeFilter: ['class'] });
    })();
</script>
<style data-mission-tokens>
html.ar-mission{--mc-bg:#04070D;--mc-p1:#0A111D;--mc-p2:#0E1727;--mc-ln:#1B2638;--mc-tx:#E5EDF7;--mc-mu:#8494AB;--mc-ac:#2DD4BF;--mc-rail:64px;--mc-dock:52px;color-scheme:dark}
html.ar-mission .fi-body{background:var(--mc-bg);color:var(--mc-tx)}
html.ar-mission .fi-topbar,html.ar-mission .fi-topbar nav{background:#070C15;box-shadow:none}
html.ar-mission .fi-topbar{border-bottom:1px solid var(--mc-ln)}
html.ar-mission .fi-sidebar,html.ar-mission .fi-sidebar-header{background:var(--mc-p1)}
html.ar-mission .fi-sidebar{border-inline-end:1px solid var(--mc-ln)}
html.ar-mission .fi-sidebar-item-label{color:#B6C3D6}
html.ar-mission .fi-sidebar-group-label{color:var(--mc-mu)}
html.ar-mission .fi-sidebar-item.fi-active>.fi-sidebar-item-btn{background:rgba(45,212,191,.14);box-shadow:inset -3px 0 0 var(--mc-ac)}
html.ar-mission[dir=ltr] .fi-sidebar-item.fi-active>.fi-sidebar-item-btn{box-shadow:inset 3px 0 0 var(--mc-ac)}
html.ar-mission .fi-sidebar-item.fi-active .fi-sidebar-item-label{color:var(--mc-ac);font-weight:600}
html.ar-mission .fi-section,html.ar-mission .fi-ta-ctn,html.ar-mission .fi-wi-stats-overview-stat,html.ar-mission .fi-dropdown-panel,html.ar-mission .fi-modal-window{background:linear-gradient(180deg,var(--mc-p2),var(--mc-p1));border:1px solid var(--mc-ln);box-shadow:none;--tw-ring-color:transparent}
html.ar-mission .fi-section{border-radius:16px}
html.ar-mission .fi-header-heading{color:var(--mc-tx)}
html.ar-mission .fi-input-wrp{background:#0B1422;--tw-ring-color:var(--mc-ln)}
html.ar-mission .ar-areas{display:none}
html.ar-mission .ar-ribbon{background:#070C15;border-bottom:1px solid var(--mc-ln);color:#B6C3D6}
html.ar-mission .ar-ribbon a{color:var(--mc-ac)}
html.ar-mission .ar-switch-btn{background:#0F1929;border-color:var(--mc-ln);color:var(--mc-tx)}
html.ar-mission .ar-switch-list{background:var(--mc-p2);border-color:var(--mc-ln)}
html.ar-mission .ar-switch-list a:hover,html.ar-mission .ar-switch-list a[aria-current]{background:rgba(45,212,191,.14);color:var(--mc-ac)}
html.ar-mission .ar-bottom{background:#070C15;border-color:var(--mc-ln)}
html.ar-mission .ar-bottom [aria-current]{color:var(--mc-ac);box-shadow:inset 0 3px 0 var(--mc-ac)}
html.ar-mission :focus-visible{outline:2px solid var(--mc-ac);outline-offset:2px}
/* Mode switch in the top bar */
.mc-mode{display:inline-flex;border:1px solid var(--mc-ln);border-radius:999px;padding:3px;background:#0B1422;margin-inline-end:8px}
.mc-mode button{min-height:36px;padding:0 14px;border:0;border-radius:999px;background:transparent;color:var(--mc-mu);font:inherit;font-size:13px;cursor:pointer;white-space:nowrap}
.mc-mode button[aria-pressed=true]{background:var(--mc-ac);color:#04221F;font-weight:700}
/* Icon rail: areas, then add-ons */
.mc-rail{display:none}
.mc-dock{display:none}
.mc-next-btn{display:none}
@media (min-width:1024px){
  html.ar-mission .mc-rail{display:flex;flex-direction:column;gap:4px;align-items:center;position:fixed;top:0;bottom:var(--mc-dock);inset-inline-start:0;width:var(--mc-rail);z-index:31;padding:10px 0;background:#070C15;border-inline-end:1px solid var(--mc-ln);overflow-y:auto}
  .mc-rail a{position:relative;width:48px;min-height:52px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;border-radius:12px;color:var(--mc-mu);text-decoration:none;font-size:10.5px;line-height:1.1;text-align:center}
  .mc-rail a:hover{background:#111C2E;color:var(--mc-tx)}
  .mc-rail a[aria-current]{background:rgba(45,212,191,.16);color:var(--mc-ac);box-shadow:inset 0 0 0 1px rgba(45,212,191,.45)}
  .mc-rail a[data-locked]{opacity:.55}
  .mc-rail .mc-lock{position:absolute;top:4px;inset-inline-end:4px}
  .mc-rail hr{width:32px;border:0;border-top:1px solid var(--mc-ln);margin:6px 0}
  html.ar-mission .fi-body{padding-inline-start:var(--mc-rail);padding-bottom:calc(var(--mc-dock) + 8px)}
  html.ar-mission .fi-sidebar{height:calc(100dvh - 4rem - var(--mc-dock))}
  html.ar-mission .mc-dock{display:flex;align-items:center;gap:8px;position:fixed;inset-inline:0;bottom:0;height:var(--mc-dock);z-index:26;padding:0 14px;background:#070C15;border-top:1px solid var(--mc-ln);font-size:13px;color:#B6C3D6;overflow:hidden}
  .mc-dock a{min-height:36px;display:inline-flex;align-items:center;gap:8px;padding:0 12px;border:1px solid var(--mc-ln);border-radius:10px;color:var(--mc-tx);text-decoration:none;white-space:nowrap;max-width:200px}
  .mc-dock a:hover{border-color:var(--mc-ac)}
  .mc-dock a bdi{overflow:hidden;text-overflow:ellipsis}
  .mc-dock-status{margin-inline-start:auto;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  html.ar-mission .mc-next-btn{display:inline-flex;align-items:center;gap:8px;position:fixed;inset-inline-end:16px;bottom:calc(var(--mc-dock) + 14px);z-index:27;min-height:44px;padding:0 16px;border-radius:999px;border:1px solid rgba(45,212,191,.5);background:#0E1727;color:var(--mc-ac);font:inherit;font-size:13.5px;font-weight:600;cursor:pointer}
}
.mc-next{position:fixed;inset-inline-end:16px;bottom:calc(var(--mc-dock) + 68px);z-index:28;width:min(340px,calc(100vw - 32px));max-height:70vh;overflow:auto;background:linear-gradient(180deg,var(--mc-p2),var(--mc-p1));border:1px solid var(--mc-ln);border-radius:16px;padding:16px;color:var(--mc-tx);font-size:13.5px}
.mc-next h2{margin:0 0 8px;font-size:15px;font-weight:700}
.mc-next .mc-mu{color:var(--mc-mu);font-size:12.5px}
.mc-next .mc-card{background:#0B1422;border:1px solid #131D2D;border-radius:12px;padding:12px;margin-bottom:12px}
.mc-next .mc-go{display:flex;align-items:center;justify-content:center;min-height:44px;margin-top:10px;border-radius:10px;background:var(--mc-ac);color:#04221F;font-weight:700;text-decoration:none}
.mc-next ul{list-style:none;margin:0;padding:0}
.mc-next li a{display:flex;align-items:center;gap:10px;min-height:44px;color:var(--mc-tx);text-decoration:none;border-top:1px solid #131D2D}
.mc-next li:first-child a{border-top:0}
.mc-next li a:hover{color:var(--mc-ac)}
.mc-chip{display:inline-flex;align-items:center;font-size:11.5px;font-weight:600;border-radius:999px;padding:2px 9px;white-space:nowrap}
.mc-critical{background:#3B1218;color:#FCA5A5}.mc-warning{background:#3A2A0B;color:#FCD34D}.mc-info{background:#12264A;color:#93C5FD}.mc-ok{background:#0E2F1C;color:#86EFAC}
.mc-dot{width:9px;height:9px;border-radius:999px;flex:none}
.mc-sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
</style>
@endif
