@php $cell = 'padding:4px 6px;text-align:start'; @endphp
<div data-import-preview style="font-size:13px;display:grid;gap:6px">
    @if ($p['header'] === null)
        <div style="color:#dc2626">{{ __('No date and spend columns were found in the first rows.') }} @if ($p['missing']) ({{ implode(', ', $p['missing']) }}) @endif</div>
    @else
        <div>{{ __('Looks like') }}: <strong>{{ $p['network'] ? ($networks[$p['network']] ?? $p['network']) : __('an unknown network (choose one above)') }}</strong> · {{ __(':n rows with a date', ['n' => $p['rows']]) }}</div>
        <div style="opacity:.8">{{ __('Matched columns') }}: <bdi dir="ltr">{{ collect($p['mapping'])->map(fn ($h, $f) => "$f ← $h")->implode(' · ') }}</bdi></div>
        <table style="border-collapse:collapse">
            <thead><tr><th style="{{ $cell }}">{{ __('Date') }}</th><th style="{{ $cell }}">{{ __('Campaign') }}</th><th style="{{ $cell }}">{{ __('Spend') }}</th><th style="{{ $cell }}">{{ __('Results') }}</th></tr></thead>
            <tbody>
                @foreach ($p['sample'] as $r)
                    <tr style="border-top:1px solid rgba(127,127,127,.2)"><td style="{{ $cell }}"><bdi dir="ltr">{{ $r['date'] }}</bdi></td><td style="{{ $cell }}"><bdi>{{ $r['campaign'] ?? '—' }}</bdi></td><td style="{{ $cell }}">{{ number_format($r['spend'], 2) }}</td><td style="{{ $cell }}">{{ $r['results'] ?? '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
