@php $muted = 'opacity:.65;font-size:12px'; @endphp
<x-filament-panels::page>
    <x-filament::section>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead><tr style="{{ $muted }}"><th style="padding:6px;text-align:start">{{ __('Network') }}</th><th style="padding:6px;text-align:start">{{ __('Type') }}</th>
                    <th style="padding:6px;text-align:end">{{ __('Workspaces') }}</th><th style="padding:6px;text-align:end">{{ __('Members') }}</th><th style="padding:6px;text-align:start">{{ __('Last request') }}</th></tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr style="border-top:1px solid rgba(148,163,184,.2)">
                        <td style="padding:6px" dir="auto">{{ $r->network_name }}</td>
                        <td style="padding:6px">{{ str_starts_with($r->network_key, 'other:') ? __('Suggestion') : __('Catalog') }}</td>
                        <td style="padding:6px;text-align:end;font-variant-numeric:tabular-nums;font-weight:600">{{ $r->workspaces }}</td>
                        <td style="padding:6px;text-align:end;font-variant-numeric:tabular-nums">{{ $r->members }}</td>
                        <td style="padding:6px">{{ \Illuminate\Support\Carbon::parse($r->last_at)->format('Y-m-d') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding:10px;{{ $muted }}">{{ __('No requests yet') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
    @if ($notes->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">{{ __('Notes from workspaces') }}</x-slot>
            <ul style="margin:0;padding-inline-start:18px;display:grid;gap:6px;font-size:13px">
                @foreach ($notes as $n)<li><strong dir="auto">{{ $n->network_name }}</strong> · {{ $n->agency?->name }}: <span dir="auto">{{ $n->note }}</span></li>@endforeach
            </ul>
        </x-filament::section>
    @endif
</x-filament-panels::page>
