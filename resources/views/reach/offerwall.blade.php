<!doctype html>
<html lang="{{ $ar ? 'ar' : 'en' }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>{{ __('reach_ui.offerwall.title') }}</title>
<style>
body{margin:0;font:15px/1.5 system-ui,sans-serif;background:#fff;color:#0f172a}
@media (prefers-color-scheme:dark){body{background:#0f172a;color:#e2e8f0}}
main{padding:12px 16px}.o{display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;padding:10px 0;border-top:1px solid rgba(127,127,127,.25)}
a.b{padding:10px 14px;min-height:44px;box-sizing:border-box;border-radius:8px;background:#0f766e;color:#fff;text-decoration:none}
.n{font-size:12px;opacity:.7}
</style>
</head>
<body>
<main>
    <p class="n">{{ __('reach_ui.offerwall.note') }}</p>
    @forelse ($payload['offers'] as $o)
        <div class="o"><span>{{ $o['title'] }}</span><a class="b" href="{{ $o['click'] }}" rel="sponsored noopener">{{ __('reach_ui.offerwall.go') }}</a></div>
    @empty
        <p>{{ __('reach_ui.offerwall.empty') }}</p>
    @endforelse
</main>
<script>if (window.innerWidth < 2 || window.innerHeight < 2) { document.body.textContent = ''; }</script>
</body>
</html>
