@php
    use App\Support\ResultResolver as R;
    $muted = 'opacity:.65;font-size:12px';
    $sel = 'padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.35);background:transparent;font-size:14px';
    $lines = [
        [__('Spend'), fn ($s) => R::money($s['t']['spend'], $s['client']->currency)],
        [__('Main result'), fn ($s) => number_format($s['t']['main_results']).' '.R::label($s['t']['main_type'], app()->getLocale())],
        [__('Cost per result'), fn ($s) => R::money($s['t']['cpr'], $s['client']->currency)],
        [__('Leads'), fn ($s) => number_format($s['t']['leads'])], [__('Conversations'), fn ($s) => number_format($s['t']['messages'])],
        [__('Calls placed'), fn ($s) => number_format($s['t']['calls'])], [__('Purchases'), fn ($s) => number_format($s['t']['purchases'])],
        [__('ROAS'), fn ($s) => $s['t']['roas'] ? number_format($s['t']['roas'], 2).'×' : '–'],
        [__('Impressions'), fn ($s) => number_format($s['t']['impressions'])], [__('Link CTR'), fn ($s) => $s['t']['ctr'] ? number_format($s['t']['ctr'], 2).'%' : '–'],
        [__('Avg. spend / day'), fn ($s) => R::money($s['t']['spend'] / max(1, count($s['days'])), $s['client']->currency)],
    ];
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <select wire:model.live="a" style="{{ $sel }}">@foreach ($list as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        <span style="{{ $muted }}">{{ __('vs') }}</span>
        <select wire:model.live="b" style="{{ $sel }}">@foreach ($list as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
        <select wire:model.live="period" style="{{ $sel }}">
            @foreach (['7d' => 'Last 7 days', '14d' => 'Last 14 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'] as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach
        </select>
        <span style="{{ $muted }}">{{ $from }} → {{ $to }} · {{ __('each in its own currency') }}</span>
    </div>
    @if ($A && $B)
        <x-filament::section>
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start"></th>
                    <th style="padding:6px;text-align:end">{{ $A['client']->name }}</th><th style="padding:6px;text-align:end">{{ $B['client']->name }}</th></tr></thead>
                <tbody>
                @foreach ($lines as [$label, $fn])
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px;{{ $muted }}">{{ $label }}</td>
                        <td style="padding:6px;text-align:end;font-weight:600">{{ $fn($A) }}</td>
                        <td style="padding:6px;text-align:end;font-weight:600">{{ $fn($B) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @else
        <x-filament::section><div style="{{ $muted }}">{{ __('Add at least two active clients to compare them.') }}</div></x-filament::section>
    @endif
</x-filament-panels::page>
