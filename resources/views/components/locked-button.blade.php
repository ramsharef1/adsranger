@props(['feature', 'label' => null])
{{-- Lock button for a gated action or value; opens <x-locked-modal> for the same feature. --}}
<button type="button" x-data x-on:click="$dispatch('open-modal', { id: 'plan-locked-{{ $feature }}' })"
    title="{{ __('plan.available_on', ['tier' => \App\Support\PlanGate::tierLabel(\App\Support\PlanGate::requiredTier($feature))]) }}"
    style="display:inline-flex;gap:4px;align-items:center;cursor:pointer;background:transparent;border:0;padding:2px;font-size:12px;opacity:.85">
    <x-filament::icon icon="heroicon-m-lock-closed" style="width:14px;height:14px" />
    <span>{{ $label ?? \App\Support\PlanGate::tierShort(\App\Support\PlanGate::requiredTier($feature)) }}</span>
</button>
