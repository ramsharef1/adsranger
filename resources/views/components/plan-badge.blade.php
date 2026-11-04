@props(['tier'])
{{-- PlanBadge (EX-64 kit, EX-50): names the tier that unlocks a feature. Neutral style, always with text. --}}
<x-filament::badge color="gray" icon="heroicon-m-lock-closed" {{ $attributes->merge(['style' => 'display:inline-flex']) }}>{{ \App\Support\PlanGate::tierLabel($tier) }}</x-filament::badge>
