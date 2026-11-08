{{-- Shared layout for the free tools: inputs pane + live result card (two columns from 1024 px), 44 px targets. --}}
<style>
.tl{display:grid;gap:20px;padding-top:24px}
@media (min-width:1024px){.tl{grid-template-columns:1fr 1fr;align-items:start}}
.tl label{display:flex;align-items:flex-end;min-height:44px}
.tl-hint{font-size:13px;color:var(--muted);margin:4px 0 0}
.tl-err{font-size:14px;color:var(--bad);margin:8px 0 0}
.tl-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
.tl-out{border:1px solid var(--line);border-radius:12px;padding:16px;background:var(--card)}
.tl-out h3{font-size:16px;margin:14px 0 6px}
.tl-out h3:first-child{margin-top:0}
.tl-list{list-style:none;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:6px}
.tl-list li{padding:4px 10px;border:1px solid var(--line);border-radius:999px;font-size:14px}
.tl-big{font-size:26px;font-weight:700;color:var(--acc);margin:0}
.tl-kv{list-style:none;padding:0;margin:0;display:grid;gap:10px}
.tl-kv li{display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid var(--line);padding-bottom:8px}
.tl-kv strong{font-variant-numeric:tabular-nums}
.tl-code{display:block;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--bg);overflow-wrap:anywhere;font-size:14px}
</style>
