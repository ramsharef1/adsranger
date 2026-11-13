@php
    $catLabels = ['server' => __('Server'), 'ai' => __('AI'), 'storage' => __('Storage (R2)'), 'data_vendor' => __('Data vendor'), 'email' => __('Email'), 'other' => __('Other')];
    $statusLabels = ['open' => __('Open'), 'done' => __('Done'), 'blocked' => __('Blocked')];
    $usd = fn ($v) => number_format((float) $v, 2).' USD';
    $field = 'min-height:44px;padding:6px 10px;border-radius:8px;border:1px solid rgba(148,163,184,.4);background:transparent;color:inherit';
@endphp
<x-filament-panels::page>
    {{-- A-4 --}}
    <x-filament::section>
        <x-slot name="heading">{{ __('Platform costs this month') }}</x-slot>
        <x-slot name="description">{{ __('Typed by hand from each bill until the providers\' own feeds are connected. The cap is :cap a month.', ['cap' => $usd($cap)]) }}</x-slot>
        <p style="font-size:22px;font-weight:700;margin:0" dir="ltr">{{ $usd($total) }} <span style="font-size:14px;font-weight:400;opacity:.75">/ {{ $usd($cap) }} ({{ $share }}%)</span></p>
        <div role="progressbar" aria-valuenow="{{ $share }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Share of the monthly cap') }}" style="height:10px;border-radius:99px;background:rgba(148,163,184,.25);margin:8px 0 12px;overflow:hidden">
            <div style="height:100%;width:{{ $share }}%;background:#0f766e"></div>
        </div>
        @if ($share >= 80)
            <p role="alert" style="margin:0 0 10px;font-size:14px">{{ __('Costs this month have reached :share% of the cap.', ['share' => $share]) }}</p>
        @endif
        @if ($byCategory !== [])
            <p style="font-size:14px;margin:0 0 12px">
                @foreach ($byCategory as $cat => $sum){{ $catLabels[$cat] ?? $cat }}: <bdi dir="ltr">{{ $usd($sum) }}</bdi>@if (! $loop->last) · @endif @endforeach
            </p>
        @endif

        <form wire:submit="addCost" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end;font-size:14px">
            <label>{{ __('Month') }}<br><input type="month" wire:model="costMonth" style="{{ $field }}"></label>
            <label>{{ __('Category') }}<br><select wire:model="costCategory" style="{{ $field }}">@foreach ($categories as $c)<option value="{{ $c }}">{{ $catLabels[$c] ?? $c }}</option>@endforeach</select></label>
            <label>{{ __('Amount (USD)') }}<br><input type="number" min="0" step="0.01" wire:model="costAmount" dir="ltr" style="{{ $field }};width:130px"></label>
            <label style="flex:1;min-width:180px">{{ __('Note (optional)') }}<br><input type="text" wire:model="costNote" maxlength="200" style="{{ $field }};width:100%"></label>
            <x-filament::button type="submit">{{ __('Add cost') }}</x-filament::button>
        </form>

        @if ($lines->isNotEmpty())
            <div style="overflow-x:auto;margin-top:14px">
                <table style="width:100%;border-collapse:collapse;font-size:14px">
                    <caption class="sr-only">{{ __('Costs, last three months') }}</caption>
                    <thead><tr><th scope="col" style="padding:6px;text-align:start">{{ __('Month') }}</th><th scope="col" style="padding:6px;text-align:start">{{ __('Category') }}</th><th scope="col" style="padding:6px;text-align:start">{{ __('Amount (USD)') }}</th><th scope="col" style="padding:6px;text-align:start">{{ __('Note (optional)') }}</th></tr></thead>
                    <tbody>
                        @foreach ($lines as $l)
                            <tr style="border-top:1px solid rgba(148,163,184,.18)"><td style="padding:6px" dir="ltr">{{ $l->month->format('Y-m') }}</td><td style="padding:6px">{{ $catLabels[$l->category] ?? $l->category }}</td><td style="padding:6px" dir="ltr">{{ $usd($l->amount_usd) }}</td><td style="padding:6px">{{ $l->note }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- A-8 --}}
    <x-filament::section style="margin-top:16px">
        <x-slot name="heading">{{ __('Launch go / no-go list') }}</x-slot>
        <x-slot name="description">{{ __(':done of :total lines done. Launch is a go only when every line is done.', ['done' => $checksDone, 'total' => $checks->count()]) }}</x-slot>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <caption class="sr-only">{{ __('Launch go / no-go list') }}</caption>
                <thead><tr><th scope="col" style="padding:6px;text-align:start">{{ __('Line') }}</th><th scope="col" style="padding:6px;text-align:start">{{ __('Owner') }}</th><th scope="col" style="padding:6px;text-align:start">{{ __('Due') }}</th><th scope="col" style="padding:6px;text-align:start">{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @foreach ($checks as $c)
                        <tr style="border-top:1px solid rgba(148,163,184,.18)">
                            <td style="padding:6px">{{ $c->title }}</td>
                            <td style="padding:6px">{{ $c->owner }}</td>
                            <td style="padding:6px"><input type="date" value="{{ $c->due_on?->format('Y-m-d') }}" wire:change="setCheckDue({{ $c->id }}, $event.target.value)" aria-label="{{ __('Due') }}: {{ $c->title }}" style="{{ $field }}"></td>
                            <td style="padding:6px">
                                <select wire:change="setCheckStatus({{ $c->id }}, $event.target.value)" aria-label="{{ __('Status') }}: {{ $c->title }}" style="{{ $field }}">
                                    @foreach ($statusLabels as $k => $label)<option value="{{ $k }}" @selected($c->status === $k)>{{ $label }}</option>@endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <form wire:submit="addCheck" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end;font-size:14px;margin-top:12px">
            <label style="flex:1;min-width:220px">{{ __('New line') }}<br><input type="text" wire:model="checkTitle" maxlength="200" style="{{ $field }};width:100%"></label>
            <label>{{ __('Owner') }}<br><input type="text" wire:model="checkOwner" maxlength="40" style="{{ $field }};width:120px"></label>
            <label>{{ __('Due') }}<br><input type="date" wire:model="checkDue" style="{{ $field }}"></label>
            <x-filament::button type="submit">{{ __('Add line') }}</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
