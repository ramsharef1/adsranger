@php
    $h = fn (string $s) => \App\Support\Seo\ToolCopy::html($s, $ar);
    $t = fn (string $k, array $r = []) => $h(__('reach_ui.portal.'.$k, $r));
    $money = fn (?float $v) => \App\Support\ResultResolver::money((float) $v, $wallet->currency ?? 'USD');
    $base = url(($ar ? '' : '/en').'/reach/portal');
    $lang = $ar ? 'ar' : 'en';
@endphp
<!doctype html>
<html lang="{{ $lang }}" dir="{{ $ar ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ __('reach_ui.portal.title') }}</title>
@include('reach._style')
</head>
<body>
<main class="wrap">
    <p class="muted"><bdi dir="ltr">AdsRanger Reach</bdi> · <a href="{{ url(($ar ? '/en' : '').'/reach/portal') }}?tab={{ $tab }}" style="color:inherit">{{ $ar ? 'English' : 'العربية' }}</a></p>
    <h1>{{ $t('title') }}</h1>
    <div class="note" role="note"><strong>{{ __('reach_ui.estimate_badge') }}</strong> · {{ __('reach_ui.billing_awaits_launch') }}</div>
    @if (session('reach_status'))<p class="card" role="status">{{ session('reach_status') }}</p>@endif
    @foreach ($errors->all() as $e)<p class="err">{{ $e }}</p>@endforeach

    @if (! $publisher)
        <form class="card" method="post" action="{{ url('/reach/portal/join') }}">
            @csrf <input type="hidden" name="lang" value="{{ $lang }}">
            <h2 style="margin-top:0">{{ $t('join_title') }}</h2>
            <label>{{ $t('name') }}<input name="name" required maxlength="120"></label>
            <label>{{ $t('domain') }}<input name="domain_or_app" required maxlength="190" dir="ltr" placeholder="example.com"></label>
            <label>{{ $t('referral') }}<input name="referral" maxlength="16" dir="ltr" value="{{ $ref }}"></label>
            <button class="btn" type="submit">{{ $t('join') }}</button>
        </form>
    @else
        @if (! $publisher->isVerified())
            <p class="card">{{ __('reach_ui.status.'.$publisher->status) }} · {{ $t('pending_note') }}</p>
        @endif
        <nav class="tabs" aria-label="{{ __('reach_ui.portal.title') }}">
            @foreach (\App\Http\Controllers\ReachPortalController::TABS as $tb)
                <a href="{{ $base }}?tab={{ $tb }}" @if ($tb === $tab) aria-current="page" @endif>{{ $t('tab.'.$tb) }}</a>
            @endforeach
        </nav>

        @if ($tab === 'overview')
            <section class="grid" aria-live="polite">
                <div class="card"><div class="muted">{{ $t('human_today') }}</div><div class="big"><bdi dir="ltr" id="rp-human">{{ $today['human'] }}</bdi></div></div>
                <div class="card"><div class="muted">{{ $t('ai_today') }}</div><div class="big"><bdi dir="ltr" id="rp-ai">{{ $today['ai'] }}</bdi></div></div>
                <div class="card"><div class="muted">{{ $t('week') }}</div><div><bdi dir="ltr" id="rp-week">{{ $week['human'] }} / {{ $week['ai'] }}</bdi></div></div>
                <div class="card"><div class="muted">{{ __('reach_ui.wallet.pending') }} · {{ __('reach_ui.wallet.cleared') }}</div><div><bdi dir="ltr">{{ $money($wallet->pending_balance) }} · {{ $money($wallet->cleared_balance) }}</bdi></div></div>
            </section>
            <div class="card">
                <h2 style="margin-top:0">{{ $t('agents') }}</h2>
                <p class="muted">{{ $t('agents_help') }}</p>
                <ul id="rp-agents">
                    @forelse ($week['agents'] as $agent => $n)
                        <li><bdi dir="ltr">{{ $agent }}</bdi>: <bdi dir="ltr">{{ $n }}</bdi></li>
                    @empty
                        <li class="muted">{{ $t('no_ai') }}</li>
                    @endforelse
                </ul>
            </div>
            <div class="card">
                <strong>{{ $t('referral_badge') }}</strong>
                <p class="muted">{{ $h(trans_choice('reach_ui.portal.referred_count', $referred, ['count' => $referred])) }}</p>
                <pre dir="ltr">{{ url('/reach') }}?ref={{ $publisher->referral_code }}</pre>
            </div>
            <script>
            (function(){
              var u = @json(url(($ar ? '' : '/en').'/reach/portal/stats'));
              function tick(){ fetch(u, {headers:{'Accept':'application/json'}, credentials:'same-origin'}).then(function(r){ return r.ok ? r.json() : null; }).then(function(j){
                if(!j) return; document.getElementById('rp-human').textContent = j.today.human; document.getElementById('rp-ai').textContent = j.today.ai;
                document.getElementById('rp-week').textContent = j.week.human + ' / ' + j.week.ai; }).catch(function(){}); }
              setInterval(tick, 30000);
            })();
            </script>
        @elseif ($tab === 'placements')
            @foreach ($publisher->widgets as $w)
                <div class="card">
                    <strong><bdi dir="ltr">{{ $w->placement }}</bdi></strong> · {{ $t('type.'.$w->type) }}
                    @if ($w->type === 'web_js')
                        <pre dir="ltr">{{ '<div data-reach="'.$w->public_key.'" data-placement="'.$w->placement.'" style="min-height:160px"></div>' }}
{{ '<script async src="'.url('/reach/v1/tag.js').'"></script>' }}</pre>
                        <p class="muted">{{ $t('cls_note') }}</p>
                        <details>
                            <summary style="min-height:44px;display:flex;align-items:center;cursor:pointer">{{ $t('install_kits') }}</summary>
                            <p><a class="btn" href="{{ url('/reach/portal/wordpress-plugin.zip').'?w='.$w->public_key }}" download>{{ $t('wp_plugin') }}</a></p>
                            <p class="muted">{{ $t('wp_plugin_help') }}</p>
                            <p><strong>{{ $t('cf_worker') }}</strong></p>
                            <p class="muted">{{ $t('cf_worker_help') }}</p>
                            <pre dir="ltr" style="max-height:260px;overflow:auto">{{ \App\Support\Reach\PublisherKit::cloudflareWorker($publisher, $w) }}</pre>
                        </details>
                    @else
                        <pre dir="ltr">{{ url('/reach/v1/offerwall').'?w='.$w->public_key }}
{{ url('/reach/v1/offerwall').'?w='.$w->public_key.'&format=html' }}</pre>
                        <p class="muted">{{ $t('incent_note') }}</p>
                    @endif
                </div>
            @endforeach
            <form class="card" method="post" action="{{ url('/reach/portal/widgets') }}">
                @csrf <input type="hidden" name="lang" value="{{ $lang }}">
                <h2 style="margin-top:0">{{ $t('add_widget') }}</h2>
                <label>{{ $t('widget_type') }}<select name="type"><option value="web_js">{{ $t('type.web_js') }}</option><option value="app_sdk">{{ $t('type.app_sdk') }}</option></select></label>
                <label>{{ $t('placement') }}<input name="placement" required maxlength="32" dir="ltr" value="main" pattern="[A-Za-z0-9\-]+"></label>
                <button class="btn" type="submit">{{ $t('add') }}</button>
            </form>
            <form class="card" method="post" action="{{ url('/reach/portal/settings') }}">
                @csrf <input type="hidden" name="lang" value="{{ $lang }}">
                <h2 style="margin-top:0">{{ $t('safety') }}</h2>
                <label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="family_safe" value="1" style="width:auto;min-height:auto" @checked($publisher->family_safe)> {{ $t('family_safe') }}</label>
                <label>{{ $t('blocked_domains', ['max' => config('reach.max_blocked_domains')]) }}<textarea name="blocked_domains" rows="4" dir="ltr">{{ implode("\n", (array) $publisher->blocked_domains) }}</textarea></label>
                <button class="btn" type="submit">{{ $t('save') }}</button>
            </form>
        @elseif ($tab === 'offers')
            <div class="card">
                <h2 style="margin-top:0">{{ $t('your_codes') }}</h2>
                @forelse ($codes as $c)
                    <p>{{ $c->offer?->title }} · <bdi dir="ltr">{{ $c->code }}</bdi> · {{ $h(__('reach_ui.tier.'.$c->tier)) }}</p>
                @empty
                    <p class="muted">{{ $t('no_codes') }}</p>
                @endforelse
            </div>
            @if ($w = $publisher->widgets->firstWhere('type', 'web_js'))
                <div class="card">
                    <h2 style="margin-top:0">{{ $t('ai_feed') }}</h2>
                    <p class="muted">{{ $t('ai_feed_help') }}</p>
                    <pre dir="ltr">{{ url('/reach/v1/ai-feed').'?w='.$w->public_key }}
{{ url('/reach/v1/llms/'.$publisher->ref.'.txt') }}</pre>
                </div>
            @endif
        @else
            <section class="grid">
                @foreach (['pending' => 'pending_balance', 'cleared' => 'cleared_balance', 'reserve' => 'reserve_balance', 'ad_credit' => 'ad_credit_balance'] as $k => $col)
                    <div class="card"><div class="muted">{{ __('reach_ui.wallet.'.$k) }}</div><div class="big"><bdi dir="ltr">{{ $money($wallet->{$col}) }}</bdi></div></div>
                @endforeach
            </section>
            @if ($publisher->isVerified())
                <form class="card" method="post" action="{{ url('/reach/portal/wallet/early-cashout') }}">
                    @csrf <input type="hidden" name="lang" value="{{ $lang }}">
                    <h2 style="margin-top:0">{{ $t('early_cashout') }}</h2>
                    <p class="muted">{{ $t('early_cashout_help') }}</p>
                    <p><bdi dir="ltr">{{ $money($quote['advance']) }} − {{ $money($quote['fee']) }} = {{ $money($quote['net']) }}</bdi> · {{ __('reach_ui.wallet.reserve') }} <bdi dir="ltr">{{ $money($quote['reserve']) }}</bdi></p>
                    <button class="btn" type="submit" @disabled($quote['advance'] <= 0)>{{ $t('request') }}</button>
                </form>
                <div class="grid">
                    <form class="card" method="post" action="{{ url('/reach/portal/wallet/withdraw') }}">
                        @csrf <input type="hidden" name="lang" value="{{ $lang }}">
                        <h2 style="margin-top:0">{{ $t('withdraw') }}</h2>
                        <p class="muted">{{ $t('withdraw_help', ['min' => config('reach.wallet.min_withdrawal')]) }}</p>
                        <label>{{ $t('amount') }}<input name="amount" inputmode="decimal" dir="ltr" required></label>
                        <button class="btn" type="submit">{{ $t('request') }}</button>
                    </form>
                    <form class="card" method="post" action="{{ url('/reach/portal/wallet/ad-credit') }}">
                        @csrf <input type="hidden" name="lang" value="{{ $lang }}">
                        <h2 style="margin-top:0">{{ $t('ad_credit') }}</h2>
                        <p class="muted">{{ $t('ad_credit_help') }}</p>
                        <label>{{ $t('amount') }}<input name="amount" inputmode="decimal" dir="ltr" required></label>
                        <button class="btn ghost" type="submit">{{ $t('request') }}</button>
                    </form>
                </div>
            @endif
            <div class="card" style="overflow-x:auto">
                <h2 style="margin-top:0">{{ $t('ledger') }}</h2>
                <table>
                    <thead><tr><th>{{ $t('date') }}</th><th>{{ $t('entry') }}</th><th>{{ $t('amount') }}</th><th>{{ $t('state') }}</th></tr></thead>
                    <tbody>
                    @forelse ($ledger as $l)
                        <tr>
                            <td><bdi dir="ltr">{{ $l->created_at->format('Y-m-d') }}</bdi></td>
                            <td>{{ $t('kind.'.$l->conversion_type) }}@if ($l->offer) · {{ $l->offer->title }}@endif</td>
                            <td><bdi dir="ltr">{{ $money($l->net_commission) }}</bdi></td>
                            <td>{{ __('reach_ui.estimate_badge') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">{{ $t('no_ledger') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</main>
</body>
</html>
