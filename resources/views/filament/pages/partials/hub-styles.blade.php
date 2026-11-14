{{-- The hub pages (agency operations, intelligence, organic social) were written with utility class names the panel's
     stylesheet does not ship, so they rendered unstyled. This sheet gives those names their meaning inside .hub only,
     in the light, dark and Mission control looks. --}}
<style data-hub-styles>
.hub{--h-card:#FFFFFF;--h-soft:#F8FAFC;--h-line:#E2E8F0;--h-control:#CBD5E1;--h-text:#0F172A;--h-2:#475569;--h-mu:#64748B;--h-ac:#0F766E;--h-ac-soft:#F0FDFA;--h-field:#FFFFFF;font-size:14px;color:var(--h-text)}
.dark .hub{--h-card:#111827;--h-soft:rgba(31,41,55,.6);--h-line:#1F2937;--h-control:#374151;--h-text:#F9FAFB;--h-2:#D1D5DB;--h-mu:#9CA3AF;--h-ac:#2DD4BF;--h-ac-soft:rgba(45,212,191,.14);--h-field:#1F2937}
html.ar-mission .hub{--h-card:#0E1727;--h-soft:#0B1422;--h-line:#1B2638;--h-control:#1B2638;--h-text:#E5EDF7;--h-2:#B6C3D6;--h-mu:#8494AB;--h-field:#0B1422}
.hub .grid{display:grid}.hub .flex{display:flex}.hub .block{display:block}
.hub .grid-cols-1{grid-template-columns:minmax(0,1fr)}.hub .grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.hub .grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}
@media (min-width:768px){.hub .md\:grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.hub .md\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}.hub .md\:grid-cols-4{grid-template-columns:repeat(4,minmax(0,1fr))}.hub .md\:col-span-2{grid-column:span 2/span 2}}
.hub .gap-3{gap:12px}.hub .gap-4{gap:16px}.hub .gap-6{gap:24px}
.hub .items-center{align-items:center}.hub .justify-between{justify-content:space-between}
.hub .space-y-2>*+*{margin-top:8px}.hub .space-y-3>*+*{margin-top:12px}.hub .space-y-4>*+*{margin-top:16px}.hub .space-y-6>*+*{margin-top:24px}
.hub.space-y-4>*+*{margin-top:16px}.hub.space-y-6>*+*{margin-top:24px}
.hub .mt-1{margin-top:4px}.hub .mt-2{margin-top:8px}.hub .mb-3{margin-bottom:12px}
.hub .p-3{padding:12px}.hub .p-4{padding:16px}.hub .p-5{padding:20px}.hub .pt-3{padding-top:12px}
.hub .px-2\.5{padding-inline:10px}.hub .px-3{padding-inline:12px}.hub .px-4{padding-inline:16px}.hub .py-1{padding-block:4px}.hub .py-1\.5{padding-block:6px}.hub .py-2{padding-block:8px}
.hub .w-full{width:100%}.hub .max-w-xs{max-width:20rem}.hub .truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hub .rounded-lg{border-radius:10px}.hub .rounded-xl{border-radius:16px}.hub .rounded-full{border-radius:999px}
.hub .border{border:1px solid var(--h-line)}.hub .border-t{border-top:1px solid var(--h-line)}
.hub .text-xs{font-size:12.5px;line-height:1.5}.hub .text-sm{font-size:14px;line-height:1.5}.hub .text-base{font-size:16px}.hub .text-lg{font-size:18px}.hub .text-2xl{font-size:26px;line-height:1.2}
.hub .font-medium{font-weight:500}.hub .font-semibold{font-weight:600}.hub .font-bold{font-weight:700}
.hub .bg-white{background:var(--h-card)}.hub .bg-gray-50,.hub .bg-gray-100{background:var(--h-soft)}.hub .bg-teal-50{background:var(--h-ac-soft)}
.hub .text-gray-900{color:var(--h-text)}.hub .text-gray-600{color:var(--h-2)}.hub .text-gray-500,.hub .text-gray-400{color:var(--h-mu)}.hub .text-teal-600,.hub .text-teal-700{color:var(--h-ac)}
.hub .bg-teal-600{background:#0F766E;color:#FFFFFF}.hub .hover\:bg-teal-500:hover{background:#0D9488}.hub .text-white{color:#FFFFFF}
.hub h3{margin:0}.hub p{margin:0}
.hub input:not([type=checkbox]):not([type=radio]),.hub select,.hub textarea{min-height:44px;padding:8px 12px;border:1px solid var(--h-control);border-radius:10px;background:var(--h-field);color:var(--h-text);font:inherit;font-size:14px;box-sizing:border-box;max-width:100%}
.hub button[type=submit],.hub .hub-btn{min-height:44px;padding:0 16px;border:0;border-radius:10px;background:#0F766E;color:#FFFFFF;font:inherit;font-size:14px;font-weight:600;cursor:pointer}
.hub button[type=submit]:hover,.hub .hub-btn:hover{background:#0D9488}
.hub label{color:var(--h-2)}
.hub .hub-chip{display:inline-flex;align-items:center;font-size:12px;font-weight:600;border-radius:999px;padding:2px 10px;white-space:nowrap;background:var(--h-soft);color:var(--h-2)}
.hub .hub-ok{background:#DCFCE7;color:#166534}.hub .hub-warn{background:#FEF3C7;color:#92400E}.hub .hub-bad{background:#FEE2E2;color:#991B1B}
.dark .hub .hub-ok{background:#0E2F1C;color:#86EFAC}.dark .hub .hub-warn{background:#3A2A0B;color:#FCD34D}.dark .hub .hub-bad{background:#3B1218;color:#FCA5A5}
.hub .hub-note{color:var(--h-mu);font-size:12.5px}
</style>
