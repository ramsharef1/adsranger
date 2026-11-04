<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="description">These choices are yours only and apply in every workspace you belong to. Emails come from noreply@adsranger.com.</x-slot>
        <label style="display:flex;gap:10px;align-items:flex-start;padding:8px 0">
            <input type="checkbox" wire:model="alerts" style="margin-top:4px">
            <span><strong>Email me new high alerts</strong><br><span style="opacity:.65;font-size:13px">Tracking breaks, spend with no results, tracking issues and connection problems, for the clients you can see.</span></span>
        </label>
        <label style="display:flex;gap:10px;align-items:flex-start;padding:8px 0">
            <input type="checkbox" wire:model="digest" style="margin-top:4px">
            <span><strong>Morning digest at 08:00</strong><br><span style="opacity:.65;font-size:13px">Yesterday's results per client and the open alerts, in the workspace time zone.</span></span>
        </label>
        <x-filament::button wire:click="save" style="margin-top:8px">Save</x-filament::button>
    </x-filament::section>
</x-filament-panels::page>
