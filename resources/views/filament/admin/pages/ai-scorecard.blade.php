@php $cell = 'padding:6px;text-align:start'; $ok = $offline ? collect($offline)->where('ok', true)->count() : 0; @endphp
<x-filament-panels::page>
    <x-filament::section :heading="__('Golden set (saved answers)')">
        <x-slot name="description"><bdi dir="ltr">{{ $version }}</bdi> · {{ __('Checked on every build; a changed result fails the tests.') }}</x-slot>
        <p style="font-weight:600;{{ $ok === count($offline) ? 'color:#0d9488' : 'color:#dc2626' }}">{{ __(':ok of :n answers keep their expected result', ['ok' => $ok, 'n' => count($offline)]) }}</p>
        @foreach ($offline as $o)
            @unless ($o['ok'])<div style="color:#dc2626;font-size:13px"><bdi dir="ltr">{{ $o['id'] }}</bdi>: {{ $o['band'] }} ≠ {{ $o['expect'] }}</div>@endunless
        @endforeach
    </x-filament::section>

    <x-filament::section :heading="__('Run on a provider key')">
        <x-slot name="description">{{ __('Sends the tasks to the key you pick and gates each answer. It costs money on that key, so the estimate comes first. Never runs on its own.') }}</x-slot>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <select wire:model.live="keyId" wire:change="$set('estimate', null)" class="fi-input" style="min-width:260px">
                <option value="">{{ __('Choose a key') }}</option>
                @foreach ($keys as $k)<option value="{{ $k->id }}">{{ $k->agency?->name }} · {{ $k->provider }} · {{ $k->model }} · •••• {{ $k->last4 }}</option>@endforeach
            </select>
            <x-filament::button size="sm" color="gray" wire:click="estimateRun" :disabled="! $keyId">{{ __('Estimate cost') }}</x-filament::button>
            @if ($estimate !== null)
                <span><bdi dir="ltr">≈ {{ number_format($estimate, 4) }} USD</bdi></span>
                <x-filament::button size="sm" wire:click="run">{{ __('Run now') }}</x-filament::button>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('Provider scorecard')">
        @if ($scorecard === [])
            <p style="opacity:.75">{{ __('No provider run yet.') }}</p>
        @else
            <table style="width:100%;font-size:13px;border-collapse:collapse">
                <thead><tr>@foreach ([__('Provider'), __('Model'), __('Passed'), __('Average score'), __('Cost'), __('Time'), __('When')] as $h)<th style="{{ $cell }}">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($scorecard as $s)
                        <tr style="border-top:1px solid rgba(127,127,127,.2)">
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ $s['provider'] }}</bdi></td><td style="{{ $cell }}"><bdi dir="ltr">{{ $s['model'] }}</bdi></td>
                            <td style="{{ $cell }}">{{ $s['passed'] }} / {{ $s['total'] }}</td><td style="{{ $cell }}">{{ $s['avg_score'] }}</td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ number_format($s['cost'], 4) }} USD</bdi></td><td style="{{ $cell }}"><bdi dir="ltr">{{ $s['seconds'] }} s</bdi></td>
                            <td style="{{ $cell }}"><bdi dir="ltr">{{ \Illuminate\Support\Carbon::parse($s['at'])->timezone('Asia/Amman')->format('Y-m-d H:i') }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
