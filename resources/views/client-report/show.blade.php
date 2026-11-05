@php
    use App\Support\ResultResolver as R;
    $dir = $lang === 'ar' ? 'rtl' : 'ltr';
    $cur = $client->currency;
    $delta = function ($now, $before) {
        if (! $before || $now === null) { return null; }
        return round(($now - $before) / $before * 100);
    };
    $typeLabel = R::label($t['main_type'], $lang);
    $q = fn (array $over) => url()->current().'?'.http_build_query(array_merge(['p' => $period, 'lang' => $lang], $over));
    $num = fn ($v) => number_format((float) $v);
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('report.title', ['client' => $client->name]) }}</title>
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
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
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div>
            <h1><bdi>{{ $client->name }}</bdi></h1>
            <div class="muted">{{ __('report.by', ['agency' => $agency->name]) }} · {{ __('report.range', ['from' => \Carbon\Carbon::parse($from)->locale($lang)->translatedFormat('j F Y'), 'to' => \Carbon\Carbon::parse($to)->locale($lang)->translatedFormat('j F Y')]) }}</div>
        </div>
        <a class="lang" href="{{ $q(['lang' => __('report.switch_lang')]) }}" lang="{{ __('report.switch_lang') }}">{{ __('report.switch') }}</a>
    </header>

    <nav class="pills" aria-label="{{ __('report.month') }}">
        @foreach ($periods as $p)
            <a class="pill {{ $p === $period ? 'on' : '' }}" href="{{ $q(['p' => $p]) }}" @if ($p === $period) aria-current="page" @endif>{{ __("report.periods.$p") }}</a>
        @endforeach
    </nav>

    @if (($t['spend'] ?? 0) <= 0 && ($t['main_results'] ?? 0) <= 0)
        <div class="card">{{ __('report.no_data') }}</div>
    @else
        <div class="grid">
            <div class="card">
                <div class="k">{{ $typeLabel ?: __('report.results') }}</div>
                <div class="v">{{ $num($t['main_results']) }}</div>
                @php $d = $delta($t['main_results'], $prev['main_results'] ?? 0); @endphp
                @if ($d !== null)<div class="d {{ $d >= 0 ? 'up' : 'down' }}"><bdi dir="ltr">{{ $d >= 0 ? '+' : '' }}{{ $d }}%</bdi> {{ __('report.vs_prev') }}</div>@endif
            </div>
            @if (! $hideSpend)
                <div class="card">
                    <div class="k">{{ __('report.spend') }}</div>
                    <div class="v"><bdi dir="ltr">{{ R::money($t['spend'], $cur) }}</bdi></div>
                </div>
                <div class="card">
                    <div class="k">{{ __('report.cpr') }}</div>
                    <div class="v"><bdi dir="ltr">{{ R::money($t['cpr'], $cur) }}</bdi></div>
                    @php $d = $delta($t['cpr'], $prev['cpr'] ?? null); @endphp
                    @if ($d !== null)<div class="d {{ $d <= 0 ? 'up' : 'down' }}"><bdi dir="ltr">{{ $d >= 0 ? '+' : '' }}{{ $d }}%</bdi> {{ __('report.vs_prev') }}</div>@endif
                </div>
                @if ($t['roas'])
                    <div class="card"><div class="k">{{ __('report.roas') }}</div><div class="v"><bdi dir="ltr">{{ number_format($t['roas'], 2) }}×</bdi></div></div>
                @endif
            @endif
            <div class="card"><div class="k">{{ __('report.impressions') }}</div><div class="v">{{ $num($t['impressions']) }}</div></div>
            <div class="card"><div class="k">{{ __('report.clicks') }}</div><div class="v">{{ $num($t['link_clicks'] ?: $t['clicks']) }}</div></div>
        </div>
    @endif

    @if ($pace && ! $hideSpend)
        <h2>{{ __('report.month') }} <span class="muted">· <bdi>{{ $pace['month_label'] }}</bdi></span></h2>
        <div class="grid">
            <div class="card"><div class="k">{{ __('report.budget') }}</div><div class="v"><bdi dir="ltr">{{ R::money($pace['budget'], $cur) }}</bdi></div></div>
            <div class="card"><div class="k">{{ __('report.spent') }}</div><div class="v"><bdi dir="ltr">{{ R::money($pace['spent'], $cur) }}</bdi></div></div>
            <div class="card"><div class="k">{{ __('report.pace') }}</div><div class="v"><bdi dir="ltr">{{ $pace['pace'] !== null ? round($pace['pace']).'%' : '–' }}</bdi></div><div class="d muted">{{ __('report.pace_note') }}</div></div>
        </div>
    @endif

    @if ($campaigns)
        <h2>{{ __('report.campaigns') }}</h2>
        <div class="tbl">
            <table>
                <thead><tr>
                    <th>{{ __('report.campaign') }}</th>
                    <th class="n">{{ __('report.results') }}</th>
                    @if (! $hideSpend)
                        <th class="n">{{ __('report.spend') }}</th><th class="n">{{ __('report.cpr') }}</th>
                    @endif
                    <th class="n">{{ __('report.clicks') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($campaigns as $c)
                    <tr>
                        <td><bdi>{{ $c['name'] }}</bdi></td>
                        <td class="n">{{ $num($c['main_results']) }} <span class="muted">{{ R::label($c['main_type'], $lang) }}</span></td>
                        @if (! $hideSpend)
                            <td class="n"><bdi dir="ltr">{{ R::money($c['spend'], $cur) }}</bdi></td><td class="n"><bdi dir="ltr">{{ R::money($c['cpr'], $cur) }}</bdi></td>
                        @endif
                        <td class="n">{{ $num($c['link_clicks'] ?: $c['clicks']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2>{{ __('report.download_pdf') }}</h2>
    <nav class="pills">
        @foreach ($pdfMonths as $m => $label)
            <a class="pill" href="{{ url('/r/'.$link->token.'/pdf').'?'.http_build_query(['m' => $m, 'lang' => $lang]) }}">{{ $label }}</a>
        @endforeach
    </nav>
    <p><a class="pill" href="{{ url('/r/'.$link->token.'/csv').'?'.http_build_query(['p' => $period, 'lang' => $lang]) }}">{!! __('report.download_csv') !!}</a></p>

    <footer>
        {{ $synced ? __('report.synced', ['ago' => $synced->locale($lang)->diffForHumans()]) : __('report.not_synced') }}<br>
        {{ __('report.footer') }}<br>
        <span dir="ltr">AdsRanger</span>
    </footer>
</div>
</body>
</html>
