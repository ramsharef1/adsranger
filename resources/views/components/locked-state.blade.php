@props(['feature', 'tier', 'canUpgrade' => false])
{{-- LockedState (EX-64 kit, EX-50): the locked page card. The modal uses the same body without the card. --}}
<x-filament::section>
    <x-locked-state-body :feature="$feature" :tier="$tier" :can-upgrade="$canUpgrade" />
</x-filament::section>
