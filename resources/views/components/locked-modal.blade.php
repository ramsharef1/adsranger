@props(['feature', 'canUpgrade' => false])
{{-- EX-50 point 5: a locked action inside an open page opens this modal with the LockedState copy. Never a 403, never a silent disabled button. --}}
@php $tier = \App\Support\PlanGate::requiredTier($feature); @endphp
<x-filament::modal id="plan-locked-{{ $feature }}" width="lg">
    <x-locked-state-body :feature="$feature" :tier="$tier" :can-upgrade="$canUpgrade" />
</x-filament::modal>
