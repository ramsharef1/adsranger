<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('Daily metrics') }}</x-slot>
        <x-slot name="description">{{ __('Spend, impressions, clicks and results per day, ad account and campaign for the period you choose. Files open in Excel with Arabic intact.') }}</x-slot>
        @if ($restricted)
            <p style="font-size:13px;opacity:.75;margin-bottom:10px">{{ __('Only the clients you can see are included.') }}</p>
        @endif
        {{ $this->metricsAction }}
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Clients and ad accounts') }}</x-slot>
        <x-slot name="description">{{ __('Your client list with targets, and every ad account with its network, client and last sync.') }}</x-slot>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
            {{ $this->clientsAction }}
            {{ $this->accountsAction }}
        </div>
    </x-filament::section>

    @if ($canZip)
        <x-filament::section>
            <x-slot name="heading">{{ __('All workspace data') }}</x-slot>
            <x-slot name="description">{{ __('Everything this workspace holds, all dates, as CSV files in one ZIP: clients, ad accounts, campaigns, daily metrics, alerts, members, client links and report schedules. Network passwords, tokens and keys are never included. Owner only.') }}</x-slot>
            {{ $this->workspaceAction }}
        </x-filament::section>
    @endif
</x-filament-panels::page>
