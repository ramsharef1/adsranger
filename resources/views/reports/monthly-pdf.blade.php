@php
    use App\Support\ResultResolver as R;
    $cur = $client->currency;
    $dir = $lang === 'ar' ? 'rtl' : 'ltr';
    $al = $lang === 'ar' ? 'right' : 'left';
    $ar = $lang === 'ar' ? 'left' : 'right';
    $pct = function ($now, $before) {
        if (! $before || $now === null) { return null; }
        return round(($now - $before) / $before * 100);
    };
    // A true minus (U+2212) inside <bdo dir="ltr">: mPDF keeps "−4%" in order in Arabic, and copied text stays "−4%".
    $sign = fn ($d) => \App\Support\Bidi::signedPct($d);
    $num = fn ($v) => number_format((float) $v);
    $typeLabel = R::label($t['main_type'], $lang) ?: __('report.results');
    $budgetPct = ($budget && ! $hideSpend) ? round($t['spend'] / $budget * 100) : null;
@endphp
<html dir="{{ $dir }}" lang="{{ $lang }}">
<head>
<style>
    body { font-family: dejavusans; font-size: 10pt; color: #0f172a; direction: {{ $dir }}; }
    h1 { font-size: 18pt; margin: 0 0 2mm 0; }
    .muted { color: #475569; font-size: 9pt; }
    .band { border-bottom: 2px solid #0F766E; padding-bottom: 3mm; margin-bottom: 6mm; }
    table.kpi { width: 100%; border-collapse: separate; border-spacing: 2mm; margin: 0 -2mm; }
    table.kpi td { border: 1px solid #e2e8f0; padding: 3mm; width: 25%; vertical-align: top; text-align: {{ $al }}; }
    .k { color: #475569; font-size: 8.5pt; }
    .v { font-size: 15pt; font-weight: bold; margin-top: 1mm; }
    .up { color: #15803d; } .down { color: #b91c1c; }
    h2 { font-size: 12pt; margin: 7mm 0 2mm 0; color: #0F766E; }
    table.list { width: 100%; border-collapse: collapse; font-size: 9pt; }
    table.list th { background: #f1f5f9; color: #334155; padding: 2mm; text-align: {{ $al }}; }
    table.list td { border-bottom: 1px solid #e2e8f0; padding: 2mm; text-align: {{ $al }}; }
    table.list .n { text-align: {{ $ar }}; }
    .ltr { direction: ltr; }
    .foot { margin-top: 10mm; font-size: 8pt; color: #64748b; }
</style>
</head>
<body>
<div class="band">
    <h1>{{ $client->name }}</h1>
    <div class="muted">{{ __('report.monthly_heading', ['month' => $monthLabel]) }} · {{ __('report.by', ['agency' => $agency->name]) }}</div>
</div>

@if (($t['spend'] ?? 0) <= 0 && ($t['main_results'] ?? 0) <= 0)
    <p>{{ __('report.no_data') }}</p>
@else
    <table class="kpi"><tr>
        <td>
            <div class="k">{{ $typeLabel }}</div>
            <div class="v">{{ $num($t['main_results']) }}</div>
            @php $d = $pct($t['main_results'], $prev['main_results'] ?? 0); @endphp
            @if ($d !== null)
                <div class="{{ $d >= 0 ? 'up' : 'down' }}"><bdo dir="ltr">{{ $sign($d) }}</bdo> {{ __('report.vs_month', ['month' => $prevLabel]) }}</div>
            @endif
        </td>
        @if (! $hideSpend)
            <td>
                <div class="k">{{ __('report.spend') }}</div>
                <div class="v"><bdo dir="ltr">{{ R::money($t['spend'], $cur) }}</bdo></div>
                @if ($budgetPct !== null)
                    <div class="muted">{{ __('report.of_budget', ['pct' => $budgetPct]) }}</div>
                @endif
            </td>
            <td>
                <div class="k">{{ __('report.cpr') }}</div>
                <div class="v"><bdo dir="ltr">{{ R::money($t['cpr'], $cur) }}</bdo></div>
                @php $d = $pct($t['cpr'], $prev['cpr'] ?? null); @endphp
                @if ($d !== null)
                    <div class="{{ $d <= 0 ? 'up' : 'down' }}"><bdo dir="ltr">{{ $sign($d) }}</bdo> {{ __('report.vs_month', ['month' => $prevLabel]) }}</div>
                @endif
            </td>
        @endif
        <td>
            <div class="k">{{ __('report.clicks') }}</div>
            <div class="v">{{ $num($t['link_clicks'] ?: $t['clicks']) }}</div>
            <div class="muted">{{ __('report.impressions') }}: {{ $num($t['impressions']) }}</div>
        </td>
    </tr></table>

    @if ($campaigns)
        <h2>{{ __('report.campaigns') }}</h2>
        <table class="list">
            <thead><tr>
                <th>{{ __('report.campaign') }}</th>
                <th class="n">{{ __('report.results') }}</th>
                @if (! $hideSpend)
                    <th class="n">{{ __('report.spend') }}</th>
                    <th class="n">{{ __('report.cpr') }}</th>
                @endif
                <th class="n">{{ __('report.clicks') }}</th>
            </tr></thead>
            <tbody>
            @foreach ($campaigns as $c)
                <tr>
                    <td>{{ $c['name'] }}</td>
                    <td class="n">{{ $num($c['main_results']) }} {{ R::label($c['main_type'], $lang) }}</td>
                    @if (! $hideSpend)
                        <td class="n" dir="ltr">{{ R::money($c['spend'], $cur) }}</td>
                        <td class="n" dir="ltr">{{ R::money($c['cpr'], $cur) }}</td>
                    @endif
                    <td class="n">{{ $num($c['link_clicks'] ?: $c['clicks']) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endif

<div class="foot">
    {{ __('report.generated', ['date' => $generated]) }} · {{ __('report.pdf_note') }} · AdsRanger
</div>
</body>
</html>
