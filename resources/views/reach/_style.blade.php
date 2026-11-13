<style>
:root{--bg:#ffffff;--fg:#0f172a;--muted:#475569;--line:rgba(127,127,127,.25);--accent:#0f766e;--accent-fg:#ffffff;--warn-bg:rgba(245,158,11,.12);--warn-line:rgba(245,158,11,.45)}
@media (prefers-color-scheme:dark){:root{--bg:#0f172a;--fg:#e2e8f0;--muted:#94a3b8;--accent:#14b8a6;--accent-fg:#042f2e}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.6 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif}
.wrap{max-width:980px;margin:0 auto;padding:16px}h1{font-size:28px;line-height:1.3;margin:12px 0}h2{font-size:20px;margin:24px 0 8px}
.muted{color:var(--muted)}.card{border:1px solid var(--line);border-radius:12px;padding:16px;margin:12px 0}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:8px 16px;border-radius:10px;border:0;background:var(--accent);color:var(--accent-fg);text-decoration:none;font:inherit;cursor:pointer}
.btn.ghost{background:none;border:1px solid var(--accent);color:inherit}
.note{padding:12px 14px;border-radius:10px;background:var(--warn-bg);border:1px solid var(--warn-line)}
input,select,textarea{width:100%;min-height:44px;font:inherit;font-size:16px;padding:8px 10px;border-radius:8px;border:1px solid var(--line);background:transparent;color:inherit}
label{display:block;margin:8px 0}nav.tabs{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}nav.tabs a{padding:8px 12px;min-height:44px;display:inline-flex;align-items:center;border-radius:8px;border:1px solid var(--line);color:inherit;text-decoration:none}
nav.tabs a[aria-current=page]{background:var(--accent);color:var(--accent-fg);border-color:var(--accent)}
table{width:100%;border-collapse:collapse;font-size:14px}td,th{padding:6px;text-align:start;border-top:1px solid var(--line);vertical-align:top}
.big{font-size:26px;font-weight:700}code,pre{font-family:ui-monospace,Menlo,monospace;font-size:13px}pre{white-space:pre-wrap;overflow-wrap:anywhere;padding:10px;border:1px solid var(--line);border-radius:8px}
.err{color:#dc2626}
</style>
