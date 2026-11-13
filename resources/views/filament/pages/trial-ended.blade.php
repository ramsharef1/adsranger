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
            @if ($canUpgrade && ($readOnlyUntil || $closed))
                <div style="border-top:1px solid rgba(127,127,127,.2);padding-top:12px">
                    @if ($extension && $extension->status === 'open')
                        <p>{{ __('We got your request for more time. The answer will show here.') }}</p>
                    @elseif ($extension && $extension->status === 'granted')
                        <p>{{ __('More time granted: :n days.', ['n' => $extension->days]) }}</p>
                    @else
                        @if ($extension && $extension->status === 'declined')<p>{{ __('Your last request for more time was not granted.') }}</p>@endif
                        <form wire:submit="requestMoreTime" style="display:grid;gap:8px">
                            <label for="trial-reason">{{ __('Need more time to try AdsRanger? Tell us why in one line.') }}</label>
                            <input id="trial-reason" type="text" wire:model="reason" maxlength="300" style="min-height:44px;border-radius:8px;padding:6px 10px;border:1px solid rgba(127,127,127,.4);background:transparent">
                            @error('reason')<p style="color:#dc2626">{{ $message }}</p>@enderror
                            <div><x-filament::button type="submit" color="gray" style="min-height:44px">{{ __('Ask for more time') }}</x-filament::button></div>
                        </form>
                    @endif
                </div>
            @endif
            <p style="font-size:13px;opacity:.75">{{ TrialNotice::helpHtml() }}</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
