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
    <title>{{ \App\Support\DemoData::isSample($client) ? \App\Support\DemoData::LABEL.' · ' : '' }}{{ __('report.title', ['client' => $client->name]) }}</title>
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;600;700&display=swap" rel="stylesheet">
    @include('client-report._style')
</head>
<body>
<div class="wrap">
    <header>
        <div>
            @if ($logoUrl = \App\Support\AgencyLogo::url($agency))
                <img class="agency-logo" src="{{ $logoUrl }}" alt="{{ $agency->name }}" height="32" style="height:32px;width:auto;max-width:240px;display:block;margin-bottom:8px">
            @endif
            @if (\App\Support\DemoData::isSample($client))
                <div data-sample-banner style="display:inline-block;margin-bottom:6px;padding:2px 10px;border-radius:999px;background:#fef3c7;color:#92400e;font-size:13px;font-weight:600">{{ $lang === 'ar' ? 'بيانات تجريبية: الأرقام هنا ليست حقيقية' : 'Sample data: these numbers are not real' }}</div>
            @endif
            <h1><bdi>{{ $client->name }}</bdi></h1>
            <div class="muted">{{ __('report.by', ['agency' => $agency->name]) }} · {{ __('report.range', ['from' => \Carbon\Carbon::parse($from)->locale($lang)->translatedFormat('j F Y'), 'to' => \Carbon\Carbon::parse($to)->locale($lang)->translatedFormat('j F Y')]) }}</div>
        </div>
        <a class="lang" href="{{ $q(['lang' => __('report.switch_lang')]) }}" lang="{{ __('report.switch_lang') }}">{{ __('report.switch') }}</a>
    </header>

    {{-- XS-06 (R4): approvals the agency asked for; agency branding only. --}}
    @if ($approvals->isNotEmpty())
        <section id="approvals" class="card" style="display:grid;gap:12px;margin-bottom:14px">
            <strong>{{ __('report.approvals') }}</strong>
            @if (session('approval_saved'))<div class="muted" role="status">{{ __('report.approval_saved') }}</div>@endif
            @foreach ($approvals as $ap)
                <div style="border-top:1px solid rgba(148,163,184,.25);padding-top:10px;display:grid;gap:6px">
                    <div><strong>{{ $ap->title }}</strong> <span class="muted">· {{ __('report.approval_kinds.'.$ap->kind) }}</span></div>
                    @if ($ap->details)<div style="white-space:pre-line" dir="auto">{{ $ap->details }}</div>@endif
                    @if ($ap->status === 'pending')
                        <form method="POST" action="/r/{{ $token }}/approvals/{{ $ap->id }}" style="display:grid;gap:8px">
                            @csrf
                            <input name="name" required maxlength="80" placeholder="{{ __('report.your_name') }}" style="min-height:44px;padding:0 10px;border-radius:10px;border:1px solid rgba(148,163,184,.4);font:inherit" dir="auto">
                            <textarea name="note" maxlength="1000" rows="2" placeholder="{{ __('report.note_optional') }}" style="padding:8px 10px;border-radius:10px;border:1px solid rgba(148,163,184,.4);font:inherit" dir="auto"></textarea>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <button name="decision" value="approved" type="submit" style="min-height:44px;padding:0 16px;border-radius:10px;border:0;background:#0F766E;color:#fff;font:inherit;font-weight:600">{{ __('report.approve') }}</button>
                                <button name="decision" value="changes" type="submit" style="min-height:44px;padding:0 16px;border-radius:10px;border:1px solid rgba(148,163,184,.5);background:transparent;font:inherit">{{ __('report.ask_changes') }}</button>
                            </div>
                        </form>
                    @else
                        <div class="muted">{{ $ap->status === 'approved' ? __('report.approved_by', ['name' => $ap->answered_by]) : __('report.changes_by', ['name' => $ap->answered_by]) }} · <bdi dir="ltr">{{ $ap->answered_at?->format('Y-m-d') }}</bdi></div>
                    @endif
                </div>
            @endforeach
        </section>
    @endif

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

    @if (! empty($gaps))
        <div class="muted" data-report-gaps style="margin-top:10px;line-height:1.8">{{ __('report.gaps_intro') }}
            @foreach ($gaps as $g)<bdi dir="ltr">{{ $g['from'] === $g['to'] ? $g['from'] : $g['from'].' → '.$g['to'] }}</bdi> ({{ __('report.gap_'.$g['reason']) }})@if (! $loop->last){{ $lang === 'ar' ? '،' : ',' }} @endif @endforeach
        </div>
    @endif

    <p><a class="pill" href="{{ url('/r/'.$link->token.'/ads').'?'.http_build_query(['p' => $period, 'lang' => $lang]) }}">{{ __('report.see_ads') }}</a></p>

    @if ($pdfMonths)
    <h2>{{ __('report.download_pdf') }}</h2>
    <nav class="pills">
        @foreach ($pdfMonths as $m => $label)
            <a class="pill" href="{{ url('/r/'.$link->token.'/pdf').'?'.http_build_query(['m' => $m, 'lang' => $lang]) }}">{{ $label }}</a>
        @endforeach
    </nav>
    @endif
    <p><a class="pill" href="{{ url('/r/'.$link->token.'/csv').'?'.http_build_query(['p' => $period, 'lang' => $lang]) }}">{!! __('report.download_csv') !!}</a></p>

    <footer>
        {{ $synced ? __('report.synced', ['ago' => $synced->locale($lang)->diffForHumans()]) : __('report.not_synced') }}<br>
        {{ __('report.footer') }}@if (\App\Support\PoweredBy::on($agency))<br>
        {!! \App\Support\PoweredBy::html($agency) !!}@endif
    </footer>
</div>
</body>
</html>
