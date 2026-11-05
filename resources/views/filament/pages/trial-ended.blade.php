@php use App\Support\TrialNotice; @endphp
<x-filament-panels::page>
    <x-filament::section>
        <div style="display:grid;gap:12px;font-size:15px;line-height:1.8" data-trial-ended>
            @if ($closed)
                <p><strong>{{ __('Your trial ended — choose a plan') }}</strong></p>
                <p>{{ __('This workspace is closed. Your data is kept until :date; the owner can still download it.', ['date' => TrialNotice::date($deleteOn)]) }}</p>
                <p>{{ __('After that date we delete the workspace: clients, ad accounts, results, alerts and reports.') }}</p>
            @elseif ($readOnlyUntil)
                <p><strong>{{ __('Your trial ended — choose a plan') }}</strong></p>
                <p>{{ __('This workspace is read-only until :date. You can view and download your data; changes, syncs and alerts are paused.', ['date' => TrialNotice::date($readOnlyUntil)]) }}</p>
            @else
                <p>{{ __('Your trial is running.') }}</p>
            @endif
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                @if ($canZip)
                    <x-filament::button tag="a" :href="$zipUrl" icon="heroicon-o-archive-box-arrow-down">{{ __('Download all workspace data') }}</x-filament::button>
                @endif
                @if ($canUpgrade)
                    <x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\PlanUsage::getUrl()">{{ __('See plans') }}</x-filament::button>
                @else
                    <p>{{ __('Ask your workspace owner to choose a plan.') }}</p>
                @endif
            </div>
            <p style="font-size:13px;opacity:.75">{{ TrialNotice::helpHtml() }}</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
