    <style>
        :root { --bg: #f8fafc; --fg: #0f172a; --muted: #475569; --card: #ffffff; --line: #e2e8f0; --accent: #0F766E; --up: #15803d; --down: #b91c1c; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b1020; --fg: #f1f5f9; --muted: #a3aec2; --card: #121933; --line: #24304f; --accent: #5EEAD4; --up: #4ade80; --down: #f87171; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--fg); font-family: 'IBM Plex Sans Arabic', system-ui, sans-serif; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 24px 16px 48px; }
        header { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 18px; }
        h1 { font-size: 22px; margin: 0; }
        .muted { color: var(--muted); font-size: 14px; }
        .pills { display: flex; flex-wrap: wrap; gap: 6px; margin: 8px 0 18px; }
        .pill { display: inline-flex; align-items: center; min-height: 44px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--line); color: var(--fg); text-decoration: none; font-size: 14px; }
        .pill.on { border-color: var(--accent); color: var(--accent); font-weight: 600; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
        .k { font-size: 13px; color: var(--muted); }
        .v { font-size: 22px; font-weight: 700; margin-top: 4px; }
        .d { font-size: 13px; margin-top: 4px; }
        .up { color: var(--up); } .down { color: var(--down); }
        h2 { font-size: 17px; margin: 26px 0 10px; }
        .tbl { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; background: var(--card); border: 1px solid var(--line); border-radius: 14px; }
        th, td { padding: 10px 12px; text-align: start; border-top: 1px solid var(--line); white-space: nowrap; }
        th { color: var(--muted); font-weight: 600; border-top: 0; }
        td.n, th.n { text-align: end; }
        td:first-child { white-space: normal; min-width: 180px; }
        footer { margin-top: 28px; font-size: 13px; color: var(--muted); line-height: 1.8; }
        .lang { display: inline-flex; align-items: center; min-height: 44px; padding: 0 12px; color: var(--accent); text-decoration: none; }
        bdi { unicode-bidi: isolate; }
        .ads { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 200px), 1fr)); gap: 12px; }
        .ad img, .ad .ph { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 10px; display: block; }
        .ad .ph { background: var(--line); display: flex; align-items: center; justify-content: center; text-align: center; padding: 10px; color: var(--muted); font-size: 13px; }
        .ad .name { font-weight: 600; font-size: 14px; margin-top: 8px; overflow-wrap: anywhere; }
        .ad dl { display: grid; grid-template-columns: 1fr auto; gap: 2px 8px; font-size: 13px; margin: 8px 0 0; }
        .ad dt { color: var(--muted); } .ad dd { margin: 0; text-align: end; }
    </style>
