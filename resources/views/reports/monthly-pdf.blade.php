@php
    use App\Support\ResultResolver as R;
    $cur = $client->currency;
    // XS-22 (R5): 'both' = Arabic first with the English line under each label, in one PDF for mixed teams.
    $both = $lang === 'both';
    $rtl = $lang !== 'en';
    $dir = $rtl ? 'rtl' : 'ltr';
    $al = $rtl ? 'right' : 'left';
    $ar = $rtl ? 'left' : 'right';
    $L = function (string $key, array $r = []) use ($both, $lang) {
        $pick = fn ($loc) => array_map(fn ($v) => is_array($v) ? ($v[$loc] ?? reset($v)) : $v, $r);
        if (! $both) {
            return new \Illuminate\Support\HtmlString(e(__($key, $pick($lang), $lang)));
        }
        return new \Illuminate\Support\HtmlString(e(__($key, $pick('ar'), 'ar')).'<br><span class="en">'.e(__($key, $pick('en'), 'en')).'</span>');
    };
    $rl = fn ($type) => $both ? trim((\App\Support\SectorPacks::resultLabel($client, $type, 'ar') ?: __('report.results', [], 'ar')).' / '.(\App\Support\SectorPacks::resultLabel($client, $type, 'en') ?: __('report.results', [], 'en'))) : \App\Support\SectorPacks::resultLabel($client, $type, $lang);
    $pct = function ($now, $before) {
        if (! $before || $now === null) { return null; }
        return round(($now - $before) / $before * 100);
    };
    // A true minus (U+2212) inside <bdo dir="ltr">: mPDF keeps "−4%" in order in Arabic, and copied text stays "−4%".
    $sign = fn ($d) => \App\Support\Bidi::signedPct($d);
    $num = fn ($v) => number_format((float) $v);
    $typeLabel = $rl($t['main_type']) ?: __('report.results');
    $budgetPct = ($budget && ! $hideSpend) ? round($t['spend'] / $budget * 100) : null;
@endphp
<html dir="{{ $dir }}" lang="{{ $both ? 'ar' : $lang }}">
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
    .en { font-size: 85%; color: #475569; direction: ltr; }
    .foot { margin-top: 10mm; font-size: 8pt; color: #64748b; }
</style>
</head>
<body>
@php $logoPath = \App\Support\AgencyLogo::absolutePath($agency); @endphp
@if ($logoPath)
    {{-- #176 spec B: 48 px on the cover, 24 px in the header of the following pages; never recoloured or cropped. --}}
    <htmlpageheader name="agencyHeader"><div style="text-align: {{ $al }};"><img src="{{ $logoPath }}" style="height: 24px;"></div></htmlpageheader>
    <sethtmlpageheader name="agencyHeader" value="on" show-this-page="0" />
@endif
<div class="band">
    @if ($logoPath)
        <div style="margin-bottom: 3mm;"><img src="{{ $logoPath }}" style="height: 48px;"></div>
    @endif
    <h1>{{ $client->name }}</h1>
    <div class="muted">{{ $L('report.monthly_heading', ['month' => $monthLabel]) }} · {{ $L('report.by', ['agency' => $agency->name]) }}</div>
</div>

@if (($t['spend'] ?? 0) <= 0 && ($t['main_results'] ?? 0) <= 0)
    <p>{{ $L('report.no_data') }}</p>
@else
    <table class="kpi"><tr>
        <td>
            <div class="k">{{ $typeLabel }}</div>
            <div class="v">{{ $num($t['main_results']) }}</div>
            @php $d = $pct($t['main_results'], $prev['main_results'] ?? 0); @endphp
            @if ($d !== null)
                <div class="{{ $d >= 0 ? 'up' : 'down' }}"><bdo dir="ltr">{{ $sign($d) }}</bdo> {{ $L('report.vs_month', ['month' => $prevLabel]) }}</div>
            @endif
        </td>
        @if (! $hideSpend)
            <td>
                <div class="k">{{ $L('report.spend') }}</div>
                <div class="v"><bdo dir="ltr">{{ R::money($t['spend'], $cur) }}</bdo></div>
                @if ($budgetPct !== null)
                    <div class="muted">{{ $L('report.of_budget', ['pct' => $budgetPct]) }}</div>
                @endif
            </td>
            <td>
                <div class="k">{{ $L('report.cpr') }}</div>
                <div class="v"><bdo dir="ltr">{{ R::money($t['cpr'], $cur) }}</bdo></div>
                @php $d = $pct($t['cpr'], $prev['cpr'] ?? null); @endphp
                @if ($d !== null)
                    <div class="{{ $d <= 0 ? 'up' : 'down' }}"><bdo dir="ltr">{{ $sign($d) }}</bdo> {{ $L('report.vs_month', ['month' => $prevLabel]) }}</div>
                @endif
            </td>
        @endif
        <td>
            <div class="k">{{ $L('report.clicks') }}</div>
            <div class="v">{{ $num($t['link_clicks'] ?: $t['clicks']) }}</div>
            <div class="muted">{{ $L('report.impressions') }}: {{ $num($t['impressions']) }}</div>
        </td>
    </tr></table>

    @if ($campaigns)
        <h2>{{ $L('report.campaigns') }}</h2>
        <table class="list">
            <thead><tr>
                <th>{{ $L('report.campaign') }}</th>
                <th class="n">{{ $L('report.results') }}</th>
                @if (! $hideSpend)
                    <th class="n">{{ $L('report.spend') }}</th>
                    <th class="n">{{ $L('report.cpr') }}</th>
                @endif
                <th class="n">{{ $L('report.clicks') }}</th>
            </tr></thead>
            <tbody>
            @foreach ($campaigns as $c)
                <tr>
                    <td>{{ $c['name'] }}</td>
                    <td class="n">{{ $num($c['main_results']) }} {{ $rl($c['main_type']) }}</td>
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

@if (! empty($gaps))
    <div class="muted" data-report-gaps style="margin-top:10px;line-height:1.8">{{ $L('report.gaps_intro') }}
        @foreach ($gaps as $g)<bdi dir="ltr">{{ $g['from'] === $g['to'] ? $g['from'] : $g['from'].' → '.$g['to'] }}</bdi> ({{ $L('report.gap_'.$g['reason']) }})@if (! $loop->last){{ $rtl ? '،' : ',' }} @endif @endforeach
    </div>
@endif

{{-- XS-03 / FS-20 (R4): the agency's own lines, edited before sending. --}}
@if (! empty($notes?->done))
    <h2>{{ $L('report.what_we_did') }}</h2>
    <div style="white-space:pre-line">{{ $notes->done }}</div>
@endif
@if (! empty($notes?->next))
    <h2>{{ $L('report.next_month') }}</h2>
    <div style="white-space:pre-line">{{ $notes->next }}</div>
@endif

<div class="foot">
    {{ $L('report.generated', ['date' => $generated]) }} · {{ $L('report.pdf_note') }}@if (\App\Support\PoweredBy::on($agency ?? null)) · {!! \App\Support\PoweredBy::html($agency ?? null) !!}@endif
</div>
</body>
</html>
